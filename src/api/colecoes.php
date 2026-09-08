<?php
declare(strict_types=1);

/**
 * Colecoes: decks, binders e listas de desejo montados a partir do acervo.
 *
 *   GET    /api/colecoes.php                        lista (filtros: game, tipo, search)
 *   GET    /api/colecoes.php?id=1                   detalhe, com as cartas dentro
 *   POST   /api/colecoes.php                        cria
 *   PUT    /api/colecoes.php?id=1                   atualiza
 *   DELETE /api/colecoes.php?id=1                   remove (leva as cartas junto)
 *
 *   POST   /api/colecoes.php?id=1&acao=cartas       inclui/ajusta uma carta
 *   DELETE /api/colecoes.php?id=1&acao=cartas&carta_id=2   tira a carta da colecao
 *
 * Toda consulta e filtrada por usuario_id da sessao: uma colecao de outro
 * usuario responde 404, nunca 403 — nao confirma que o id existe.
 */

require_once __DIR__ . '/auth.php';

const COLECAO_COLUNAS = 'id, usuario_id, nome, descricao, card_game, tipo, created_at, updated_at';

/** Quantas capas de carta a listagem devolve por colecao, para o mosaico. */
const CAPAS_POR_COLECAO = 4;

// ------------------------------------------------------------------
// Leitura
// ------------------------------------------------------------------

/** Acrescenta os rotulos de jogo e tipo para o frontend nao precisar de um de-para. */
function apresentar_colecao(array $colecao): array
{
    $colecao['id']             = (int) $colecao['id'];
    $colecao['usuario_id']     = (int) $colecao['usuario_id'];
    $colecao['card_game_nome'] = rotulos_dos_jogos()[$colecao['card_game']] ?? $colecao['card_game'];
    $colecao['tipo_nome']      = tipos_de_colecao()[$colecao['tipo']] ?? $colecao['tipo'];

    if (isset($colecao['total_cartas'])) {
        $colecao['total_cartas']   = (int) $colecao['total_cartas'];
        $colecao['total_unidades'] = (int) $colecao['total_unidades'];
    }

    return $colecao;
}

/** Colecao pertencente ao usuario da sessao, ou null. */
function colecao_do_usuario(int $id, int $usuarioId): ?array
{
    $consulta = conexao()->prepare(
        'SELECT ' . COLECAO_COLUNAS . ' FROM colecoes WHERE id = :id AND usuario_id = :usuario LIMIT 1'
    );
    $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
    $colecao = $consulta->fetch();

    return $colecao ? apresentar_colecao($colecao) : null;
}

/** Interrompe com 404 quando a colecao nao existe ou e de outro usuario. */
function exigir_colecao(int $id, int $usuarioId): array
{
    $colecao = colecao_do_usuario($id, $usuarioId);

    if ($colecao === null) {
        erro('Coleção não encontrada.', 404);
    }

    return $colecao;
}

/** Cartas de uma colecao, na ordem em que aparecem no detalhe. */
function cartas_da_colecao(int $colecaoId): array
{
    $consulta = conexao()->prepare(
        'SELECT ct.id, ct.nome_en, ct.nome_pt, ct.card_game, ct.edicao_id, ct.edicao_nome,
                ct.raridade, ct.imagem_url, cc.quantidade, cc.origem
           FROM colecao_cartas cc
           JOIN cartas ct ON ct.id = cc.carta_id
          WHERE cc.colecao_id = :colecao
          ORDER BY ct.nome_en ASC'
    );
    $consulta->execute(['colecao' => $colecaoId]);

    return array_map(static function (array $carta): array {
        $carta['id']         = (int) $carta['id'];
        $carta['quantidade'] = (int) $carta['quantidade'];

        return $carta;
    }, $consulta->fetchAll());
}

/**
 * Ate CAPAS_POR_COLECAO imagens por colecao, para o mosaico da listagem.
 * Uma consulta so para todas as colecoes da pagina, em vez de uma por card.
 */
function capas_das_colecoes(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $consulta = conexao()->prepare(
        'SELECT cc.colecao_id, ct.nome_en, ct.imagem_url
           FROM colecao_cartas cc
           JOIN cartas ct ON ct.id = cc.carta_id
          WHERE cc.colecao_id IN (' . $marcadores . ')
          ORDER BY cc.colecao_id ASC, cc.created_at DESC, ct.id DESC'
    );
    $consulta->execute($ids);

    $capas = [];

    foreach ($consulta->fetchAll() as $linha) {
        $colecao = (int) $linha['colecao_id'];

        if (count($capas[$colecao] ?? []) >= CAPAS_POR_COLECAO) {
            continue;
        }

        $capas[$colecao][] = [
            'nome_en'    => $linha['nome_en'],
            'imagem_url' => $linha['imagem_url'],
        ];
    }

    return $capas;
}

function listar(int $usuarioId): never
{
    $pdo = conexao();

    $condicoes  = ['c.usuario_id = :usuario'];
    $parametros = [':usuario' => $usuarioId];

    $jogo = trim((string) ($_GET['game'] ?? ''));
    if ($jogo !== '' && $jogo !== 'all') {
        if (!in_array($jogo, jogos_suportados(), true)) {
            erro('Card game inválido no filtro.', 422);
        }
        $condicoes[]         = 'c.card_game = :game';
        $parametros[':game'] = $jogo;
    }

    $tipo = trim((string) ($_GET['tipo'] ?? ''));
    if ($tipo !== '' && $tipo !== 'all') {
        if (!in_array($tipo, tipos_suportados(), true)) {
            erro('Tipo de coleção inválido no filtro.', 422);
        }
        $condicoes[]         = 'c.tipo = :tipo';
        $parametros[':tipo'] = $tipo;
    }

    $busca = trim((string) ($_GET['search'] ?? ''));
    if ($busca !== '') {
        $condicoes[] = '(c.nome LIKE :busca_nome OR c.descricao LIKE :busca_desc)';

        // Escapa os curingas do LIKE para que % e _ sejam tratados como literais.
        $termo = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $busca) . '%';

        $parametros[':busca_nome'] = $termo;
        $parametros[':busca_desc'] = $termo;
    }

    $onde = ' WHERE ' . implode(' AND ', $condicoes);

    $consulta = $pdo->prepare(
        'SELECT c.id, c.usuario_id, c.nome, c.descricao, c.card_game, c.tipo,
                c.created_at, c.updated_at,
                COUNT(cc.carta_id)                  AS total_cartas,
                COALESCE(SUM(cc.quantidade), 0)     AS total_unidades
           FROM colecoes c
           LEFT JOIN colecao_cartas cc ON cc.colecao_id = c.id' . $onde . '
          GROUP BY c.id
          ORDER BY c.updated_at DESC, c.id DESC'
    );
    $consulta->execute($parametros);
    $colecoes = array_map('apresentar_colecao', $consulta->fetchAll());

    $capas = capas_das_colecoes(array_column($colecoes, 'id'));

    foreach ($colecoes as &$colecao) {
        $colecao['capas'] = $capas[$colecao['id']] ?? [];
    }
    unset($colecao);

    // Resumo do acervo inteiro, independente dos filtros ativos: alimenta os
    // indicadores do topo da pagina, que nao devem mudar ao filtrar a lista.
    $resumo = $pdo->prepare(
        "SELECT COUNT(*)                                          AS total,
                COUNT(DISTINCT card_game)                         AS jogos,
                SUM(CASE WHEN tipo = 'deck' THEN 1 ELSE 0 END)    AS decks
           FROM colecoes
          WHERE usuario_id = :usuario"
    );
    $resumo->execute(['usuario' => $usuarioId]);
    $totais = $resumo->fetch();

    $unidades = $pdo->prepare(
        'SELECT COALESCE(SUM(cc.quantidade), 0)
           FROM colecao_cartas cc
           JOIN colecoes c ON c.id = cc.colecao_id
          WHERE c.usuario_id = :usuario'
    );
    $unidades->execute(['usuario' => $usuarioId]);

    responder([
        'success'  => true,
        'total'    => count($colecoes),
        'resumo'   => [
            'total'    => (int) $totais['total'],
            'jogos'    => (int) $totais['jogos'],
            'decks'    => (int) $totais['decks'],
            'unidades' => (int) $unidades->fetchColumn(),
        ],
        'colecoes' => $colecoes,
    ]);
}

function exibir(int $id, int $usuarioId): never
{
    $colecao = exigir_colecao($id, $usuarioId);
    $cartas  = cartas_da_colecao($id);

    $colecao['cartas']         = $cartas;
    $colecao['total_cartas']   = count($cartas);
    $colecao['total_unidades'] = array_sum(array_column($cartas, 'quantidade'));

    responder(['success' => true, 'colecao' => $colecao]);
}

// ------------------------------------------------------------------
// Escrita
// ------------------------------------------------------------------

/** Normaliza e valida os campos da colecao. */
function campos_validados(array $dados): array
{
    $problemas = [];

    $nome = texto($dados, 'nome');
    if ($nome === null) {
        $problemas['nome'] = 'Dê um nome para a coleção.';
    } elseif (mb_strlen($nome) > 120) {
        $problemas['nome'] = 'O nome deve ter no máximo 120 caracteres.';
    }

    $descricao = texto($dados, 'descricao');
    if ($descricao !== null && mb_strlen($descricao) > 255) {
        $problemas['descricao'] = 'A descrição deve ter no máximo 255 caracteres.';
    }

    $jogo = texto($dados, 'card_game');
    if ($jogo === null) {
        $problemas['card_game'] = 'Selecione o card game.';
    } elseif (!in_array($jogo, jogos_suportados(), true)) {
        $problemas['card_game'] = 'Card game inválido.';
    }

    $tipo = texto($dados, 'tipo');
    if ($tipo === null) {
        $problemas['tipo'] = 'Escolha o tipo da coleção.';
    } elseif (!in_array($tipo, tipos_suportados(), true)) {
        $problemas['tipo'] = 'Tipo de coleção inválido.';
    }

    if ($problemas) {
        erro('Revise os campos destacados no formulário.', 422, ['campos' => $problemas]);
    }

    return [
        'nome'      => $nome,
        'descricao' => $descricao,
        'card_game' => $jogo,
        'tipo'      => $tipo,
    ];
}

function criar(int $usuarioId): never
{
    $campos               = campos_validados(entrada());
    $campos['usuario_id'] = $usuarioId;

    $consulta = conexao()->prepare(
        'INSERT INTO colecoes (usuario_id, nome, descricao, card_game, tipo)
         VALUES (:usuario_id, :nome, :descricao, :card_game, :tipo)'
    );
    $consulta->execute($campos);

    responder([
        'success' => true,
        'message' => 'Coleção criada com sucesso.',
        'colecao' => colecao_do_usuario((int) conexao()->lastInsertId(), $usuarioId),
    ], 201);
}

function atualizar(int $usuarioId): never
{
    $dados = entrada();
    $id    = id_da_query('id', $dados, '/api/colecoes.php?id=1');
    $atual = exigir_colecao($id, $usuarioId);

    $campos = campos_validados($dados);

    // Trocar o card game invalidaria as cartas ja incluidas: a regra e explicita
    // em vez de silenciosa, para o usuario nao perder o conteudo sem perceber.
    if ($campos['card_game'] !== $atual['card_game'] && cartas_da_colecao($id) !== []) {
        erro('Esvazie a coleção antes de mudar o card game dela.', 409, [
            'campos' => ['card_game' => 'A coleção já tem cartas deste card game.'],
        ]);
    }

    $campos['id']      = $id;
    $campos['usuario'] = $usuarioId;

    $consulta = conexao()->prepare(
        'UPDATE colecoes SET
            nome      = :nome,
            descricao = :descricao,
            card_game = :card_game,
            tipo      = :tipo
         WHERE id = :id AND usuario_id = :usuario'
    );
    $consulta->execute($campos);

    responder([
        'success' => true,
        'message' => 'Coleção atualizada com sucesso.',
        'colecao' => colecao_do_usuario($id, $usuarioId),
    ]);
}

function remover(int $usuarioId): never
{
    $id = id_da_query('id', entrada(), '/api/colecoes.php?id=1');
    exigir_colecao($id, $usuarioId);

    $consulta = conexao()->prepare('DELETE FROM colecoes WHERE id = :id AND usuario_id = :usuario');
    $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);

    responder(['success' => true, 'message' => 'Coleção excluída com sucesso.']);
}

// ------------------------------------------------------------------
// Cartas dentro da colecao
// ------------------------------------------------------------------

function incluir_carta(int $usuarioId): never
{
    $dados   = entrada();
    $id      = id_da_query('id', $dados, '/api/colecoes.php?id=1&acao=cartas');
    $colecao = exigir_colecao($id, $usuarioId);

    $cartaId = inteiro($dados, 'carta_id') ?? inteiro($_GET, 'carta_id');

    if ($cartaId === null || $cartaId < 1) {
        erro('Escolha a carta que entra na coleção.', 422, [
            'campos' => ['carta_id' => 'Selecione uma carta do acervo.'],
        ]);
    }

    $quantidade = inteiro($dados, 'quantidade') ?? 1;

    if ($quantidade < 1 || $quantidade > 99) {
        erro('A quantidade deve ficar entre 1 e 99.', 422, [
            'campos' => ['quantidade' => 'Informe um número entre 1 e 99.'],
        ]);
    }

    $consulta = conexao()->prepare('SELECT id, nome_en, card_game FROM cartas WHERE id = :id LIMIT 1');
    $consulta->execute(['id' => $cartaId]);
    $carta = $consulta->fetch();

    if (!$carta) {
        erro('Carta não encontrada no acervo.', 404);
    }

    // Uma colecao e sempre de um card game so: e o que garante que os filtros
    // por TCG continuem verdadeiros depois de qualquer inclusao.
    if ($carta['card_game'] !== $colecao['card_game']) {
        erro(
            sprintf('“%s” não é de %s.', $carta['nome_en'], $colecao['card_game_nome']),
            422,
            ['campos' => ['carta_id' => 'Escolha uma carta do mesmo card game da coleção.']]
        );
    }

    $insercao = conexao()->prepare(
        'INSERT INTO colecao_cartas (colecao_id, carta_id, quantidade, origem)
         VALUES (:colecao, :carta, :quantidade, :origem)
         ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)'
    );
    $insercao->execute([
        'colecao'    => $id,
        'carta'      => $cartaId,
        'quantidade' => $quantidade,
        'origem'     => 'manual',
    ]);

    tocar_colecao($id);

    responder([
        'success' => true,
        'message' => sprintf('“%s” está na coleção.', $carta['nome_en']),
        'cartas'  => cartas_da_colecao($id),
    ]);
}

function excluir_carta(int $usuarioId): never
{
    $dados = entrada();
    $id    = id_da_query('id', $dados, '/api/colecoes.php?id=1&acao=cartas&carta_id=2');
    exigir_colecao($id, $usuarioId);

    $cartaId = inteiro($_GET, 'carta_id') ?? inteiro($dados, 'carta_id');

    if ($cartaId === null || $cartaId < 1) {
        erro('Informe a carta que sai da coleção.', 422);
    }

    $consulta = conexao()->prepare(
        'DELETE FROM colecao_cartas WHERE colecao_id = :colecao AND carta_id = :carta'
    );
    $consulta->execute(['colecao' => $id, 'carta' => $cartaId]);

    if ($consulta->rowCount() === 0) {
        erro('Essa carta não está nesta coleção.', 404);
    }

    tocar_colecao($id);

    responder([
        'success' => true,
        'message' => 'Carta removida da coleção.',
        'cartas'  => cartas_da_colecao($id),
    ]);
}

/**
 * Mexer nas cartas nao dispara o ON UPDATE de colecoes, entao o carimbo e
 * atualizado na mao: a listagem ordena por updated_at e o usuario espera ver
 * a colecao que acabou de mexer no topo.
 */
function tocar_colecao(int $id): void
{
    $consulta = conexao()->prepare('UPDATE colecoes SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $consulta->execute(['id' => $id]);
}

// ------------------------------------------------------------------
// Dispatch
// ------------------------------------------------------------------

$metodo  = exigir_metodo(['GET', 'POST', 'PUT', 'DELETE']);
$usuario = exigir_autenticacao();
$acao    = trim((string) ($_GET['acao'] ?? ''));

if ($acao !== '' && $acao !== 'cartas') {
    erro(sprintf('Ação "%s" não existe neste endpoint.', $acao), 404);
}

try {
    match (true) {
        $metodo === 'POST'   && $acao === 'cartas' => incluir_carta($usuario['id']),
        $metodo === 'DELETE' && $acao === 'cartas' => excluir_carta($usuario['id']),
        $metodo === 'GET'    => isset($_GET['id'])
            ? exibir(id_da_query('id', [], '/api/colecoes.php?id=1'), $usuario['id'])
            : listar($usuario['id']),
        $metodo === 'POST'   => criar($usuario['id']),
        $metodo === 'PUT'    => atualizar($usuario['id']),
        $metodo === 'DELETE' => remover($usuario['id']),
    };
} catch (PDOException $falha) {
    error_log('[colecoes] ' . $falha->getMessage());
    erro('Não foi possível concluir a operação no banco de dados.', 500);
}
