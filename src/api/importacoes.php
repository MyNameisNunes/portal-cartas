<?php
declare(strict_types=1);

/**
 * Importacoes: o vinculo entre um deck offline (uma colecao do tipo deck) e o
 * mesmo deck hospedado em uma plataforma online.
 *
 *   GET    /api/importacoes.php                       lista (filtros: game, status)
 *   GET    /api/importacoes.php?id=1                  detalhe
 *   POST   /api/importacoes.php                       cria o vinculo
 *   POST   /api/importacoes.php?id=1&acao=sincronizar puxa o deck online
 *   DELETE /api/importacoes.php?id=1                  desfaz o vinculo
 *
 * O handshake com a plataforma e SIMULADO: nao ha chamada HTTP para fora. O
 * resto e real — a sincronizacao le o acervo, escreve em colecao_cartas com
 * origem 'importacao' e persiste contadores, mensagem e carimbo de tempo. Para
 * plugar uma plataforma de verdade basta trocar o corpo de deck_online().
 */

require_once __DIR__ . '/auth.php';

const IMPORTACAO_COLUNAS = 'i.id, i.usuario_id, i.colecao_id, i.card_game, i.plataforma,
    i.plataforma_nome, i.identificador, i.status, i.cartas_encontradas, i.cartas_vinculadas,
    i.mensagem, i.sincronizado_em, i.created_at, i.updated_at';

// ------------------------------------------------------------------
// Leitura
// ------------------------------------------------------------------

/** Acrescenta os rotulos que o frontend exibe sem precisar de um de-para. */
function apresentar_importacao(array $importacao): array
{
    $importacao['id']             = (int) $importacao['id'];
    $importacao['usuario_id']     = (int) $importacao['usuario_id'];
    $importacao['colecao_id']     = $importacao['colecao_id'] === null ? null : (int) $importacao['colecao_id'];
    $importacao['card_game_nome'] = rotulos_dos_jogos()[$importacao['card_game']] ?? $importacao['card_game'];
    $importacao['status_nome']    = rotulos_de_status()[$importacao['status']] ?? $importacao['status'];

    $importacao['cartas_encontradas'] = (int) $importacao['cartas_encontradas'];
    $importacao['cartas_vinculadas']  = (int) $importacao['cartas_vinculadas'];

    // O vinculo sobrevive a exclusao da colecao (ON DELETE SET NULL). Marcar o
    // orfao e melhor do que sumir com a linha sem o usuario entender por que.
    $importacao['colecao_removida'] = $importacao['colecao_id'] === null;

    return $importacao;
}

/** Vinculo pertencente ao usuario da sessao, ou null. */
function importacao_do_usuario(int $id, int $usuarioId): ?array
{
    $consulta = conexao()->prepare(
        'SELECT ' . IMPORTACAO_COLUNAS . ', c.nome AS colecao_nome, c.tipo AS colecao_tipo
           FROM importacoes i
           LEFT JOIN colecoes c ON c.id = i.colecao_id
          WHERE i.id = :id AND i.usuario_id = :usuario
          LIMIT 1'
    );
    $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
    $importacao = $consulta->fetch();

    return $importacao ? apresentar_importacao($importacao) : null;
}

/** Interrompe com 404 quando o vinculo nao existe ou e de outro usuario. */
function exigir_importacao(int $id, int $usuarioId): array
{
    $importacao = importacao_do_usuario($id, $usuarioId);

    if ($importacao === null) {
        erro('Vínculo não encontrado.', 404);
    }

    return $importacao;
}

function listar(int $usuarioId): never
{
    $condicoes  = ['i.usuario_id = :usuario'];
    $parametros = [':usuario' => $usuarioId];

    $jogo = trim((string) ($_GET['game'] ?? ''));
    if ($jogo !== '' && $jogo !== 'all') {
        if (!in_array($jogo, jogos_suportados(), true)) {
            erro('Card game inválido no filtro.', 422);
        }
        $condicoes[]         = 'i.card_game = :game';
        $parametros[':game'] = $jogo;
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    if ($status !== '' && $status !== 'all') {
        if (!in_array($status, status_suportados(), true)) {
            erro('Situação inválida no filtro.', 422);
        }
        $condicoes[]           = 'i.status = :status';
        $parametros[':status'] = $status;
    }

    $consulta = conexao()->prepare(
        'SELECT ' . IMPORTACAO_COLUNAS . ', c.nome AS colecao_nome, c.tipo AS colecao_tipo
           FROM importacoes i
           LEFT JOIN colecoes c ON c.id = i.colecao_id
          WHERE ' . implode(' AND ', $condicoes) . '
          ORDER BY i.updated_at DESC, i.id DESC'
    );
    $consulta->execute($parametros);

    // Resumo do total, independente dos filtros: alimenta os indicadores do topo.
    $resumo = conexao()->prepare(
        "SELECT COUNT(*)                                                AS total,
                SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END)   AS sincronizados,
                SUM(CASE WHEN status = 'pendente'  THEN 1 ELSE 0 END)   AS pendentes,
                SUM(CASE WHEN status = 'erro'      THEN 1 ELSE 0 END)   AS falhas,
                COALESCE(SUM(cartas_vinculadas), 0)                     AS cartas
           FROM importacoes
          WHERE usuario_id = :usuario"
    );
    $resumo->execute(['usuario' => $usuarioId]);
    $totais = $resumo->fetch();

    responder([
        'success'     => true,
        'resumo'      => [
            'total'         => (int) $totais['total'],
            'sincronizados' => (int) $totais['sincronizados'],
            'pendentes'     => (int) $totais['pendentes'],
            'falhas'        => (int) $totais['falhas'],
            'cartas'        => (int) $totais['cartas'],
        ],
        'importacoes' => array_map('apresentar_importacao', $consulta->fetchAll()),
    ]);
}

function exibir(int $id, int $usuarioId): never
{
    responder(['success' => true, 'importacao' => exigir_importacao($id, $usuarioId)]);
}

// ------------------------------------------------------------------
// Criacao do vinculo
// ------------------------------------------------------------------

function criar(int $usuarioId): never
{
    $dados     = entrada();
    $problemas = [];

    $jogo = texto($dados, 'card_game');
    if ($jogo === null) {
        $problemas['card_game'] = 'Selecione o card game.';
    } elseif (!in_array($jogo, jogos_suportados(), true)) {
        $problemas['card_game'] = 'Card game inválido.';
    }

    $plataformaId = texto($dados, 'plataforma');
    $plataforma   = null;

    if ($plataformaId === null) {
        $problemas['plataforma'] = 'Escolha a plataforma onde o deck está.';
    } elseif (!isset($problemas['card_game']) && $jogo !== null) {
        $plataforma = plataforma_por_id($jogo, $plataformaId);
        if ($plataforma === null) {
            $problemas['plataforma'] = 'Essa plataforma não atende o card game selecionado.';
        }
    }

    $identificador = texto($dados, 'identificador');
    if ($identificador === null) {
        $problemas['identificador'] = 'Informe o identificador do deck online.';
    } elseif (mb_strlen($identificador) > 255) {
        $problemas['identificador'] = 'O identificador deve ter no máximo 255 caracteres.';
    }

    $colecaoId = inteiro($dados, 'colecao_id');
    $colecao   = null;

    if ($colecaoId === null) {
        $problemas['colecao_id'] = 'Escolha o deck offline que receberá as cartas.';
    } else {
        $consulta = conexao()->prepare(
            'SELECT id, nome, card_game, tipo FROM colecoes WHERE id = :id AND usuario_id = :usuario LIMIT 1'
        );
        $consulta->execute(['id' => $colecaoId, 'usuario' => $usuarioId]);
        $colecao = $consulta->fetch() ?: null;

        if ($colecao === null) {
            $problemas['colecao_id'] = 'Coleção não encontrada.';
        } elseif ($colecao['tipo'] !== 'deck') {
            $problemas['colecao_id'] = 'Só um deck aceita vínculo com uma lista online.';
        } elseif ($jogo !== null && $colecao['card_game'] !== $jogo) {
            $problemas['colecao_id'] = 'O deck escolhido é de outro card game.';
        }
    }

    if ($problemas) {
        erro('Revise os campos destacados no formulário.', 422, ['campos' => $problemas]);
    }

    // O mesmo deck online duas vezes so geraria sincronizacoes concorrentes
    // sobre a mesma colecao, sem nada de novo para importar.
    $duplicado = conexao()->prepare(
        'SELECT id FROM importacoes
          WHERE usuario_id = :usuario AND plataforma = :plataforma AND identificador = :identificador
          LIMIT 1'
    );
    $duplicado->execute([
        'usuario'       => $usuarioId,
        'plataforma'    => $plataformaId,
        'identificador' => $identificador,
    ]);

    if ($duplicado->fetch()) {
        erro('Esse deck online já está vinculado.', 409, [
            'campos' => ['identificador' => 'Já existe um vínculo com este identificador.'],
        ]);
    }

    $insercao = conexao()->prepare(
        'INSERT INTO importacoes
            (usuario_id, colecao_id, card_game, plataforma, plataforma_nome, identificador, status)
         VALUES
            (:usuario, :colecao, :card_game, :plataforma, :plataforma_nome, :identificador, :status)'
    );
    $insercao->execute([
        'usuario'         => $usuarioId,
        'colecao'         => $colecaoId,
        'card_game'       => $jogo,
        'plataforma'      => $plataformaId,
        'plataforma_nome' => $plataforma['name'],
        'identificador'   => $identificador,
        'status'          => 'pendente',
    ]);

    responder([
        'success'    => true,
        'message'    => sprintf('Vínculo criado. Sincronize para trazer as cartas do %s.', $plataforma['name']),
        'importacao' => importacao_do_usuario((int) conexao()->lastInsertId(), $usuarioId),
    ], 201);
}

// ------------------------------------------------------------------
// Sincronizacao
// ------------------------------------------------------------------

/**
 * Aqui mora o mock.
 *
 * Representa a lista que a plataforma online devolveria para o identificador
 * informado: nomes em ingles com quantidade, que e o formato que Moxfield,
 * Limitless e companhia exportam. Nada sai pela rede.
 *
 * De proposito ela nao cabe inteira no acervo — algumas cartas do deck online
 * nao existem no portal. E o caso que separa "encontradas" de "vinculadas" e
 * o que o usuario mais precisa enxergar ao ligar os dois mundos.
 *
 * Trocar por uma integracao real significa trocar so esta funcao: o contrato
 * de saida (nome_en + quantidade) e o que o resto do arquivo consome.
 */
function deck_online(string $jogo): array
{
    $consulta = conexao()->prepare('SELECT id, nome_en FROM cartas WHERE card_game = :jogo ORDER BY id ASC');
    $consulta->execute(['jogo' => $jogo]);

    $lista = array_map(
        // Quantidade plausivel para um deck: parte das cartas entra repetida.
        static fn (array $linha): array => [
            'nome_en'    => $linha['nome_en'],
            'quantidade' => 1 + ((int) $linha['id'] % 3),
        ],
        $consulta->fetchAll()
    );

    foreach (ausentes_do_acervo()[$jogo] ?? [] as $nome) {
        $lista[] = ['nome_en' => $nome, 'quantidade' => 2];
    }

    return $lista;
}

/**
 * Cartas reais de cada jogo que o deck online costuma ter e que nao estao
 * cadastradas no portal. Sao elas que caem em "não localizadas no acervo".
 */
function ausentes_do_acervo(): array
{
    return [
        'magic'    => ['Cyclonic Rift', 'Wrenn and Six'],
        'pokemon'  => ["Professor's Research", 'Iono'],
        'yugioh'   => ['Mirror Force', 'Monster Reborn'],
        'onepiece' => ['Nami', 'Trafalgar Law'],
        'fab'      => ['Command and Conquer', 'Sink Below'],
    ];
}

/**
 * Casa a lista online com o acervo, pelo par (card game, nome em ingles).
 * Devolve o que casou e o que ficou de fora, sem perder a quantidade.
 */
function casar_com_acervo(array $lista, string $jogo): array
{
    $consulta = conexao()->prepare('SELECT id, nome_en FROM cartas WHERE card_game = :jogo');
    $consulta->execute(['jogo' => $jogo]);

    $porNome = [];
    foreach ($consulta->fetchAll() as $carta) {
        $porNome[mb_strtolower($carta['nome_en'])] = (int) $carta['id'];
    }

    $casadas = [];
    $ausentes = [];

    foreach ($lista as $item) {
        $chave = mb_strtolower($item['nome_en']);

        if (isset($porNome[$chave])) {
            $casadas[] = ['carta_id' => $porNome[$chave], 'quantidade' => $item['quantidade']];
            continue;
        }

        $ausentes[] = $item['nome_en'];
    }

    return ['casadas' => $casadas, 'ausentes' => $ausentes];
}

/** Marca o vinculo como falho e responde 200 — o erro e de negocio, nao de HTTP. */
function encerrar_com_falha(int $id, int $usuarioId, string $mensagem): never
{
    $consulta = conexao()->prepare(
        "UPDATE importacoes
            SET status = 'erro', mensagem = :mensagem
          WHERE id = :id AND usuario_id = :usuario"
    );
    $consulta->execute(['mensagem' => $mensagem, 'id' => $id, 'usuario' => $usuarioId]);

    responder([
        'success'    => true,
        'message'    => $mensagem,
        'importacao' => importacao_do_usuario($id, $usuarioId),
    ]);
}

function sincronizar(int $id, int $usuarioId): never
{
    $importacao = exigir_importacao($id, $usuarioId);

    if ($importacao['colecao_id'] === null) {
        encerrar_com_falha(
            $id,
            $usuarioId,
            'A coleção de destino foi excluída. Refaça o vínculo apontando para outro deck.'
        );
    }

    $lista      = deck_online($importacao['card_game']);
    $resultado  = casar_com_acervo($lista, $importacao['card_game']);
    $casadas    = $resultado['casadas'];
    $ausentes   = $resultado['ausentes'];

    if ($casadas === []) {
        encerrar_com_falha(
            $id,
            $usuarioId,
            sprintf(
                'Nenhuma carta da lista do %s existe no acervo. Cadastre as cartas de %s antes de sincronizar.',
                $importacao['plataforma_nome'],
                $importacao['card_game_nome']
            )
        );
    }

    $pdo = conexao();
    $pdo->beginTransaction();

    try {
        // Cartas que a importacao anterior trouxe e que sairam do deck online
        // saem tambem daqui. O que o usuario incluiu na mao (origem 'manual')
        // fica intocado: a sincronizacao nao manda no acervo local dele.
        $limpeza = $pdo->prepare(
            "DELETE FROM colecao_cartas WHERE colecao_id = :colecao AND origem = 'importacao'"
        );
        $limpeza->execute(['colecao' => $importacao['colecao_id']]);

        // O UPDATE e um no-op de proposito: se a carta ja esta na colecao, ela
        // foi incluida na mao e a quantidade e do usuario, nao da plataforma.
        // (INSERT IGNORE teria o mesmo efeito, mas engoliria tambem erros de
        // verdade, como violacao de chave estrangeira.)
        $insercao = $pdo->prepare(
            "INSERT INTO colecao_cartas (colecao_id, carta_id, quantidade, origem)
             VALUES (:colecao, :carta, :quantidade, 'importacao')
             ON DUPLICATE KEY UPDATE quantidade = quantidade"
        );

        foreach ($casadas as $item) {
            $insercao->execute([
                'colecao'    => $importacao['colecao_id'],
                'carta'      => $item['carta_id'],
                'quantidade' => $item['quantidade'],
            ]);
        }

        $mensagem = $ausentes === []
            ? sprintf('Deck do %s sincronizado por inteiro.', $importacao['plataforma_nome'])
            : sprintf(
                '%d de %d cartas casaram com o acervo. Fora: %s.',
                count($casadas),
                count($lista),
                implode(', ', $ausentes)
            );

        $conclusao = $pdo->prepare(
            "UPDATE importacoes
                SET status             = 'concluida',
                    cartas_encontradas = :encontradas,
                    cartas_vinculadas  = :vinculadas,
                    mensagem           = :mensagem,
                    sincronizado_em    = CURRENT_TIMESTAMP
              WHERE id = :id AND usuario_id = :usuario"
        );
        $conclusao->execute([
            'encontradas' => count($lista),
            'vinculadas'  => count($casadas),
            // A coluna guarda 255 chars: uma lista longa de ausentes e cortada.
            'mensagem'    => mb_substr($mensagem, 0, 255),
            'id'          => $id,
            'usuario'     => $usuarioId,
        ]);

        $toque = $pdo->prepare('UPDATE colecoes SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $toque->execute(['id' => $importacao['colecao_id']]);

        $pdo->commit();
    } catch (PDOException $falha) {
        $pdo->rollBack();
        throw $falha;
    }

    responder([
        'success'         => true,
        'message'         => sprintf(
            '%d cartas vinculadas em “%s”.',
            count($casadas),
            $importacao['colecao_nome'] ?? 'deck'
        ),
        'nao_localizadas' => $ausentes,
        'importacao'      => importacao_do_usuario($id, $usuarioId),
    ]);
}

// ------------------------------------------------------------------
// Remocao
// ------------------------------------------------------------------

function remover(int $usuarioId): never
{
    $dados      = entrada();
    $id         = id_da_query('id', $dados, '/api/importacoes.php?id=1');
    $importacao = exigir_importacao($id, $usuarioId);

    $pdo = conexao();
    $pdo->beginTransaction();

    try {
        // Desfazer o vinculo devolve o deck ao estado offline: o que veio da
        // plataforma sai, o que foi incluido na mao permanece.
        if ($importacao['colecao_id'] !== null) {
            $limpeza = $pdo->prepare(
                "DELETE FROM colecao_cartas WHERE colecao_id = :colecao AND origem = 'importacao'"
            );
            $limpeza->execute(['colecao' => $importacao['colecao_id']]);
        }

        $exclusao = $pdo->prepare('DELETE FROM importacoes WHERE id = :id AND usuario_id = :usuario');
        $exclusao->execute(['id' => $id, 'usuario' => $usuarioId]);

        $pdo->commit();
    } catch (PDOException $falha) {
        $pdo->rollBack();
        throw $falha;
    }

    responder(['success' => true, 'message' => 'Vínculo desfeito.']);
}

// ------------------------------------------------------------------
// Dispatch
// ------------------------------------------------------------------

$metodo  = exigir_metodo(['GET', 'POST', 'DELETE']);
$usuario = exigir_autenticacao();
$acao    = trim((string) ($_GET['acao'] ?? ''));

if ($acao !== '' && $acao !== 'sincronizar') {
    erro(sprintf('Ação "%s" não existe neste endpoint.', $acao), 404);
}

try {
    match (true) {
        $metodo === 'POST' && $acao === 'sincronizar' => sincronizar(
            id_da_query('id', entrada(), '/api/importacoes.php?id=1&acao=sincronizar'),
            $usuario['id']
        ),
        $metodo === 'GET' => isset($_GET['id'])
            ? exibir(id_da_query('id', [], '/api/importacoes.php?id=1'), $usuario['id'])
            : listar($usuario['id']),
        $metodo === 'POST'   => criar($usuario['id']),
        $metodo === 'DELETE' => remover($usuario['id']),
    };
} catch (PDOException $falha) {
    error_log('[importacoes] ' . $falha->getMessage());
    erro('Não foi possível concluir a operação no banco de dados.', 500);
}
