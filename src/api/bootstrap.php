<?php
declare(strict_types=1);

/**
 * Base comum de todos os endpoints em /api.
 * Cuida de sessao, headers JSON, leitura de corpo e respostas padronizadas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/edicoes.php';
require_once __DIR__ . '/../config/colecoes.php';
require_once __DIR__ . '/../config/plataformas.php';
require_once __DIR__ . '/../config/sessao.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** Encerra o request devolvendo JSON. */
function responder(mixed $dados, int $status = 200): never
{
    http_response_code($status);

    if ($status !== 204) {
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}

/** Encerra o request com uma mensagem de erro amigavel. */
function erro(string $mensagem, int $status = 400, array $extra = []): never
{
    responder(['success' => false, 'error' => $mensagem] + $extra, $status);
}

/**
 * Metodo HTTP efetivo. Aceita o override _method para permitir enviar
 * multipart/form-data (upload de arquivo) em uma edicao, ja que o PHP nao
 * parseia o corpo de um PUT real.
 */
function metodo_http(): string
{
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($metodo === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper(trim((string) $_POST['_method']));
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $override;
        }
    }

    return $metodo;
}

/** Responde 405 com o header Allow quando o metodo nao e suportado. */
function exigir_metodo(array $permitidos): string
{
    $metodo = metodo_http();

    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        erro('Método não permitido para este endpoint.', 405);
    }

    return $metodo;
}

/**
 * Dados de entrada, tanto de JSON quanto de formulario.
 * Retorna sempre um array associativo.
 */
function entrada(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($tipo, 'application/json') !== false) {
        $bruto = file_get_contents('php://input');

        if ($bruto === false || trim($bruto) === '') {
            return [];
        }

        $dados = json_decode($bruto, true);

        if (!is_array($dados)) {
            erro('Corpo da requisição não é um JSON válido.', 400);
        }

        return $dados;
    }

    // multipart/form-data e application/x-www-form-urlencoded
    return $_POST;
}

/** Inteiro de um campo de entrada; retorna null quando ausente ou nao numerico. */
function inteiro(array $dados, string $campo): ?int
{
    $valor = $dados[$campo] ?? null;

    if (!is_scalar($valor) || !preg_match('/^-?\d+$/', trim((string) $valor))) {
        return null;
    }

    return (int) trim((string) $valor);
}

/**
 * Le um id positivo de ?{$campo}=N, com fallback para o corpo da requisicao.
 * Usado pelos endpoints que operam sobre um unico registro.
 */
function id_da_query(string $campo, array $dados, string $exemplo): int
{
    $id = inteiro([$campo => $_GET[$campo] ?? $dados[$campo] ?? null], $campo);

    if ($id === null || $id < 1) {
        erro(sprintf('Informe o %s, por exemplo: %s', $campo, $exemplo), 422);
    }

    return $id;
}

/** Texto limpo de um campo de entrada; retorna null quando vazio. */
function texto(array $dados, string $campo): ?string
{
    $valor = $dados[$campo] ?? null;

    if (!is_scalar($valor)) {
        return null;
    }

    $valor = trim((string) $valor);

    return $valor === '' ? null : $valor;
}
