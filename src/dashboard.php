<?php
declare(strict_types=1);

/** Painel administrativo. Protegido: sem sessão, volta para o login. */

require_once __DIR__ . '/config/sessao.php';
require_once __DIR__ . '/config/edicoes.php';
require_once __DIR__ . '/config/vista.php';

$usuario = usuario_atual();

if ($usuario === null) {
    header('Location: index.php?expirada=1');
    exit;
}

/** Opções sugeridas de raridade. O backend aceita qualquer texto até 50 chars. */
$raridades = ['Comum', 'Incomum', 'Rara', 'Super Rara', 'Ultra Rara', 'Mítica', 'Secreta', 'Promo'];

$jogos = rotulos_dos_jogos();

$paginaAtual = 'painel';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <?php $tituloDaPagina = 'Painel de cartas'; require __DIR__ . '/partials/documento.php'; ?>
</head>
<body>
  <a class="pular-para-conteudo" href="#conteudo">Pular para o conteúdo</a>

  <div class="site-shell">
    <?php require __DIR__ . '/partials/cabecalho.php'; ?>

    <main id="conteudo">
      <section class="dashboard-hero">
        <div>
          <p class="eyebrow">PORTAL ADMINISTRATIVO</p>
          <h1>Seu acervo.<br><em>Sob controle.</em></h1>
          <p>Cadastre, edite e organize as cartas de todos os TCGs em um só lugar.</p>
        </div>
        <div class="hero-art">
          <div class="hero-orbit" aria-hidden="true"></div>
          <img src="assets/img/hero-painel.jpg" alt="">
          <span class="hero-badge"><?= count($jogos) ?> TCGs<br><small>em um só lugar</small></span>
        </div>
      </section>

      <section class="quick-stats" aria-label="Resumo do acervo">
        <div>
          <span class="stat-icon marca" aria-hidden="true">✦</span>
          <p><strong id="stat-total">—</strong><small>cartas cadastradas</small></p>
        </div>
        <div>
          <span class="stat-icon ouro" aria-hidden="true">◈</span>
          <p><strong><?= count($jogos) ?></strong><small>TCGs disponíveis</small></p>
        </div>
        <div>
          <span class="stat-icon realce" aria-hidden="true">◇</span>
          <p><strong id="stat-jogos">—</strong><small>jogos com cartas</small></p>
        </div>
        <div>
          <span class="stat-icon tinta" aria-hidden="true">◆</span>
          <p><strong id="stat-edicoes">—</strong><small>edições no acervo</small></p>
        </div>
      </section>

      <section class="catalog-section">
        <div class="section-heading">
          <div>
            <p class="eyebrow">INVENTÁRIO</p>
            <h2>Cartas cadastradas</h2>
          </div>
          <button class="primary-button" id="botao-nova-carta" type="button">
            <span class="rotulo">＋ Nova carta</span>
          </button>
        </div>

        <div class="toolbar">
          <label class="search-field" for="busca">
            <span aria-hidden="true">⌕</span>
            <input id="busca" type="search" placeholder="Buscar por nome ou edição"
                   aria-label="Buscar cartas por nome ou edição">
          </label>

          <div class="filter-tabs" role="group" aria-label="Filtrar por card game">
            <button class="filter-tab active" type="button" data-game="all">Todos</button>
            <?php foreach ($jogos as $chave => $rotulo): ?>
              <button class="filter-tab" type="button" data-game="<?= h($chave) ?>"><?= h($rotulo) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="skeleton-grid" id="catalogo-carregando" hidden aria-hidden="true">
          <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
          <div class="skeleton"></div><div class="skeleton"></div>
        </div>

        <div class="catalog-grid" id="grade-cartas" aria-live="polite"></div>

        <div class="catalogo-status" id="catalogo-status" hidden></div>
      </section>
    </main>

    <?php require __DIR__ . '/partials/rodape.php'; ?>
  </div>

  <!-- ============ Modal de inclusão / edição ============ -->
  <div class="modal-backdrop" id="modal-carta" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-carta-titulo">
      <button class="modal-close" type="button" data-fechar-modal aria-label="Fechar formulário">×</button>
      <p class="eyebrow" id="modal-carta-kicker">NOVA CARTA</p>
      <h2 id="modal-carta-titulo">Adicione ao acervo.</h2>

      <div class="alerta erro" id="form-alerta" role="alert" hidden></div>

      <form id="form-carta" class="card-form" novalidate enctype="multipart/form-data">
        <input type="hidden" name="id" id="carta-id" value="">

        <div class="form-grid">
          <label class="field" for="nome_en">
            <span>Nome em inglês<span class="obrigatorio" aria-hidden="true">*</span></span>
            <input id="nome_en" name="nome_en" type="text" maxlength="150"
                   placeholder="Ex.: Charizard" required aria-describedby="erro-nome_en">
            <span class="field-error" id="erro-nome_en" aria-live="polite"></span>
          </label>

          <label class="field" for="nome_pt">
            <span>Nome em português <span class="opcional">(opcional)</span></span>
            <input id="nome_pt" name="nome_pt" type="text" maxlength="150"
                   placeholder="Deixe em branco se não houver" aria-describedby="erro-nome_pt">
            <span class="field-error" id="erro-nome_pt" aria-live="polite"></span>
          </label>

          <label class="field" for="card_game">
            <span>Card game<span class="obrigatorio" aria-hidden="true">*</span></span>
            <select id="card_game" name="card_game" required aria-describedby="erro-card_game">
              <option value="">Selecione o card game</option>
              <?php foreach ($jogos as $chave => $rotulo): ?>
                <option value="<?= h($chave) ?>"><?= h($rotulo) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-error" id="erro-card_game" aria-live="polite"></span>
          </label>

          <div class="field">
            <label for="edicao_id">Edição<span class="obrigatorio" aria-hidden="true">*</span></label>
            <span class="select-wrap" id="edicao-wrap">
              <select id="edicao_id" name="edicao_id" required disabled
                      aria-describedby="ajuda-edicao erro-edicao_id">
                <option value="">Selecione o card game primeiro</option>
              </select>
            </span>
            <button class="select-retry" type="button" id="edicao-retry" hidden>
              Tentar carregar as edições novamente
            </button>
            <span class="ajuda" id="ajuda-edicao">A lista muda conforme o card game escolhido.</span>
            <span class="field-error" id="erro-edicao_id" aria-live="polite"></span>
          </div>

          <label class="field" for="raridade">
            <span>Raridade<span class="obrigatorio" aria-hidden="true">*</span></span>
            <select id="raridade" name="raridade" required aria-describedby="erro-raridade">
              <option value="">Selecione a raridade</option>
              <?php foreach ($raridades as $raridade): ?>
                <option value="<?= h($raridade) ?>"><?= h($raridade) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-error" id="erro-raridade" aria-live="polite"></span>
          </label>

          <div class="field imagem-campo">
            <span>Imagem da carta <span class="opcional">(opcional)</span></span>
            <div class="imagem-grade">
              <div class="imagem-previa" id="imagem-previa">
                <span id="imagem-previa-vazia">Sem imagem</span>
              </div>
              <div class="imagem-opcoes">
                <input id="imagem" name="imagem" type="file"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       aria-describedby="ajuda-imagem">
                <span class="imagem-separador">ou informe uma URL</span>
                <input id="imagem_url" name="imagem_url" type="url"
                       placeholder="https://exemplo.com/carta.png" aria-describedby="erro-imagem_url">
                <button class="remover-imagem" type="button" id="botao-remover-imagem" hidden>
                  Remover imagem atual
                </button>
                <span class="ajuda" id="ajuda-imagem">JPG, PNG, WEBP ou GIF, até 5 MB.</span>
                <span class="field-error" id="erro-imagem_url" aria-live="polite"></span>
              </div>
            </div>
            <input type="hidden" name="remover_imagem" id="remover_imagem" value="">
          </div>
        </div>

        <div class="modal-actions">
          <button class="secondary-button" type="button" data-fechar-modal>Cancelar</button>
          <button class="primary-button" type="submit" id="botao-salvar">
            <span class="rotulo">Salvar carta</span>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- ============ Modal de confirmação de exclusão ============ -->
  <div class="modal-backdrop" id="modal-exclusao" hidden>
    <section class="modal confirmacao" role="alertdialog" aria-modal="true"
             aria-labelledby="exclusao-titulo" aria-describedby="exclusao-descricao">
      <button class="modal-close" type="button" data-fechar-exclusao aria-label="Cancelar exclusão">×</button>
      <p class="eyebrow">CONFIRMAR EXCLUSÃO</p>
      <h2 id="exclusao-titulo">Excluir esta carta?</h2>
      <p id="exclusao-descricao">
        Você está prestes a excluir <span class="alvo" id="exclusao-nome"></span> do acervo.
      </p>
      <span class="irreversivel">Esta ação não pode ser desfeita.</span>

      <div class="modal-actions">
        <button class="secondary-button" type="button" data-fechar-exclusao>Manter carta</button>
        <button class="primary-button botao-perigo" type="button" id="botao-confirmar-exclusao">
          <span class="rotulo">Sim, excluir</span>
        </button>
      </div>
    </section>
  </div>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script src="assets/js/tema.js"></script>
  <script src="assets/js/common.js"></script>
  <script src="assets/js/cards.js"></script>
</body>
</html>
