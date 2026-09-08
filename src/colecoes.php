<?php
declare(strict_types=1);

/** Coleções: decks, binders e listas de desejo, organizados por TCG. */

require_once __DIR__ . '/config/sessao.php';
require_once __DIR__ . '/config/edicoes.php';
require_once __DIR__ . '/config/colecoes.php';
require_once __DIR__ . '/config/vista.php';

$usuario = usuario_atual();

if ($usuario === null) {
    header('Location: index.php?expirada=1');
    exit;
}

$jogos           = rotulos_dos_jogos();
$tipos           = tipos_de_colecao();
$descricoesTipos = descricoes_dos_tipos();

$paginaAtual = 'colecoes';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <?php $tituloDaPagina = 'Coleções'; require __DIR__ . '/partials/documento.php'; ?>
</head>
<body>
  <a class="pular-para-conteudo" href="#conteudo">Pular para o conteúdo</a>

  <div class="site-shell">
    <?php require __DIR__ . '/partials/cabecalho.php'; ?>

    <main id="conteudo">
      <section class="dashboard-hero compacto">
        <div>
          <p class="eyebrow">COLEÇÕES</p>
          <h1>Cada carta<br><em>no lugar certo.</em></h1>
          <p>
            Separe o acervo em decks, binders e listas de desejo. Toda coleção
            pertence a um único TCG — é o que mantém os filtros honestos.
          </p>
        </div>
        <div class="hero-art">
          <div class="hero-orbit" aria-hidden="true"></div>
          <img src="assets/img/hero-colecoes.jpg" alt="">
          <span class="hero-badge"><?= count($tipos) ?> tipos<br><small>de coleção</small></span>
        </div>
      </section>

      <section class="quick-stats" aria-label="Resumo das coleções">
        <div>
          <span class="stat-icon marca" aria-hidden="true">✦</span>
          <p><strong id="stat-colecoes">—</strong><small>coleções criadas</small></p>
        </div>
        <div>
          <span class="stat-icon ouro" aria-hidden="true">◈</span>
          <p><strong id="stat-jogos">—</strong><small>TCGs organizados</small></p>
        </div>
        <div>
          <span class="stat-icon realce" aria-hidden="true">◇</span>
          <p><strong id="stat-decks">—</strong><small>decks montados</small></p>
        </div>
        <div>
          <span class="stat-icon tinta" aria-hidden="true">◆</span>
          <p><strong id="stat-unidades">—</strong><small>cartas guardadas</small></p>
        </div>
      </section>

      <section class="catalog-section">
        <div class="section-heading">
          <div>
            <p class="eyebrow">ORGANIZAÇÃO</p>
            <h2>Suas coleções</h2>
          </div>
          <button class="primary-button" id="botao-nova-colecao" type="button">
            <span class="rotulo">＋ Nova coleção</span>
          </button>
        </div>

        <div class="toolbar">
          <label class="search-field" for="busca">
            <span aria-hidden="true">⌕</span>
            <input id="busca" type="search" placeholder="Buscar por nome ou descrição"
                   aria-label="Buscar coleções por nome ou descrição">
          </label>

          <div class="filter-tabs" role="group" aria-label="Filtrar por card game">
            <button class="filter-tab active" type="button" data-game="all">Todos os TCGs</button>
            <?php foreach ($jogos as $chave => $rotulo): ?>
              <button class="filter-tab" type="button" data-game="<?= h($chave) ?>"><?= h($rotulo) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="filter-tabs linha-secundaria" role="group" aria-label="Filtrar por tipo de coleção">
          <button class="filter-tab active" type="button" data-tipo="all">Todos os tipos</button>
          <?php foreach ($tipos as $chave => $rotulo): ?>
            <button class="filter-tab" type="button" data-tipo="<?= h($chave) ?>"><?= h($rotulo) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="skeleton-grid colecoes" id="colecoes-carregando" hidden aria-hidden="true">
          <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
        </div>

        <div class="colecao-grid" id="grade-colecoes" aria-live="polite"></div>

        <div class="catalogo-status" id="colecoes-status" hidden></div>
      </section>
    </main>

    <?php require __DIR__ . '/partials/rodape.php'; ?>
  </div>

  <!-- ============ Modal de criação / edição da coleção ============ -->
  <div class="modal-backdrop" id="modal-colecao" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-colecao-titulo">
      <button class="modal-close" type="button" data-fechar-colecao aria-label="Fechar formulário">×</button>
      <p class="eyebrow" id="modal-colecao-kicker">NOVA COLEÇÃO</p>
      <h2 id="modal-colecao-titulo">Comece uma coleção.</h2>

      <div class="alerta erro" id="colecao-alerta" role="alert" hidden></div>

      <form id="form-colecao" class="card-form" novalidate>
        <input type="hidden" name="id" id="colecao-id" value="">

        <div class="form-grid">
          <label class="field larga" for="nome">
            <span>Nome<span class="obrigatorio" aria-hidden="true">*</span></span>
            <input id="nome" name="nome" type="text" maxlength="120"
                   placeholder="Ex.: Charizard Control" required aria-describedby="erro-nome">
            <span class="field-error" id="erro-nome" aria-live="polite"></span>
          </label>

          <label class="field" for="card_game">
            <span>Card game<span class="obrigatorio" aria-hidden="true">*</span></span>
            <select id="card_game" name="card_game" required aria-describedby="ajuda-card_game erro-card_game">
              <option value="">Selecione o card game</option>
              <?php foreach ($jogos as $chave => $rotulo): ?>
                <option value="<?= h($chave) ?>"><?= h($rotulo) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="ajuda" id="ajuda-card_game">Só entram cartas deste TCG na coleção.</span>
            <span class="field-error" id="erro-card_game" aria-live="polite"></span>
          </label>

          <label class="field" for="tipo">
            <span>Tipo<span class="obrigatorio" aria-hidden="true">*</span></span>
            <select id="tipo" name="tipo" required aria-describedby="ajuda-tipo erro-tipo">
              <?php foreach ($tipos as $chave => $rotulo): ?>
                <option value="<?= h($chave) ?>" <?= $chave === 'deck' ? 'selected' : '' ?>><?= h($rotulo) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="ajuda" id="ajuda-tipo"
                  data-descricoes="<?= h(json_encode($descricoesTipos, JSON_UNESCAPED_UNICODE)) ?>">
              <?= h($descricoesTipos['deck']) ?>
            </span>
            <span class="field-error" id="erro-tipo" aria-live="polite"></span>
          </label>

          <label class="field larga" for="descricao">
            <span>Descrição <span class="opcional">(opcional)</span></span>
            <input id="descricao" name="descricao" type="text" maxlength="255"
                   placeholder="Uma linha sobre o que essa coleção guarda"
                   aria-describedby="erro-descricao">
            <span class="field-error" id="erro-descricao" aria-live="polite"></span>
          </label>
        </div>

        <div class="modal-actions">
          <button class="secondary-button" type="button" data-fechar-colecao>Cancelar</button>
          <button class="primary-button" type="submit" id="botao-salvar-colecao">
            <span class="rotulo">Salvar coleção</span>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- ============ Modal do conteúdo da coleção ============ -->
  <div class="modal-backdrop" id="modal-conteudo" hidden>
    <section class="modal largo" role="dialog" aria-modal="true" aria-labelledby="conteudo-titulo">
      <button class="modal-close" type="button" data-fechar-conteudo aria-label="Fechar coleção">×</button>
      <p class="eyebrow" id="conteudo-kicker">COLEÇÃO</p>
      <h2 id="conteudo-titulo">—</h2>
      <p class="conteudo-descricao" id="conteudo-descricao"></p>

      <div class="alerta erro" id="conteudo-alerta" role="alert" hidden></div>

      <form class="incluir-carta" id="form-incluir-carta" novalidate>
        <div class="field">
          <label for="carta_id">Incluir carta do acervo</label>
          <span class="select-wrap" id="carta-wrap">
            <select id="carta_id" name="carta_id" aria-describedby="erro-carta_id">
              <option value="">Carregando cartas…</option>
            </select>
          </span>
          <span class="field-error" id="erro-carta_id" aria-live="polite"></span>
        </div>

        <label class="field estreita" for="quantidade">
          <span>Qtd.</span>
          <input id="quantidade" name="quantidade" type="number" min="1" max="99" value="1"
                 aria-describedby="erro-quantidade">
          <span class="field-error" id="erro-quantidade" aria-live="polite"></span>
        </label>

        <button class="primary-button" type="submit" id="botao-incluir-carta">
          <span class="rotulo">Incluir</span>
        </button>
      </form>

      <div class="conteudo-lista" id="conteudo-lista" aria-live="polite"></div>

      <div class="modal-actions">
        <button class="secondary-button" type="button" data-fechar-conteudo>Fechar</button>
      </div>
    </section>
  </div>

  <!-- ============ Modal de confirmação de exclusão ============ -->
  <div class="modal-backdrop" id="modal-exclusao" hidden>
    <section class="modal confirmacao" role="alertdialog" aria-modal="true"
             aria-labelledby="exclusao-titulo" aria-describedby="exclusao-descricao">
      <button class="modal-close" type="button" data-fechar-exclusao aria-label="Cancelar exclusão">×</button>
      <p class="eyebrow">CONFIRMAR EXCLUSÃO</p>
      <h2 id="exclusao-titulo">Excluir esta coleção?</h2>
      <p id="exclusao-descricao">
        Você está prestes a excluir <span class="alvo" id="exclusao-nome"></span>.
        As cartas continuam no acervo — só o agrupamento some.
      </p>
      <span class="irreversivel">Esta ação não pode ser desfeita.</span>

      <div class="modal-actions">
        <button class="secondary-button" type="button" data-fechar-exclusao>Manter coleção</button>
        <button class="primary-button botao-perigo" type="button" id="botao-confirmar-exclusao">
          <span class="rotulo">Sim, excluir</span>
        </button>
      </div>
    </section>
  </div>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script src="assets/js/tema.js"></script>
  <script src="assets/js/common.js"></script>
  <script src="assets/js/colecoes.js"></script>
</body>
</html>
