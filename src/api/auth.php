<?php
declare(strict_types=1);

/**
 * Guarda de autenticacao das rotas de API.
 *
 * A sessao em si (login, logout, usuario atual) vive em config/sessao.php,
 * que tambem e usado pelas paginas index.php e dashboard.php.
 */

require_once __DIR__ . '/bootstrap.php';

/** Interrompe o request com 401 quando nao ha sessao valida. */
function exigir_autenticacao(): array
{
    $usuario = usuario_atual();

    if ($usuario === null) {
        erro('Sessão expirada. Faça login novamente para continuar.', 401);
    }

    return $usuario;
}
