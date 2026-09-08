<?php
declare(strict_types=1);

/**
 * GET /api/edicoes.php?game={magic|pokemon|yugioh|onepiece|fab}
 *
 * Catalogo publico: sao dados de referencia estaticos, sem informacao de
 * negocio, entao o endpoint nao exige sessao. As rotas de cartas exigem.
 */

require_once __DIR__ . '/bootstrap.php';

exigir_metodo(['GET']);

$jogo = trim((string) ($_GET['game'] ?? ''));

if ($jogo === '') {
    erro('Informe o card game na query string, por exemplo: ?game=magic', 422, [
        'jogos_suportados' => jogos_suportados(),
    ]);
}

$edicoes = edicoes_do_jogo($jogo);

if ($edicoes === null) {
    erro(sprintf('Card game "%s" não é suportado.', $jogo), 404, [
        'jogos_suportados' => jogos_suportados(),
    ]);
}

responder([
    'success' => true,
    'game'    => $jogo,
    'edicoes' => $edicoes,
]);
