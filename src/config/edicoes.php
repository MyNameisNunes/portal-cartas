<?php
declare(strict_types=1);

/**
 * Catalogo de edicoes por card game.
 *
 * Fonte de verdade unica: alimenta o endpoint GET /api/edicoes.php e tambem
 * valida o par (card_game, edicao_id) recebido em POST/PUT de cartas. O nome
 * da edicao nunca vem do cliente, e sempre resolvido aqui.
 */
function catalogo_edicoes(): array
{
    return [
        'magic' => [
            ['id' => 'dom',   'name' => 'Dominaria'],
            ['id' => 'war',   'name' => 'War of the Spark'],
            ['id' => 'eld',   'name' => 'Throne of Eldraine'],
            ['id' => 'hob',   'name' => 'The Hobbit'],
            ['id' => 'msh',   'name' => 'Marvel Super Heroes'],
        ],
        'pokemon' => [
            ['id' => 'base1', 'name' => 'Base Set'],
            ['id' => 'swsh1', 'name' => 'Sword & Shield'],
            ['id' => 'sv1',   'name' => 'Scarlet & Violet'],
            ['id' => '30c',   'name' => '30th Celebration'],
            ['id' => 'cri',   'name' => 'Chaos Rising'],
        ],
        'yugioh' => [
            ['id' => 'lob',   'name' => 'Legend of Blue Eyes White Dragon'],
            ['id' => 'mrd',   'name' => 'Metal Raiders'],
            ['id' => 'sdy',   'name' => 'Starter Deck: Yugi'],
            ['id' => 'rotd',  'name' => 'Rise of the Duelist'],
            ['id' => 'blzd',  'name' => 'Blazing Dominion'],
        ],
        'onepiece' => [
            ['id' => 'op01',  'name' => 'Romance Dawn'],
            ['id' => 'op02',  'name' => 'Paramount War'],
            ['id' => 'op03',  'name' => 'Pillars of Strength'],
            ['id' => 'op04',  'name' => 'Kingdoms of Intrigue'],
            ['id' => 'op05',  'name' => 'Awakening of the New Era'],
        ],
        'fab' => [
            ['id' => 'wtr',   'name' => 'Welcome to Rathe'],
            ['id' => 'arc',   'name' => 'Arcane Rising'],
            ['id' => 'mon',   'name' => 'Monarch'],
            ['id' => 'ele',   'name' => 'Tales of Aria'],
            ['id' => 'out',   'name' => 'Outsiders'],
        ],
    ];
}

/** Chaves aceitas pela coluna ENUM cartas.card_game. */
function jogos_suportados(): array
{
    return array_keys(catalogo_edicoes());
}

/** Rotulos amigaveis usados no frontend. */
function rotulos_dos_jogos(): array
{
    return [
        'magic'    => 'Magic: The Gathering',
        'pokemon'  => 'Pokémon',
        'yugioh'   => 'Yu-Gi-Oh!',
        'onepiece' => 'One Piece Card Game',
        'fab'      => 'Flesh and Blood',
    ];
}

/** Lista de edicoes de um jogo, ou null se o jogo nao existe. */
function edicoes_do_jogo(string $jogo): ?array
{
    return catalogo_edicoes()[$jogo] ?? null;
}

/** Resolve o nome da edicao a partir do par (jogo, id), ou null se invalido. */
function nome_da_edicao(string $jogo, string $edicaoId): ?string
{
    foreach (edicoes_do_jogo($jogo) ?? [] as $edicao) {
        if (strcasecmp($edicao['id'], $edicaoId) === 0) {
            return $edicao['name'];
        }
    }

    return null;
}
