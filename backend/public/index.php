<?php

require_once __DIR__ . '/../src/database.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:8080');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
	http_response_code(204);
	exit;
}

function respond($payload, $status = 200) {
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function card_payload(array $card) {
	$card['image'] = $card['image_path'] ?: $card['image_url'];
	$card['image_reference'] = $card['image_reference'] ?: $card['image_url'];
	return $card;
}

function request_data() {
	$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
	if (stripos($contentType, 'application/json') !== false) {
		return json_decode(file_get_contents('php://input'), true) ?: [];
	}
	return $_POST;
}

function require_auth() {
	if (empty($_SESSION['user_id'])) respond(['error' => 'Autenticação necessária'], 401);
}

function valid_card_data(array $data) {
	$games = ['magic', 'pokemon', 'yugioh', 'onepiece', 'fab'];
	$name = trim((string) ($data['name_en'] ?? ''));
	$game = trim((string) ($data['game'] ?? ''));
	if ($name === '' || !in_array($game, $games, true)) respond(['error' => 'Nome e TCG válido são obrigatórios'], 422);
	return [
		'name_en' => $name,
		'name_pt' => trim((string) ($data['name_pt'] ?? '')) ?: null,
		'game' => $game,
		'edition_id' => trim((string) ($data['edition_id'] ?? '')) ?: null,
		'rarity' => trim((string) ($data['rarity'] ?? '')) ?: null,
		'image_url' => trim((string) ($data['image_url'] ?? '')) ?: null,
		'image_reference' => trim((string) ($data['image_reference'] ?? '')) ?: null,
	];
}

function card_by_id(PDO $pdo, $id) {
	$statement = $pdo->prepare('SELECT id, name_en, name_pt, game, edition_id, image_path, image_url, image_reference, rarity, created_at FROM cards WHERE id = :id');
	$statement->execute(['id' => $id]);
	$card = $statement->fetch();
	return $card ? card_payload($card) : null;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
	$pdo = get_pdo();
	$data = request_data();

	if ($path === '/login' && $method === 'POST') {
		$statement = $pdo->prepare('SELECT id, email, password, name FROM users WHERE email = :email');
		$statement->execute(['email' => trim((string) ($data['email'] ?? ''))]);
		$user = $statement->fetch();
		if (!$user || !password_verify((string) ($data['password'] ?? ''), $user['password'])) respond(['error' => 'E-mail ou senha inválidos'], 401);
		$_SESSION['user_id'] = $user['id'];
		respond(['user' => ['id' => $user['id'], 'email' => $user['email'], 'name' => $user['name']]]);
	}

	if ($path === '/register' && $method === 'POST') {
		$email = trim((string) ($data['email'] ?? ''));
		$password = (string) ($data['password'] ?? '');
		$name = trim((string) ($data['name'] ?? ''));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $name === '') respond(['error' => 'Informe nome, e-mail válido e senha com 6 caracteres ou mais'], 422);
		$statement = $pdo->prepare('INSERT INTO users (email, password, name) VALUES (:email, :password, :name)');
		try { $statement->execute(['email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT), 'name' => $name]); }
		catch (PDOException $error) { respond(['error' => 'Este e-mail já está cadastrado'], 409); }
		$_SESSION['user_id'] = $pdo->lastInsertId();
		respond(['message' => 'Cadastro realizado com sucesso'], 201);
	}

	if ($path === '/forgot-password' && $method === 'POST') {
		respond(['message' => 'Se o e-mail estiver cadastrado, enviaremos as instruções de recuperação.']);
	}

	if ($path === '/logout' && $method === 'POST') {
		$_SESSION = [];
		session_destroy();
		respond(['message' => 'Sessão encerrada']);
	}

	if (!preg_match('#^/cards(?:/(\d+))?$#', $path, $matches)) respond(['error' => 'Rota não encontrada'], 404);
	$id = $matches[1] ?? null;
	if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) require_auth();

	if ($method === 'POST' && !$id) {
		$card = valid_card_data($data);
		$statement = $pdo->prepare('INSERT INTO cards (name_en, name_pt, game, edition_id, rarity, image_url, image_reference) VALUES (:name_en, :name_pt, :game, :edition_id, :rarity, :image_url, :image_reference)');
		$statement->execute($card);
		respond(card_by_id($pdo, $pdo->lastInsertId()), 201);
	}
	if ($method === 'PUT' && $id) {
		$card = valid_card_data($data);
		$card['id'] = $id;
		$statement = $pdo->prepare('UPDATE cards SET name_en = :name_en, name_pt = :name_pt, game = :game, edition_id = :edition_id, rarity = :rarity, image_url = :image_url, image_reference = :image_reference WHERE id = :id');
		$statement->execute($card);
		respond(card_by_id($pdo, $id) ?: ['error' => 'Carta não encontrada'], $statement->rowCount() || card_by_id($pdo, $id) ? 200 : 404);
	}
	if ($method === 'DELETE' && $id) {
		$statement = $pdo->prepare('DELETE FROM cards WHERE id = :id');
		$statement->execute(['id' => $id]);
		if (!$statement->rowCount()) respond(['error' => 'Carta não encontrada'], 404);
		respond(null, 204);
	}

	if ($id) {
		$card = card_by_id($pdo, $id);
		respond($card ?: ['error' => 'Carta não encontrada'], $card ? 200 : 404);
	}

	$page = max(1, (int) ($_GET['page'] ?? 1));
	$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
	$offset = ($page - 1) * $limit;
	$query = 'SELECT id, name_en, name_pt, game, edition_id, image_path, image_url, image_reference, rarity, created_at FROM cards';
	$params = [];
	$conditions = [];

	if (!empty($_GET['game'])) {
		$conditions[] = 'game = :game';
		$params['game'] = $_GET['game'];
	}
	if (!empty($_GET['search'])) {
		$conditions[] = '(name_en LIKE :search OR name_pt LIKE :search)';
		$params['search'] = '%' . $_GET['search'] . '%';
	}
	if ($conditions) $query .= ' WHERE ' . implode(' AND ', $conditions);
	$query .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
	$statement = $pdo->prepare($query);
	foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
	$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
	$statement->bindValue(':offset', $offset, PDO::PARAM_INT);
	$statement->execute();
	respond(array_map('card_payload', $statement->fetchAll()));
} catch (Throwable $error) {
	error_log($error->getMessage());
	respond(['error' => 'Não foi possível consultar as cartas'], 500);
}
