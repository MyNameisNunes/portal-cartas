<?php
declare(strict_types=1);

/** POST /api/login.php  ->  { "email": "...", "senha": "..." } */

require_once __DIR__ . '/auth.php';

exigir_metodo(['POST']);

$dados = entrada();
$email = texto($dados, 'email');
$senha = is_scalar($dados['senha'] ?? null) ? (string) $dados['senha'] : '';

if ($email === null || $senha === '') {
    erro('Informe e-mail e senha para entrar.', 422);
}

try {
    $usuario = autenticar($email, $senha);
} catch (Throwable $falha) {
    error_log('[login] ' . $falha->getMessage());
    erro('Não foi possível validar suas credenciais agora. Tente novamente.', 500);
}

if ($usuario === null) {
    // Mensagem generica de proposito: nao revela se o e-mail existe.
    erro('E-mail ou senha inválidos.', 401);
}

responder(['success' => true, 'usuario' => $usuario]);
