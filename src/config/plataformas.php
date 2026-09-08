<?php
declare(strict_types=1);

/**
 * Catalogo de plataformas online por card game.
 *
 * Fonte de verdade unica, no mesmo espirito de config/edicoes.php: alimenta o
 * endpoint GET /api/plataformas.php e valida o par (card_game, plataforma)
 * recebido em POST de importacoes. O nome da plataforma nunca vem do cliente.
 *
 * "identificador" descreve o que o usuario precisa colar para que o vinculo
 * seja feito — cada plataforma expoe o deck de um jeito diferente.
 */
function catalogo_plataformas(): array
{
    return [
        'magic' => [
            ['id' => 'moxfield',   'name' => 'Moxfield',            'identificador' => 'ID do deck no Moxfield',      'exemplo' => 'AbCd1234EfGh'],
            ['id' => 'archidekt',  'name' => 'Archidekt',           'identificador' => 'ID numérico do deck',         'exemplo' => '1938472'],
            ['id' => 'mtgarena',   'name' => 'MTG Arena',           'identificador' => 'Seu nick no Arena',           'exemplo' => 'Jogador#12345'],
        ],
        'pokemon' => [
            ['id' => 'ptcglive',   'name' => 'Pokémon TCG Live',    'identificador' => 'Código de compartilhamento',  'exemplo' => 'xKp29d-Fj1LmQ'],
            ['id' => 'limitless',  'name' => 'Limitless TCG',       'identificador' => 'Slug do deck',                'exemplo' => 'charizard-ex-pidgeot'],
        ],
        'yugioh' => [
            ['id' => 'masterduel', 'name' => 'Master Duel',         'identificador' => 'Código do deck',              'exemplo' => 'A1B2C3D4'],
            ['id' => 'ygoprodeck', 'name' => 'YGOPRODeck',          'identificador' => 'URL ou ID do deck',           'exemplo' => '1094821'],
        ],
        'onepiece' => [
            ['id' => 'egman',      'name' => 'Egman Events',        'identificador' => 'ID da lista publicada',       'exemplo' => 'OP-93412'],
            ['id' => 'opnexus',    'name' => 'OP Nexus',            'identificador' => 'Slug do deck',                'exemplo' => 'zoro-agressivo'],
        ],
        'fab' => [
            ['id' => 'fabrary',    'name' => 'FaBrary',             'identificador' => 'ID do deck no FaBrary',       'exemplo' => 'kJqWzP'],
            ['id' => 'fabdb',      'name' => 'FaB DB',              'identificador' => 'Slug do deck',                'exemplo' => 'rhinar-agressivo'],
        ],
    ];
}

/** Lista de plataformas de um jogo, ou null se o jogo nao existe. */
function plataformas_do_jogo(string $jogo): ?array
{
    return catalogo_plataformas()[$jogo] ?? null;
}

/** Resolve os dados da plataforma a partir do par (jogo, id), ou null se invalido. */
function plataforma_por_id(string $jogo, string $plataformaId): ?array
{
    foreach (plataformas_do_jogo($jogo) ?? [] as $plataforma) {
        if (strcasecmp($plataforma['id'], $plataformaId) === 0) {
            return $plataforma;
        }
    }

    return null;
}

/** Rotulo amigavel da plataforma, com fallback para o proprio id. */
function nome_da_plataforma(string $jogo, string $plataformaId): string
{
    return plataforma_por_id($jogo, $plataformaId)['name'] ?? $plataformaId;
}

/**
 * Rotulos dos estados de um vinculo. Espelham o ENUM importacoes.status.
 *
 * "sincronizando" existe para o caso de a sincronizacao virar assincrona:
 * hoje ela roda dentro do request e o registro nunca fica parado nesse estado.
 */
function rotulos_de_status(): array
{
    return [
        'pendente'      => 'Aguardando sincronização',
        'sincronizando' => 'Sincronizando…',
        'concluida'     => 'Sincronizado',
        'erro'          => 'Falhou',
    ];
}

/** Chaves aceitas pela coluna ENUM importacoes.status. */
function status_suportados(): array
{
    return array_keys(rotulos_de_status());
}
