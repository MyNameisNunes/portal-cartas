<?php
declare(strict_types=1);

/**
 * Conexao PDO unica por request.
 *
 * As credenciais vem de variaveis de ambiente definidas no docker-compose.yml,
 * com defaults que apontam para o servico "db" do compose.
 */
function conexao(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host  = getenv('DB_HOST') ?: 'db';
    $porta = getenv('DB_PORT') ?: '3306';
    $banco = getenv('DB_NAME') ?: 'portal_cartas';
    $user  = getenv('DB_USER') ?: 'portal';
    $senha = getenv('DB_PASS') ?: 'portal';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $porta, $banco);

    $pdo = new PDO($dsn, $user, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Prepares reais no servidor: sem interpolacao de string em lugar nenhum.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
