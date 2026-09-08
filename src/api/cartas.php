<?php
declare(strict_types=1);

/**
 * CRUD de cartas.
 *
 *   GET    /api/cartas.php                 lista (filtros: game, search, page, limit)
 *   GET    /api/cartas.php?id=1            detalhe
 *   POST   /api/cartas.php                 cria      (JSON ou multipart com upload)
 *   PUT    /api/cartas.php?id=1            atualiza  (JSON)
 *   POST   /api/cartas.php?id=1  _method=PUT         (multipart, para upload na edicao)
 *   DELETE /api/cartas.php?id=1            remove
 *
 * Todos os metodos exigem sessao: sao dados de negocio do portal.
 */

require_once __DIR__ . '/auth.php';

const IMAGEM_TAMANHO_MAXIMO = 5 * 1024 * 1024; // 5 MB
const IMAGEM_TIPOS_ACEITOS  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

const COLUNAS = 'id, nome_en, nome_pt, card_game, edicao_id, edicao_nome, raridade, imagem_url, created_at, updated_at';

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

/** Contagem de caracteres UTF-8 sem depender da extensao mbstring. */
function tamanho_texto(string $valor): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($valor, 'UTF-8');
    }

    return (int) preg_match_all('/./us', $valor);
}

function diretorio_uploads(): string
{
    return dirname(__DIR__) . '/uploads';
}

/** Acrescenta o rotulo do jogo para o frontend nao precisar de um de-para. */
function apresentar_carta(array $carta): array
{
    $carta['id']              = (int) $carta['id'];
    $carta['card_game_nome']  = rotulos_dos_jogos()[$carta['card_game']] ?? $carta['card_game'];

    return $carta;
}

function carta_por_id(int $id): ?array
{
    $consulta = conexao()->prepare('SELECT ' . COLUNAS . ' FROM cartas WHERE id = :id LIMIT 1');
    $consulta->execute(['id' => $id]);
    $carta = $consulta->fetch();

    return $carta ? apresentar_carta($carta) : null;
}

/** Le o id de /api/cartas.php?id=N, com fallback para o corpo da requisicao. */
function id_solicitado(array $dados = []): int
{
    $bruto = $_GET['id'] ?? $dados['id'] ?? null;

    if ($bruto === null || !is_scalar($bruto) || !preg_match('/^\d+$/', (string) $bruto)) {
        erro('Informe o id da carta, por exemplo: /api/cartas.php?id=1', 422);
    }

    return (int) $bruto;
}

// ------------------------------------------------------------------
// Validacao
// ------------------------------------------------------------------

/**
 * Normaliza e valida os campos da carta.
 * edicao_nome nunca vem do cliente: e resolvido a partir do catalogo.
 */
function campos_validados(array $dados): array
{
    $problemas = [];

    $nomeEn = texto($dados, 'nome_en');
    if ($nomeEn === null) {
        $problemas['nome_en'] = 'O nome em inglês é obrigatório.';
    } elseif (tamanho_texto($nomeEn) > 150) {
        $problemas['nome_en'] = 'O nome em inglês deve ter no máximo 150 caracteres.';
    }

    $nomePt = texto($dados, 'nome_pt');
    if ($nomePt !== null && tamanho_texto($nomePt) > 150) {
        $problemas['nome_pt'] = 'O nome em português deve ter no máximo 150 caracteres.';
    }

    $jogo = texto($dados, 'card_game');
    if ($jogo === null) {
        $problemas['card_game'] = 'Selecione o card game.';
    } elseif (!in_array($jogo, jogos_suportados(), true)) {
        $problemas['card_game'] = 'Card game inválido.';
    }

    $edicaoId   = texto($dados, 'edicao_id');
    $edicaoNome = null;

    if ($edicaoId === null) {
        $problemas['edicao_id'] = 'Selecione a edição.';
    } elseif (!isset($problemas['card_game']) && $jogo !== null) {
        $edicaoNome = nome_da_edicao($jogo, $edicaoId);
        if ($edicaoNome === null) {
            $problemas['edicao_id'] = 'Essa edição não pertence ao card game selecionado.';
        }
    }

    $raridade = texto($dados, 'raridade');
    if ($raridade === null) {
        $problemas['raridade'] = 'Informe a raridade da carta.';
    } elseif (tamanho_texto($raridade) > 50) {
        $problemas['raridade'] = 'A raridade deve ter no máximo 50 caracteres.';
    }

    if ($problemas) {
        erro('Revise os campos destacados no formulário.', 422, ['campos' => $problemas]);
    }

    return [
        'nome_en'     => $nomeEn,
        'nome_pt'     => $nomePt,
        'card_game'   => $jogo,
        'edicao_id'   => $edicaoId,
        'edicao_nome' => $edicaoNome,
        'raridade'    => $raridade,
    ];
}

// ------------------------------------------------------------------
// Imagem
// ------------------------------------------------------------------

/**
 * Aceita apenas http(s) ou um caminho de upload ja gerado por este backend.
 * Bloqueia javascript: e data:, que virariam XSS ao cair em <img src>.
 */
function url_de_imagem_valida(string $valor): ?string
{
    if (str_starts_with($valor, 'uploads/')) {
        return preg_match('#^uploads/[A-Za-z0-9._-]{1,120}$#', $valor) ? $valor : null;
    }

    // Imagens do seed, servidas localmente pelo Apache.
    if (str_starts_with($valor, 'assets/img/cards/')) {
        return preg_match('#^assets/img/cards/[A-Za-z0-9._-]{1,120}$#', $valor) ? $valor : null;
    }

    if (strlen($valor) > 2000 || !filter_var($valor, FILTER_VALIDATE_URL)) {
        return null;
    }

    $esquema = strtolower((string) parse_url($valor, PHP_URL_SCHEME));

    return in_array($esquema, ['http', 'https'], true) ? $valor : null;
}

/** Move o arquivo enviado para src/uploads e devolve o caminho relativo. */
function salvar_upload(array $arquivo): string
{
    $codigos = [
        UPLOAD_ERR_INI_SIZE   => 'A imagem excede o tamanho máximo permitido pelo servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'A imagem excede o tamanho máximo permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL    => 'O envio da imagem foi interrompido. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falha temporária no servidor ao receber a imagem.',
        UPLOAD_ERR_CANT_WRITE => 'Não foi possível gravar a imagem no servidor.',
        UPLOAD_ERR_EXTENSION  => 'O envio da imagem foi bloqueado pelo servidor.',
    ];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        erro($codigos[$arquivo['error']] ?? 'Não foi possível enviar a imagem.', 422);
    }

    if (!is_uploaded_file($arquivo['tmp_name'])) {
        erro('Arquivo de imagem inválido.', 422);
    }

    if ($arquivo['size'] > IMAGEM_TAMANHO_MAXIMO) {
        erro('A imagem deve ter no máximo 5 MB.', 422);
    }

    // Confia no conteudo do arquivo, nao na extensao nem no Content-Type enviado.
    $informacoes = @getimagesize($arquivo['tmp_name']);
    $tipo        = is_array($informacoes) ? ($informacoes['mime'] ?? '') : '';

    if (!isset(IMAGEM_TIPOS_ACEITOS[$tipo])) {
        erro('Formato não suportado. Envie uma imagem JPG, PNG, WEBP ou GIF.', 422);
    }

    $destinoDir = diretorio_uploads();

    if (!is_dir($destinoDir) && !@mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
        error_log('[cartas] nao foi possivel criar ' . $destinoDir);
        erro('Não foi possível salvar a imagem no servidor.', 500);
    }

    $nome    = sprintf('carta-%s.%s', bin2hex(random_bytes(8)), IMAGEM_TIPOS_ACEITOS[$tipo]);
    $destino = $destinoDir . '/' . $nome;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        error_log('[cartas] falha ao mover upload para ' . $destino);
        erro('Não foi possível salvar a imagem. Verifique as permissões de src/uploads.', 500);
    }

    @chmod($destino, 0644);

    return 'uploads/' . $nome;
}

/** Apaga um upload local que deixou de ser referenciado. */
function descartar_upload(?string $caminho): void
{
    if ($caminho === null || !str_starts_with($caminho, 'uploads/')) {
        return;
    }

    if (!preg_match('#^uploads/[A-Za-z0-9._-]{1,120}$#', $caminho)) {
        return;
    }

    $arquivo = dirname(__DIR__) . '/' . $caminho;

    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

/**
 * Decide a imagem final da carta, nesta ordem:
 *   1. arquivo enviado no campo "imagem"
 *   2. campo "imagem_url" preenchido
 *   3. flag "remover_imagem"
 *   4. mantem a imagem atual (apenas na edicao)
 */
function resolver_imagem(array $dados, ?string $atual): ?string
{
    $enviado = $_FILES['imagem'] ?? null;

    if (is_array($enviado) && ($enviado['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $novo = salvar_upload($enviado);
        descartar_upload($atual);

        return $novo;
    }

    $url = texto($dados, 'imagem_url');

    if ($url !== null) {
        $validada = url_de_imagem_valida($url);

        if ($validada === null) {
            erro('Informe uma URL de imagem válida (http ou https).', 422, [
                'campos' => ['imagem_url' => 'URL de imagem inválida.'],
            ]);
        }

        if ($validada !== $atual) {
            descartar_upload($atual);
        }

        return $validada;
    }

    if (!empty($dados['remover_imagem'])) {
        descartar_upload($atual);

        return null;
    }

    return $atual;
}

// ------------------------------------------------------------------
// Acoes
// ------------------------------------------------------------------

function listar(): never
{
    $pdo = conexao();

    $pagina = max(1, (int) ($_GET['page'] ?? 1));
    $limite = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($pagina - 1) * $limite;

    $condicoes = [];
    $parametros = [];

    $jogo = trim((string) ($_GET['game'] ?? ''));
    if ($jogo !== '' && $jogo !== 'all') {
        if (!in_array($jogo, jogos_suportados(), true)) {
            erro('Card game inválido no filtro.', 422);
        }
        $condicoes[]        = 'card_game = :game';
        $parametros[':game'] = $jogo;
    }

    $busca = trim((string) ($_GET['search'] ?? ''));
    if ($busca !== '') {
        // Um placeholder por coluna: com prepared statements nativos o MySQL nao
        // aceita o mesmo parametro nomeado repetido na mesma consulta.
        $condicoes[] = '(nome_en LIKE :busca_en OR nome_pt LIKE :busca_pt OR edicao_nome LIKE :busca_ed)';

        // Escapa os curingas do LIKE para que % e _ sejam tratados como literais.
        $termo = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $busca) . '%';

        $parametros[':busca_en'] = $termo;
        $parametros[':busca_pt'] = $termo;
        $parametros[':busca_ed'] = $termo;
    }

    $onde = $condicoes ? ' WHERE ' . implode(' AND ', $condicoes) : '';

    $contagem = $pdo->prepare('SELECT COUNT(*) FROM cartas' . $onde);
    $contagem->execute($parametros);
    $total = (int) $contagem->fetchColumn();

    $consulta = $pdo->prepare(
        'SELECT ' . COLUNAS . ' FROM cartas' . $onde . ' ORDER BY updated_at DESC, id DESC LIMIT :limite OFFSET :offset'
    );

    foreach ($parametros as $chave => $valor) {
        $consulta->bindValue($chave, $valor, PDO::PARAM_STR);
    }
    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
    $consulta->execute();

    // Resumo do acervo inteiro, independente dos filtros ativos: alimenta os
    // indicadores do topo do painel, que nao devem mudar ao filtrar a lista.
    $resumo = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(DISTINCT card_game) AS jogos,
                COUNT(DISTINCT CONCAT(card_game, ':', edicao_id)) AS edicoes
           FROM cartas"
    )->fetch();

    responder([
        'success' => true,
        'total'   => $total,
        'pagina'  => $pagina,
        'limite'  => $limite,
        'resumo'  => [
            'total'   => (int) $resumo['total'],
            'jogos'   => (int) $resumo['jogos'],
            'edicoes' => (int) $resumo['edicoes'],
        ],
        'cartas'  => array_map('apresentar_carta', $consulta->fetchAll()),
    ]);
}

function exibir(int $id): never
{
    $carta = carta_por_id($id);

    if ($carta === null) {
        erro('Carta não encontrada.', 404);
    }

    responder(['success' => true, 'carta' => $carta]);
}

function criar(): never
{
    $dados  = entrada();
    $campos = campos_validados($dados);

    $campos['imagem_url'] = resolver_imagem($dados, null);

    $consulta = conexao()->prepare(
        'INSERT INTO cartas (nome_en, nome_pt, card_game, edicao_id, edicao_nome, raridade, imagem_url)
         VALUES (:nome_en, :nome_pt, :card_game, :edicao_id, :edicao_nome, :raridade, :imagem_url)'
    );

    try {
        $consulta->execute($campos);
    } catch (PDOException $falha) {
        descartar_upload($campos['imagem_url']);
        error_log('[cartas] insert: ' . $falha->getMessage());
        erro('Não foi possível cadastrar a carta.', 500);
    }

    responder([
        'success' => true,
        'message' => 'Carta cadastrada com sucesso.',
        'carta'   => carta_por_id((int) conexao()->lastInsertId()),
    ], 201);
}

function atualizar(): never
{
    $dados = entrada();
    $id    = id_solicitado($dados);
    $atual = carta_por_id($id);

    if ($atual === null) {
        erro('Carta não encontrada.', 404);
    }

    $campos               = campos_validados($dados);
    $campos['imagem_url'] = resolver_imagem($dados, $atual['imagem_url']);
    $campos['id']         = $id;

    $consulta = conexao()->prepare(
        'UPDATE cartas SET
            nome_en     = :nome_en,
            nome_pt     = :nome_pt,
            card_game   = :card_game,
            edicao_id   = :edicao_id,
            edicao_nome = :edicao_nome,
            raridade    = :raridade,
            imagem_url  = :imagem_url
         WHERE id = :id'
    );

    try {
        $consulta->execute($campos);
    } catch (PDOException $falha) {
        error_log('[cartas] update: ' . $falha->getMessage());
        erro('Não foi possível atualizar a carta.', 500);
    }

    responder([
        'success' => true,
        'message' => 'Carta atualizada com sucesso.',
        'carta'   => carta_por_id($id),
    ]);
}

function remover(): never
{
    $id    = id_solicitado(entrada());
    $carta = carta_por_id($id);

    if ($carta === null) {
        erro('Carta não encontrada.', 404);
    }

    $consulta = conexao()->prepare('DELETE FROM cartas WHERE id = :id');
    $consulta->execute(['id' => $id]);

    descartar_upload($carta['imagem_url']);

    responder(['success' => true, 'message' => 'Carta excluída com sucesso.']);
}

// ------------------------------------------------------------------
// Dispatch
// ------------------------------------------------------------------

$metodo = exigir_metodo(['GET', 'POST', 'PUT', 'DELETE']);

exigir_autenticacao();

try {
    match ($metodo) {
        'GET'    => isset($_GET['id']) ? exibir(id_solicitado()) : listar(),
        'POST'   => criar(),
        'PUT'    => atualizar(),
        'DELETE' => remover(),
    };
} catch (PDOException $falha) {
    error_log('[cartas] ' . $falha->getMessage());
    erro('Não foi possível concluir a operação no banco de dados.', 500);
}
