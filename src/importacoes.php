<?php
declare(strict_types=1);

/** Importações: vínculo entre um deck offline do portal e o mesmo deck online. */

require_once __DIR__ . '/config/sessao.php';
require_once __DIR__ . '/config/edicoes.php';
require_once __DIR__ . '/config/plataformas.php';
require_once __DIR__ . '/config/vista.php';

$usuario = usuario_atual();

if ($usuario === null) {
    header('Location: index.php?expirada=1');
    exit;
}

$jogos  = rotulos_dos_jogos();
$status = rotulos_de_status();

/** Quantas plataformas o catálogo conhece somando todos os jogos. */
$totalPlataformas = array_sum(array_map('count', catalogo_plataformas()));

$paginaAtual = 'importacoes';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <?php $tituloDaPagina = 'Importações'; require __DIR__ . '/partials/documento.php'; ?>
</head>
<body>
  <a class="pular-para-conteudo" href="#conteudo">Pular para o conteúdo</a>

  <div class="site-shell">
    <?php require __DIR__ . '/partials/cabecalho.php'; ?>

    <main id="conteudo">
      <section class="dashboard-hero compacto">
        <div>
          <p class="eyebrow">IMPORTAÇÕES</p>
          <h1>Seu deck offline,<br><em>ligado ao online.</em></h1>
          <p>
            Aponte um deck do portal para a lista que você mantém no Moxfield,
            no TCG Live ou no Master Duel. Sincronize e as cartas caem no deck
            daqui — o que você montou na mão continua intocado.
          </p>
        </div>
        <div class="hero-art">
          <div class="hero-orbit" aria-hidden="true"></div>
          <img src="assets/img/hero-importacoes.jpg" alt="">
          <span class="hero-badge"><?= $totalPlataformas ?> plataformas<br><small>reconhecidas</small></span>
        </div>
      </section>

      <section class="quick-stats" aria-label="Resumo das importações">
        <div>
          <span class="stat-icon marca" aria-hidden="true">✦</span>
          <p><strong id="stat-total">—</strong><small>vínculos ativos</small></p>
        </div>
        <div>
          <span class="stat-icon ouro" aria-hidden="true">◈</span>
          <p><strong id="stat-sincronizados">—</strong><small>já sincronizados</small></p>
        </div>
        <div>
          <span class="stat-icon realce" aria-hidden="true">◇</span>
          <p><strong id="stat-pendentes">—</strong><small>aguardando</small></p>
        </div>
        <div>
          <span class="stat-icon tinta" aria-hidden="true">◆</span>
          <p><strong id="stat-cartas">—</strong><small>cartas vinculadas</small></p>
        </div>
      </section>

      <section class="catalog-section">
        <div class="section-heading">
          <div>
            <p class="eyebrow">SINCRONIZAÇÃO</p>
            <h2>Decks vinculados</h2>
          </div>
          <button class="primary-button" id="botao-novo-vinculo" type="button">
            <span class="rotulo">＋ Novo vínculo</span>
          </button>
        </div>

        <div class="alerta info" id="aviso-mock" role="note">
          <span>
            <strong>Integração simulada</strong><br>
            Nenhuma requisição sai para as plataformas: a lista online é gerada
            a partir do próprio acervo, mais algumas cartas propositalmente
            ausentes, para exercitar o caso em que nem tudo casa. A gravação no
            banco, os contadores e os estados são reais.
          </span>
        </div>

        <div class="toolbar">
          <div class="filter-tabs" role="group" aria-label="Filtrar por card game">
            <button class="filter-tab active" type="button" data-game="all">Todos os TCGs</button>
            <?php foreach ($jogos as $chave => $rotulo): ?>
              <button class="filter-tab" type="button" data-game="<?= h($chave) ?>"><?= h($rotulo) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="filter-tabs linha-secundaria" role="group" aria-label="Filtrar por situação">
          <button class="filter-tab active" type="button" data-status="all">Todas as situações</button>
          <?php foreach ($status as $chave => $rotulo): ?>
            <?php if ($chave === 'sincronizando') continue; ?>
            <button class="filter-tab" type="button" data-status="<?= h($chave) ?>"><?= h($rotulo) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="skeleton-grid vinculos" id="importacoes-carregando" hidden aria-hidden="true">
          <div class="skeleton"></div><div class="skeleton"></div>
        </div>

        <div class="vinculo-lista" id="lista-importacoes" aria-live="polite"></div>

        <div class="catalogo-status" id="importacoes-status" hidden></div>
      </section>
    </main>

    <?php require __DIR__ . '/partials/rodape.php'; ?>
  </div>

  <!-- ============ Modal de novo vínculo ============ -->
  <div class="modal-backdrop" id="modal-vinculo" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-vinculo-titulo">
      <button class="modal-close" type="button" data-fechar-vinculo aria-label="Fechar formulário">×</button>
      <p class="eyebrow">NOVO VÍNCULO</p>
      <h2 id="modal-vinculo-titulo">Ligue os dois decks.</h2>

      <div class="alerta erro" id="vinculo-alerta" role="alert" hidden></div>

      <form id="form-vinculo" class="card-form" novalidate>
        <div class="form-grid">
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
            <label for="plataforma">Plataforma<span class="obrigatorio" aria-hidden="true">*</span></label>
            <span class="select-wrap" id="plataforma-wrap">
              <select id="plataforma" name="plataforma" required disabled
                      aria-describedby="ajuda-plataforma erro-plataforma">
                <option value="">Selecione o card game primeiro</option>
              </select>
            </span>
            <button class="select-retry" type="button" id="plataforma-retry" hidden>
              Tentar carregar as plataformas novamente
            </button>
            <span class="ajuda" id="ajuda-plataforma">A lista muda conforme o card game escolhido.</span>
            <span class="field-error" id="erro-plataforma" aria-live="polite"></span>
          </div>

          <label class="field larga" for="identificador">
            <span id="rotulo-identificador">Identificador do deck online<span class="obrigatorio" aria-hidden="true">*</span></span>
            <input id="identificador" name="identificador" type="text" maxlength="255"
                   placeholder="Escolha a plataforma para ver o formato"
                   required aria-describedby="ajuda-identificador erro-identificador">
            <span class="ajuda" id="ajuda-identificador">Cada plataforma expõe o deck de um jeito.</span>
            <span class="field-error" id="erro-identificador" aria-live="polite"></span>
          </label>

          <div class="field larga">
            <label for="colecao_id">Deck offline de destino<span class="obrigatorio" aria-hidden="true">*</span></label>
            <span class="select-wrap" id="colecao-wrap">
              <select id="colecao_id" name="colecao_id" required disabled
                      aria-describedby="ajuda-colecao erro-colecao_id">
                <option value="">Selecione o card game primeiro</option>
              </select>
            </span>
            <span class="ajuda" id="ajuda-colecao">
              Só decks aparecem aqui. Binders e listas de desejo não recebem vínculo.
              Não tem nenhum? <a href="colecoes.php">Crie um deck em Coleções</a>.
            </span>
            <span class="field-error" id="erro-colecao_id" aria-live="polite"></span>
          </div>
        </div>

        <div class="modal-actions">
          <button class="secondary-button" type="button" data-fechar-vinculo>Cancelar</button>
          <button class="primary-button" type="submit" id="botao-salvar-vinculo">
            <span class="rotulo">Criar vínculo</span>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- ============ Modal de confirmação de desvinculação ============ -->
  <div class="modal-backdrop" id="modal-desvincular" hidden>
    <section class="modal confirmacao" role="alertdialog" aria-modal="true"
             aria-labelledby="desvincular-titulo" aria-describedby="desvincular-descricao">
      <button class="modal-close" type="button" data-fechar-desvincular aria-label="Cancelar">×</button>
      <p class="eyebrow">CONFIRMAR</p>
      <h2 id="desvincular-titulo">Desfazer este vínculo?</h2>
      <p id="desvincular-descricao">
        O deck <span class="alvo" id="desvincular-nome"></span> volta a ser só offline.
        As cartas que vieram da importação saem dele; o que você incluiu na mão fica.
      </p>
      <span class="irreversivel">Para religar, é preciso criar o vínculo de novo.</span>

      <div class="modal-actions">
        <button class="secondary-button" type="button" data-fechar-desvincular>Manter vínculo</button>
        <button class="primary-button botao-perigo" type="button" id="botao-confirmar-desvinculo">
          <span class="rotulo">Sim, desvincular</span>
        </button>
      </div>
    </section>
  </div>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script src="assets/js/tema.js"></script>
  <script src="assets/js/common.js"></script>
  <script src="assets/js/importacoes.js"></script>
</body>
</html>
