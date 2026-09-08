<?php
declare(strict_types=1);

/**
 * Sessao nativa do PHP, compartilhada entre as paginas (index/dashboard)
 * e os endpoints de /api. Nao emite nenhum header de conteudo, justamente
 * para poder ser incluida tanto por HTML quanto por JSON.
 */

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

/** Usuario da sessao atual, ou null se nao autenticado. */
function usuario_atual(): ?array
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id'    => (int) $_SESSION['usuario_id'],
        'nome'  => (string) ($_SESSION['usuario_nome'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
    ];
}

/**
 * Valida as credenciais e abre a sessao.
 * Retorna o usuario autenticado ou null quando e-mail ou senha nao conferem.
 */
function autenticar(string $email, string $senha): ?array
{
    $consulta = conexao()->prepare(
        'SELECT id, nome, email, senha FROM usuarios WHERE email = :email LIMIT 1'
    );
    $consulta->execute(['email' => $email]);
    $usuario = $consulta->fetch();

    // O password_verify roda mesmo sem usuario, contra um hash descartavel, para
    // manter o tempo de resposta parecido e nao revelar quais e-mails existem.
    $hash = $usuario['senha'] ?? '$2y$10$L3xojzqFwwWNjgjzyIk0cOfgAnvPvFUnEtSapFiUnlCBMdNq6itre';

    if (!password_verify($senha, $hash) || !$usuario) {
        return null;
    }

    // Evita fixacao de sessao: o id anterior ao login e descartado.
    session_regenerate_id(true);

    $_SESSION['usuario_id']    = (int) $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];

    return [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
    ];
}

/** Fecha a sessao e remove o cookie do navegador. */
function encerrar_sessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $parametros['path'] ?: '/',
            'domain'   => $parametros['domain'],
            'secure'   => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
}
