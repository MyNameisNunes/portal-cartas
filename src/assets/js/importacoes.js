'use strict';

/**
 * Importações: a lista de vínculos entre decks offline e decks online, com a
 * sincronização disparada por linha e o formulário de criação, onde plataforma
 * e deck de destino dependem do card game escolhido.
 */
(() => {
  const estado = {
    importacoes: [],
    jogo: 'all',
    status: 'all',
    primeiraCarga: true,
    desvinculoAlvo: null,
  };

  const lista = document.getElementById('lista-importacoes');
  const status = document.getElementById('importacoes-status');
  const esqueleto = document.getElementById('importacoes-carregando');

  const modalVinculo = document.getElementById('modal-vinculo');
  const modalDesvincular = document.getElementById('modal-desvincular');

  const formulario = document.getElementById('form-vinculo');
  const alertaFormulario = document.getElementById('vinculo-alerta');
  const botaoSalvar = document.getElementById('botao-salvar-vinculo');

  const selectJogo = document.getElementById('card_game');
  const selectPlataforma = document.getElementById('plataforma');
  const plataformaWrap = document.getElementById('plataforma-wrap');
  const plataformaRetry = document.getElementById('plataforma-retry');

  const selectColecao = document.getElementById('colecao_id');
  const colecaoWrap = document.getElementById('colecao-wrap');

  const campoIdentificador = document.getElementById('identificador');
  const rotuloIdentificador = document.getElementById('rotulo-identificador');
  const ajudaIdentificador = document.getElementById('ajuda-identificador');

  const botaoConfirmarDesvinculo = document.getElementById('botao-confirmar-desvinculo');

  let elementoFocadoAntesDoModal = null;

  // ================================================================
  // Listagem
  // ================================================================

  function mostrarStatus(titulo, descricao) {
    status.innerHTML = `<strong>${Portal.escaparHtml(titulo)}</strong>${Portal.escaparHtml(descricao)}`;
    status.hidden = false;
  }

  /** "2026-09-08 13:22:27" -> "08/09/2026 às 13:22". */
  function formatarData(valor) {
    if (!valor) return null;

    // O backend devolve o formato do MySQL; o Safari não o aceita direto.
    const data = new Date(String(valor).replace(' ', 'T'));
    if (Number.isNaN(data.getTime())) return null;

    const dia = String(data.getDate()).padStart(2, '0');
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const hora = String(data.getHours()).padStart(2, '0');
    const minuto = String(data.getMinutes()).padStart(2, '0');

    return `${dia}/${mes}/${data.getFullYear()} às ${hora}:${minuto}`;
  }

  function destinoHtml(importacao) {
    if (importacao.colecao_removida) {
      return '<span class="vinculo-destino removido">coleção removida</span>';
    }

    return `<span class="vinculo-destino">${Portal.escaparHtml(importacao.colecao_nome)}</span>`;
  }

  function importacaoHtml(importacao) {
    const sincronizado = formatarData(importacao.sincronizado_em);

    const rodape = sincronizado
      ? `Última sincronização em ${sincronizado}`
      : 'Ainda não sincronizado.';

    // A mensagem é o que o backend gravou no último ciclo: quantas cartas
    // casaram, quais ficaram de fora, ou por que a tentativa falhou.
    const mensagem = importacao.mensagem
      ? `<p class="vinculo-mensagem">${Portal.escaparHtml(importacao.mensagem)}</p>`
      : '';

    const contadores = importacao.status === 'concluida'
      ? `<span class="vinculo-numeros">
           <strong>${importacao.cartas_vinculadas}</strong> de
           <strong>${importacao.cartas_encontradas}</strong> cartas da lista online
         </span>`
      : '';

    return `
      <article class="vinculo-card" data-id="${importacao.id}">
        <div class="vinculo-topo">
          <span class="status-pill" data-status="${Portal.escaparHtml(importacao.status)}">${Portal.escaparHtml(importacao.status_nome)}</span>
          <span class="vinculo-jogo">${Portal.escaparHtml(importacao.card_game_nome)}</span>
        </div>

        <h3>${Portal.escaparHtml(importacao.plataforma_nome)}</h3>
        <p class="vinculo-identificador">
          <code>${Portal.escaparHtml(importacao.identificador)}</code>
          <span aria-hidden="true">→</span>
          ${destinoHtml(importacao)}
        </p>

        ${contadores}
        ${mensagem}

        <p class="vinculo-rodape">${Portal.escaparHtml(rodape)}</p>

        <div class="card-actions">
          <button type="button" data-sincronizar="${importacao.id}">
            <span class="rotulo">${importacao.status === 'concluida' ? 'Sincronizar de novo' : 'Sincronizar agora'}</span>
          </button>
          <button type="button" data-desvincular="${importacao.id}">Desvincular</button>
        </div>
      </article>`;
  }

  function renderizar() {
    if (estado.importacoes.length === 0) {
      lista.innerHTML = '';
      const filtrando = estado.jogo !== 'all' || estado.status !== 'all';
      mostrarStatus(
        filtrando ? 'Nenhum vínculo nesse filtro' : 'Nenhum deck vinculado ainda',
        filtrando
          ? 'Escolha outro TCG ou outra situação.'
          : 'Use “Novo vínculo” para apontar um deck daqui para a sua lista online.'
      );
      return;
    }

    status.hidden = true;
    lista.innerHTML = estado.importacoes.map(importacaoHtml).join('');
  }

  function atualizarIndicadores(resumo) {
    if (!resumo) return;
    document.getElementById('stat-total').textContent = resumo.total;
    document.getElementById('stat-sincronizados').textContent = resumo.sincronizados;
    document.getElementById('stat-pendentes').textContent = resumo.pendentes;
    document.getElementById('stat-cartas').textContent = resumo.cartas;
  }

  async function carregarImportacoes() {
    if (estado.primeiraCarga) {
      esqueleto.hidden = false;
      lista.hidden = true;
    } else {
      lista.style.opacity = '0.55';
    }
    status.hidden = true;

    const parametros = new URLSearchParams();
    if (estado.jogo !== 'all') parametros.set('game', estado.jogo);
    if (estado.status !== 'all') parametros.set('status', estado.status);

    try {
      const resposta = await Portal.api(`api/importacoes.php?${parametros.toString()}`);
      estado.importacoes = resposta.importacoes || [];
      renderizar();
      atualizarIndicadores(resposta.resumo);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      lista.innerHTML = '';
      mostrarStatus('Não foi possível carregar os vínculos', falha.message);
    } finally {
      estado.primeiraCarga = false;
      esqueleto.hidden = true;
      lista.hidden = false;
      lista.style.opacity = '';
    }
  }

  // ================================================================
  // Modais
  // ================================================================

  function abrirModal(modal) {
    elementoFocadoAntesDoModal = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function fecharModal(modal) {
    modal.hidden = true;
    document.body.style.overflow = '';
    if (elementoFocadoAntesDoModal) elementoFocadoAntesDoModal.focus();
  }

  document.querySelectorAll('[data-fechar-vinculo]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalVinculo));
  });

  document.querySelectorAll('[data-fechar-desvincular]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalDesvincular));
  });

  [modalVinculo, modalDesvincular].forEach((modal) => {
    modal.addEventListener('mousedown', (evento) => {
      if (evento.target === modal) fecharModal(modal);
    });
  });

  document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') return;
    if (!modalDesvincular.hidden) fecharModal(modalDesvincular);
    else if (!modalVinculo.hidden) fecharModal(modalVinculo);
  });

  // ================================================================
  // Selects dependentes do card game
  // ================================================================

  const cachePlataformas = new Map();
  let controladorPlataformas = null;

  function definirOpcoes(select, opcoes) {
    select.innerHTML = opcoes
      .map((opcao) => `<option value="${Portal.escaparHtml(opcao.valor)}">${Portal.escaparHtml(opcao.texto)}</option>`)
      .join('');
  }

  /**
   * O rótulo e o placeholder do identificador mudam com a plataforma: o que o
   * Moxfield chama de "ID do deck" o TCG Live chama de "código de
   * compartilhamento". Deixar o campo genérico é o caminho mais curto para o
   * usuário colar a coisa errada.
   */
  function ajustarCampoIdentificador(plataforma) {
    if (!plataforma) {
      rotuloIdentificador.innerHTML =
        'Identificador do deck online<span class="obrigatorio" aria-hidden="true">*</span>';
      campoIdentificador.placeholder = 'Escolha a plataforma para ver o formato';
      ajudaIdentificador.textContent = 'Cada plataforma expõe o deck de um jeito.';
      return;
    }

    rotuloIdentificador.innerHTML =
      `${Portal.escaparHtml(plataforma.identificador)}<span class="obrigatorio" aria-hidden="true">*</span>`;
    campoIdentificador.placeholder = `Ex.: ${plataforma.exemplo}`;
    ajudaIdentificador.textContent = `Cole aqui o que o ${plataforma.name} mostra na tela do deck.`;
  }

  /**
   * Busca as plataformas do card game escolhido, no mesmo desenho do select de
   * edições do painel: desabilita e mostra o spinner, popula e reabilita, ou
   * cai em estado de erro com botão de nova tentativa. Uma troca rápida de
   * card game aborta a requisição anterior.
   */
  async function carregarPlataformas(jogo) {
    if (controladorPlataformas) controladorPlataformas.abort();

    plataformaRetry.hidden = true;
    selectPlataforma.removeAttribute('aria-invalid');
    document.getElementById('erro-plataforma').textContent = '';
    ajustarCampoIdentificador(null);

    if (!jogo) {
      plataformaWrap.dataset.estado = 'vazio';
      selectPlataforma.disabled = true;
      definirOpcoes(selectPlataforma, [{ valor: '', texto: 'Selecione o card game primeiro' }]);
      return;
    }

    if (cachePlataformas.has(jogo)) {
      preencherPlataformas(cachePlataformas.get(jogo));
      return;
    }

    plataformaWrap.dataset.estado = 'carregando';
    selectPlataforma.disabled = true;
    definirOpcoes(selectPlataforma, [{ valor: '', texto: 'Carregando plataformas…' }]);

    const controlador = new AbortController();
    controladorPlataformas = controlador;

    try {
      const resposta = await Portal.api(
        `api/plataformas.php?game=${encodeURIComponent(jogo)}`,
        { signal: controlador.signal }
      );

      if (controlador !== controladorPlataformas) return; // resposta obsoleta

      const plataformas = resposta.plataformas || [];
      cachePlataformas.set(jogo, plataformas);
      preencherPlataformas(plataformas);
    } catch (falha) {
      if (falha.name === 'AbortError') return;
      if (Portal.sessaoExpirou(falha)) return;

      plataformaWrap.dataset.estado = 'erro';
      selectPlataforma.disabled = true;
      definirOpcoes(selectPlataforma, [{ valor: '', texto: 'Não foi possível carregar as plataformas' }]);
      plataformaRetry.hidden = false;
      plataformaRetry.dataset.jogo = jogo;
    } finally {
      if (controlador === controladorPlataformas) controladorPlataformas = null;
    }
  }

  function preencherPlataformas(plataformas) {
    plataformaWrap.dataset.estado = 'pronto';
    definirOpcoes(selectPlataforma, [
      { valor: '', texto: 'Selecione a plataforma' },
      ...plataformas.map((plataforma) => ({ valor: plataforma.id, texto: plataforma.name })),
    ]);
    selectPlataforma.disabled = false;
    selectPlataforma.value = '';
  }

  /**
   * Só decks do mesmo card game podem receber o vínculo — é a mesma regra que
   * o backend aplica. Trazer a lista já filtrada evita oferecer uma opção que
   * seria recusada no submit.
   */
  async function carregarDecks(jogo) {
    document.getElementById('erro-colecao_id').textContent = '';
    selectColecao.removeAttribute('aria-invalid');

    if (!jogo) {
      colecaoWrap.dataset.estado = 'vazio';
      selectColecao.disabled = true;
      definirOpcoes(selectColecao, [{ valor: '', texto: 'Selecione o card game primeiro' }]);
      return;
    }

    colecaoWrap.dataset.estado = 'carregando';
    selectColecao.disabled = true;
    definirOpcoes(selectColecao, [{ valor: '', texto: 'Carregando decks…' }]);

    try {
      const resposta = await Portal.api(
        `api/colecoes.php?tipo=deck&game=${encodeURIComponent(jogo)}`
      );
      const decks = resposta.colecoes || [];

      colecaoWrap.dataset.estado = 'pronto';

      if (decks.length === 0) {
        definirOpcoes(selectColecao, [{ valor: '', texto: 'Nenhum deck deste TCG ainda' }]);
        selectColecao.disabled = true;
        return;
      }

      definirOpcoes(selectColecao, [
        { valor: '', texto: 'Selecione o deck de destino' },
        ...decks.map((deck) => ({
          valor: String(deck.id),
          texto: `${deck.nome} (${deck.total_unidades} cartas)`,
        })),
      ]);
      selectColecao.disabled = false;
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      colecaoWrap.dataset.estado = 'erro';
      selectColecao.disabled = true;
      definirOpcoes(selectColecao, [{ valor: '', texto: 'Não foi possível carregar os decks' }]);
    }
  }

  selectJogo.addEventListener('change', () => {
    carregarPlataformas(selectJogo.value);
    carregarDecks(selectJogo.value);
  });

  selectPlataforma.addEventListener('change', () => {
    const plataformas = cachePlataformas.get(selectJogo.value) || [];
    ajustarCampoIdentificador(plataformas.find((item) => item.id === selectPlataforma.value) || null);
  });

  plataformaRetry.addEventListener('click', () => {
    cachePlataformas.delete(plataformaRetry.dataset.jogo);
    carregarPlataformas(selectJogo.value);
  });

  // ================================================================
  // Criação do vínculo
  // ================================================================

  function abrirFormulario() {
    formulario.reset();
    Portal.limparErros(formulario);
    Portal.esconderAlerta(alertaFormulario);

    carregarPlataformas('');
    carregarDecks('');

    abrirModal(modalVinculo);
    selectJogo.focus();
  }

  function validarFormulario() {
    const problemas = {};

    if (!selectJogo.value) {
      problemas.card_game = 'Selecione o card game.';
    }
    if (!selectPlataforma.value) {
      problemas.plataforma = selectPlataforma.disabled
        ? 'Escolha o card game para carregar as plataformas.'
        : 'Selecione a plataforma onde o deck está.';
    }
    if (!campoIdentificador.value.trim()) {
      problemas.identificador = 'Informe o identificador do deck online.';
    }
    if (!selectColecao.value) {
      problemas.colecao_id = selectColecao.disabled
        ? 'Crie um deck deste TCG em Coleções antes de vincular.'
        : 'Escolha o deck offline de destino.';
    }

    return problemas;
  }

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    Portal.esconderAlerta(alertaFormulario);
    Portal.limparErros(formulario);

    const problemas = validarFormulario();
    if (Object.keys(problemas).length > 0) {
      Portal.aplicarErros(formulario, problemas);
      return;
    }

    Portal.ocupar(botaoSalvar, true, 'Criando…');

    try {
      const resposta = await Portal.api('api/importacoes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          card_game: selectJogo.value,
          plataforma: selectPlataforma.value,
          identificador: campoIdentificador.value.trim(),
          colecao_id: Number(selectColecao.value),
        }),
      });

      fecharModal(modalVinculo);
      Portal.notificar(resposta.message || 'Vínculo criado.');
      await carregarImportacoes();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      Portal.mostrarAlerta(alertaFormulario, falha.message);
      if (falha.campos) Portal.aplicarErros(formulario, falha.campos);
    } finally {
      Portal.ocupar(botaoSalvar, false);
    }
  });

  // ================================================================
  // Sincronização e desvinculação
  // ================================================================

  async function sincronizar(id, botao) {
    Portal.ocupar(botao, true, 'Sincronizando…');

    try {
      const resposta = await Portal.api(
        `api/importacoes.php?id=${encodeURIComponent(id)}&acao=sincronizar`,
        { method: 'POST' }
      );

      // A sincronização pode terminar em erro de negócio com HTTP 200: o
      // status do registro é a fonte da verdade, não o código da resposta.
      const falhou = resposta.importacao && resposta.importacao.status === 'erro';
      Portal.notificar(resposta.message, falhou ? 'erro' : 'ok');

      await carregarImportacoes();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      Portal.notificar(falha.message, 'erro');
      Portal.ocupar(botao, false);
    }
  }

  function pedirConfirmacao(importacao) {
    estado.desvinculoAlvo = importacao;
    document.getElementById('desvincular-nome').textContent =
      importacao.colecao_nome || 'sem coleção';

    abrirModal(modalDesvincular);
    botaoConfirmarDesvinculo.focus();
  }

  botaoConfirmarDesvinculo.addEventListener('click', async () => {
    const importacao = estado.desvinculoAlvo;
    if (!importacao) return;

    Portal.ocupar(botaoConfirmarDesvinculo, true, 'Desvinculando…');

    try {
      await Portal.api(`api/importacoes.php?id=${encodeURIComponent(importacao.id)}`, {
        method: 'DELETE',
      });
      fecharModal(modalDesvincular);
      Portal.notificar('Vínculo desfeito.');
      estado.desvinculoAlvo = null;
      await carregarImportacoes();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      fecharModal(modalDesvincular);
      Portal.notificar(falha.message, 'erro');
    } finally {
      Portal.ocupar(botaoConfirmarDesvinculo, false);
    }
  });

  // ================================================================
  // Ligações da página
  // ================================================================

  lista.addEventListener('click', (evento) => {
    const botao = evento.target.closest('[data-sincronizar], [data-desvincular]');
    if (!botao) return;

    const id = Number(botao.dataset.sincronizar || botao.dataset.desvincular);
    const importacao = estado.importacoes.find((item) => item.id === id);
    if (!importacao) return;

    if (botao.dataset.sincronizar) sincronizar(id, botao);
    else pedirConfirmacao(importacao);
  });

  document.getElementById('botao-novo-vinculo').addEventListener('click', abrirFormulario);

  /** Os dois grupos de abas se comportam igual, só mudam a chave do estado. */
  function ligarAbas(atributo, chave) {
    const abas = document.querySelectorAll(`.filter-tab[data-${atributo}]`);

    abas.forEach((aba) => {
      aba.addEventListener('click', () => {
        abas.forEach((outra) => outra.classList.remove('active'));
        aba.classList.add('active');
        estado[chave] = aba.dataset[atributo];
        carregarImportacoes();
      });
    });
  }

  ligarAbas('game', 'jogo');
  ligarAbas('status', 'status');

  carregarImportacoes();
})();
