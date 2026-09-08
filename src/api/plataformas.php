<?php
declare(strict_types=1);

/**
 * GET /api/plataformas.php?game={magic|pokemon|yugioh|onepiece|fab}
 *
 * Catalogo publico das plataformas online que aceitam vinculo de deck.
 * Mesma natureza de /api/edicoes.php: dado de referencia estatico, sem
 * informacao de negocio, entao nao exige sessao.
 */

require_once __DIR__ . '/bootstrap.php';

exigir_metodo(['GET']);

$jogo = trim((string) ($_GET['game'] ?? ''));

if ($jogo === '') {
    erro('Informe o card game na query string, por exemplo: ?game=magic', 422, [
        'jogos_suportados' => jogos_suportados(),
    ]);
}

$plataformas = plataformas_do_jogo($jogo);

if ($plataformas === null) {
    erro(sprintf('Card game "%s" não é suportado.', $jogo), 404, [
        'jogos_suportados' => jogos_suportados(),
    ]);
}

responder([
    'success'     => true,
    'game'        => $jogo,
    'plataformas' => $plataformas,
]);
