<?php
declare(strict_types=1);

/** POST /api/logout.php  ->  encerra a sessao atual */

require_once __DIR__ . '/auth.php';

exigir_metodo(['POST']);

encerrar_sessao();

responder(['success' => true, 'message' => 'Sessão encerrada com sucesso.']);
