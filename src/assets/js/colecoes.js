'use strict';

/**
 * Coleções: listagem filtrada por TCG e por tipo, formulário de criação/edição
 * e o modal de conteúdo, onde as cartas do acervo entram e saem da coleção.
 */
(() => {
  const estado = {
    colecoes: [],
    jogo: 'all',
    tipo: 'all',
    busca: '',
    primeiraCarga: true,
    aberta: null,
    exclusaoAlvo: null,
  };

  const grade = document.getElementById('grade-colecoes');
  const status = document.getElementById('colecoes-status');
  const esqueleto = document.getElementById('colecoes-carregando');
  const campoBusca = document.getElementById('busca');

  const modalColecao = document.getElementById('modal-colecao');
  const modalConteudo = document.getElementById('modal-conteudo');
  const modalExclusao = document.getElementById('modal-exclusao');

  const formulario = document.getElementById('form-colecao');
  const alertaFormulario = document.getElementById('colecao-alerta');
  const botaoSalvar = document.getElementById('botao-salvar-colecao');

  const formIncluir = document.getElementById('form-incluir-carta');
  const alertaConteudo = document.getElementById('conteudo-alerta');
  const listaConteudo = document.getElementById('conteudo-lista');
  const selectCarta = document.getElementById('carta_id');
  const cartaWrap = document.getElementById('carta-wrap');
  const botaoIncluir = document.getElementById('botao-incluir-carta');

  const selectTipo = document.getElementById('tipo');
  const ajudaTipo = document.getElementById('ajuda-tipo');
  const botaoConfirmarExclusao = document.getElementById('botao-confirmar-exclusao');

  let elementoFocadoAntesDoModal = null;

  // ================================================================
  // Listagem
  // ================================================================

  function mostrarStatus(titulo, descricao) {
    status.innerHTML = `<strong>${Portal.escaparHtml(titulo)}</strong>${Portal.escaparHtml(descricao)}`;
    status.hidden = false;
  }

  /** "Charizard Control" -> "CC". Placeholder de cada célula do mosaico. */
  function iniciaisDaCarta(nome) {
    return String(nome || '?')
      .split(/[\s,.-]+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((parte) => parte.charAt(0))
      .join('')
      .toUpperCase();
  }

  /**
   * Mosaico de até quatro capas. As células vazias continuam no DOM para o
   * quadrado não mudar de forma conforme a coleção enche — a listagem fica
   * estável mesmo com coleções de tamanhos muito diferentes.
   */
  function mosaicoHtml(capas) {
    const celulas = [];

    for (let indice = 0; indice < 4; indice += 1) {
      const capa = capas[indice];

      if (!capa) {
        celulas.push('<span class="mosaico-celula vazia" aria-hidden="true"></span>');
        continue;
      }

      const placeholder = `<span class="mosaico-iniciais" aria-hidden="true">${Portal.escaparHtml(iniciaisDaCarta(capa.nome_en))}</span>`;
      const imagem = capa.imagem_url
        ? `<img src="${Portal.escaparHtml(capa.imagem_url)}" alt="" loading="lazy">`
        : '';

      celulas.push(`<span class="mosaico-celula">${placeholder}${imagem}</span>`);
    }

    return `<div class="colecao-mosaico" aria-hidden="true">${celulas.join('')}</div>`;
  }

  function colecaoHtml(colecao) {
    const descricao = colecao.descricao
      ? `<p class="colecao-descricao">${Portal.escaparHtml(colecao.descricao)}</p>`
      : '<p class="colecao-descricao vazia">Sem descrição.</p>';

    const unidades = colecao.total_unidades === 1 ? '1 carta' : `${colecao.total_unidades} cartas`;
    const distintas = colecao.total_cartas === 1 ? '1 título' : `${colecao.total_cartas} títulos`;

    return `
      <article class="colecao-card">
        ${mosaicoHtml(colecao.capas || [])}
        <div class="colecao-corpo">
          <div class="colecao-marcadores">
            <span class="tipo-badge" data-tipo="${Portal.escaparHtml(colecao.tipo)}">${Portal.escaparHtml(colecao.tipo_nome)}</span>
            <span class="colecao-jogo">${Portal.escaparHtml(colecao.card_game_nome)}</span>
          </div>
          <h3 title="${Portal.escaparHtml(colecao.nome)}">${Portal.escaparHtml(colecao.nome)}</h3>
          ${descricao}
          <p class="colecao-contagem">${Portal.escaparHtml(unidades)} · ${Portal.escaparHtml(distintas)}</p>
          <div class="card-actions">
            <button type="button" data-abrir="${colecao.id}">Abrir</button>
            <button type="button" data-editar="${colecao.id}">Editar</button>
            <button type="button" data-excluir="${colecao.id}">Excluir</button>
          </div>
        </div>
      </article>`;
  }

  function renderizar() {
    if (estado.colecoes.length === 0) {
      grade.innerHTML = '';
      const filtrando = estado.jogo !== 'all' || estado.tipo !== 'all' || estado.busca.trim() !== '';
      mostrarStatus(
        filtrando ? 'Nenhuma coleção encontrada' : 'Você ainda não tem coleções',
        filtrando
          ? 'Ajuste a busca ou escolha outro TCG no filtro.'
          : 'Crie a primeira usando o botão “Nova coleção”.'
      );
      return;
    }

    status.hidden = true;
    grade.innerHTML = estado.colecoes.map(colecaoHtml).join('');

    // URLs externas podem estar quebradas: cai para as iniciais da carta.
    grade.querySelectorAll('.colecao-mosaico img').forEach((imagem) => {
      imagem.addEventListener('error', () => imagem.remove(), { once: true });
    });
  }

  function atualizarIndicadores(resumo) {
    if (!resumo) return;
    document.getElementById('stat-colecoes').textContent = resumo.total;
    document.getElementById('stat-jogos').textContent = resumo.jogos;
    document.getElementById('stat-decks').textContent = resumo.decks;
    document.getElementById('stat-unidades').textContent = resumo.unidades;
  }

  async function carregarColecoes() {
    // O esqueleto só aparece na primeira carga; refiltros mantêm a lista
    // visível para a tela não "piscar" a cada tecla digitada.
    if (estado.primeiraCarga) {
      esqueleto.hidden = false;
      grade.hidden = true;
    } else {
      grade.style.opacity = '0.55';
    }
    status.hidden = true;

    const parametros = new URLSearchParams();
    if (estado.jogo !== 'all') parametros.set('game', estado.jogo);
    if (estado.tipo !== 'all') parametros.set('tipo', estado.tipo);
    if (estado.busca.trim() !== '') parametros.set('search', estado.busca.trim());

    try {
      const resposta = await Portal.api(`api/colecoes.php?${parametros.toString()}`);
      estado.colecoes = resposta.colecoes || [];
      renderizar();
      atualizarIndicadores(resposta.resumo);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      grade.innerHTML = '';
      mostrarStatus('Não foi possível carregar as coleções', falha.message);
    } finally {
      estado.primeiraCarga = false;
      esqueleto.hidden = true;
      grade.hidden = false;
      grade.style.opacity = '';
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

  document.querySelectorAll('[data-fechar-colecao]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalColecao));
  });

  document.querySelectorAll('[data-fechar-conteudo]').forEach((botao) => {
    botao.addEventListener('click', () => {
      fecharModal(modalConteudo);
      // Contagens e capas mudaram enquanto o modal esteve aberto.
      carregarColecoes();
    });
  });

  document.querySelectorAll('[data-fechar-exclusao]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalExclusao));
  });

  [modalColecao, modalConteudo, modalExclusao].forEach((modal) => {
    modal.addEventListener('mousedown', (evento) => {
      if (evento.target === modal) fecharModal(modal);
    });
  });

  document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') return;
    if (!modalExclusao.hidden) fecharModal(modalExclusao);
    else if (!modalConteudo.hidden) fecharModal(modalConteudo);
    else if (!modalColecao.hidden) fecharModal(modalColecao);
  });

  // ================================================================
  // Formulário da coleção
  // ================================================================

  const descricoesDosTipos = JSON.parse(ajudaTipo.dataset.descricoes || '{}');

  selectTipo.addEventListener('change', () => {
    ajudaTipo.textContent = descricoesDosTipos[selectTipo.value] || '';
  });

  function abrirFormulario(colecao) {
    formulario.reset();
    Portal.limparErros(formulario);
    Portal.esconderAlerta(alertaFormulario);

    const kicker = document.getElementById('modal-colecao-kicker');
    const titulo = document.getElementById('modal-colecao-titulo');

    if (colecao) {
      kicker.textContent = 'EDITAR COLEÇÃO';
      titulo.textContent = 'Ajuste os detalhes.';

      formulario.elements.id.value = colecao.id;
      formulario.elements.nome.value = colecao.nome || '';
      formulario.elements.descricao.value = colecao.descricao || '';
      formulario.elements.card_game.value = colecao.card_game;
      formulario.elements.tipo.value = colecao.tipo;
    } else {
      kicker.textContent = 'NOVA COLEÇÃO';
      titulo.textContent = 'Comece uma coleção.';
      formulario.elements.id.value = '';
    }

    ajudaTipo.textContent = descricoesDosTipos[selectTipo.value] || '';

    abrirModal(modalColecao);
    formulario.elements.nome.focus();
  }

  function validarFormulario() {
    const problemas = {};

    if (!formulario.elements.nome.value.trim()) {
      problemas.nome = 'Dê um nome para a coleção.';
    }
    if (!formulario.elements.card_game.value) {
      problemas.card_game = 'Selecione o card game.';
    }
    if (!formulario.elements.tipo.value) {
      problemas.tipo = 'Escolha o tipo da coleção.';
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

    const id = formulario.elements.id.value;
    const corpo = {
      nome: formulario.elements.nome.value.trim(),
      descricao: formulario.elements.descricao.value.trim(),
      card_game: formulario.elements.card_game.value,
      tipo: formulario.elements.tipo.value,
    };

    Portal.ocupar(botaoSalvar, true, id ? 'Salvando…' : 'Criando…');

    try {
      const resposta = await Portal.api(id ? `api/colecoes.php?id=${encodeURIComponent(id)}` : 'api/colecoes.php', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corpo),
      });

      fecharModal(modalColecao);
      Portal.notificar(resposta.message || 'Coleção salva.');
      await carregarColecoes();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      Portal.mostrarAlerta(alertaFormulario, falha.message);
      if (falha.campos) Portal.aplicarErros(formulario, falha.campos);
    } finally {
      Portal.ocupar(botaoSalvar, false);
    }
  });

  // ================================================================
  // Conteúdo da coleção
  // ================================================================

  /** Cartas do acervo por card game, buscadas uma vez por jogo. */
  const cacheCartas = new Map();

  async function carregarCartasDoAcervo(jogo) {
    if (cacheCartas.has(jogo)) return cacheCartas.get(jogo);

    cartaWrap.dataset.estado = 'carregando';
    selectCarta.disabled = true;
    selectCarta.innerHTML = '<option value="">Carregando cartas…</option>';

    try {
      const resposta = await Portal.api(
        `api/cartas.php?limit=100&game=${encodeURIComponent(jogo)}`
      );
      const cartas = resposta.cartas || [];
      cacheCartas.set(jogo, cartas);
      return cartas;
    } finally {
      cartaWrap.dataset.estado = 'pronto';
    }
  }

  /**
   * O select só oferece o que ainda não está na coleção: incluir uma carta
   * repetida não faria nada além de sobrescrever a quantidade, e o usuário
   * tem o campo de quantidade da própria linha para isso.
   */
  function preencherSelectDeCartas(cartas, jaIncluidas) {
    const disponiveis = cartas.filter((carta) => !jaIncluidas.has(carta.id));

    if (disponiveis.length === 0) {
      selectCarta.innerHTML = '<option value="">Todo o acervo deste TCG já está aqui</option>';
      selectCarta.disabled = true;
      return;
    }

    selectCarta.innerHTML = [
      '<option value="">Selecione a carta</option>',
      ...disponiveis.map((carta) => {
        const rotulo = `${carta.nome_en} — ${carta.edicao_nome}`;
        return `<option value="${carta.id}">${Portal.escaparHtml(rotulo)}</option>`;
      }),
    ].join('');
    selectCarta.disabled = false;
  }

  function linhaDaCartaHtml(carta) {
    const placeholder = `<span class="linha-iniciais" aria-hidden="true">${Portal.escaparHtml(iniciaisDaCarta(carta.nome_en))}</span>`;
    const imagem = carta.imagem_url
      ? `<img src="${Portal.escaparHtml(carta.imagem_url)}" alt="" loading="lazy">`
      : '';

    // Cartas trazidas por uma sincronização ganham selo: mexer nelas de novo
    // pelo painel de importações é o caminho esperado.
    const selo = carta.origem === 'importacao'
      ? '<span class="selo-origem" title="Veio de uma importação">importada</span>'
      : '';

    return `
      <li class="conteudo-item">
        <span class="conteudo-capa">${placeholder}${imagem}</span>
        <span class="conteudo-texto">
          <strong>${Portal.escaparHtml(carta.nome_en)}</strong>
          <small>${Portal.escaparHtml(carta.edicao_nome)} · ${Portal.escaparHtml(carta.raridade)}</small>
        </span>
        ${selo}
        <span class="conteudo-quantidade">${carta.quantidade}×</span>
        <button type="button" class="tirar-carta" data-tirar="${carta.id}"
                aria-label="Tirar ${Portal.escaparHtml(carta.nome_en)} da coleção">Tirar</button>
      </li>`;
  }

  function renderizarConteudo(cartas) {
    if (cartas.length === 0) {
      listaConteudo.innerHTML =
        '<p class="conteudo-vazio">Nenhuma carta aqui ainda. Use o campo acima para incluir a primeira.</p>';
      return;
    }

    const unidades = cartas.reduce((soma, carta) => soma + carta.quantidade, 0);

    listaConteudo.innerHTML = `
      <p class="conteudo-resumo">${cartas.length} título(s) · ${unidades} carta(s)</p>
      <ul class="conteudo-cartas">${cartas.map(linhaDaCartaHtml).join('')}</ul>`;

    listaConteudo.querySelectorAll('.conteudo-capa img').forEach((imagem) => {
      imagem.addEventListener('error', () => imagem.remove(), { once: true });
    });
  }

  /** Reflete as cartas atuais na tela e no select de inclusão. */
  async function sincronizarConteudo(cartas) {
    estado.aberta.cartas = cartas;
    renderizarConteudo(cartas);

    const acervo = await carregarCartasDoAcervo(estado.aberta.card_game);
    preencherSelectDeCartas(acervo, new Set(cartas.map((carta) => carta.id)));
  }

  async function abrirConteudo(colecaoResumo) {
    Portal.esconderAlerta(alertaConteudo);
    Portal.limparErros(formIncluir);
    formIncluir.elements.quantidade.value = '1';

    document.getElementById('conteudo-kicker').textContent =
      `${colecaoResumo.tipo_nome.toUpperCase()} · ${colecaoResumo.card_game_nome}`;
    document.getElementById('conteudo-titulo').textContent = colecaoResumo.nome;
    document.getElementById('conteudo-descricao').textContent = colecaoResumo.descricao || '';

    listaConteudo.innerHTML = '<p class="conteudo-vazio">Carregando cartas…</p>';
    abrirModal(modalConteudo);

    try {
      const resposta = await Portal.api(`api/colecoes.php?id=${encodeURIComponent(colecaoResumo.id)}`);
      estado.aberta = resposta.colecao;
      await sincronizarConteudo(resposta.colecao.cartas || []);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      listaConteudo.innerHTML = '';
      Portal.mostrarAlerta(alertaConteudo, falha.message);
    }
  }

  formIncluir.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    Portal.esconderAlerta(alertaConteudo);
    Portal.limparErros(formIncluir);

    if (!estado.aberta) return;

    if (!selectCarta.value) {
      Portal.aplicarErros(formIncluir, { carta_id: 'Escolha a carta que entra na coleção.' });
      return;
    }

    Portal.ocupar(botaoIncluir, true, 'Incluindo…');

    try {
      const resposta = await Portal.api(
        `api/colecoes.php?id=${encodeURIComponent(estado.aberta.id)}&acao=cartas`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            carta_id: Number(selectCarta.value),
            quantidade: Number(formIncluir.elements.quantidade.value || 1),
          }),
        }
      );

      Portal.notificar(resposta.message || 'Carta incluída.');
      formIncluir.elements.quantidade.value = '1';
      await sincronizarConteudo(resposta.cartas || []);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      Portal.mostrarAlerta(alertaConteudo, falha.message);
      if (falha.campos) Portal.aplicarErros(formIncluir, falha.campos);
    } finally {
      Portal.ocupar(botaoIncluir, false);
    }
  });

  listaConteudo.addEventListener('click', async (evento) => {
    const botao = evento.target.closest('[data-tirar]');
    if (!botao || !estado.aberta) return;

    botao.disabled = true;

    try {
      const resposta = await Portal.api(
        `api/colecoes.php?id=${encodeURIComponent(estado.aberta.id)}` +
          `&acao=cartas&carta_id=${encodeURIComponent(botao.dataset.tirar)}`,
        { method: 'DELETE' }
      );

      Portal.notificar(resposta.message || 'Carta removida.');
      await sincronizarConteudo(resposta.cartas || []);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      botao.disabled = false;
      Portal.mostrarAlerta(alertaConteudo, falha.message);
    }
  });

  // ================================================================
  // Exclusão confirmada
  // ================================================================

  function pedirConfirmacao(colecao) {
    estado.exclusaoAlvo = colecao;
    document.getElementById('exclusao-nome').textContent = colecao.nome;

    abrirModal(modalExclusao);
    botaoConfirmarExclusao.focus();
  }

  botaoConfirmarExclusao.addEventListener('click', async () => {
    const colecao = estado.exclusaoAlvo;
    if (!colecao) return;

    Portal.ocupar(botaoConfirmarExclusao, true, 'Excluindo…');

    try {
      await Portal.api(`api/colecoes.php?id=${encodeURIComponent(colecao.id)}`, { method: 'DELETE' });
      fecharModal(modalExclusao);
      Portal.notificar(`“${colecao.nome}” foi excluída.`);
      estado.exclusaoAlvo = null;
      await carregarColecoes();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      fecharModal(modalExclusao);
      Portal.notificar(falha.message, 'erro');
    } finally {
      Portal.ocupar(botaoConfirmarExclusao, false);
    }
  });

  // ================================================================
  // Ligações da página
  // ================================================================

  grade.addEventListener('click', (evento) => {
    const botao = evento.target.closest('[data-abrir], [data-editar], [data-excluir]');
    if (!botao) return;

    const id = Number(botao.dataset.abrir || botao.dataset.editar || botao.dataset.excluir);
    const colecao = estado.colecoes.find((item) => item.id === id);
    if (!colecao) return;

    if (botao.dataset.abrir) abrirConteudo(colecao);
    else if (botao.dataset.editar) abrirFormulario(colecao);
    else pedirConfirmacao(colecao);
  });

  document.getElementById('botao-nova-colecao').addEventListener('click', () => abrirFormulario(null));

  /** Os dois grupos de abas se comportam igual, só mudam a chave do estado. */
  function ligarAbas(atributo, chave) {
    const abas = document.querySelectorAll(`.filter-tab[data-${atributo}]`);

    abas.forEach((aba) => {
      aba.addEventListener('click', () => {
        abas.forEach((outra) => outra.classList.remove('active'));
        aba.classList.add('active');
        estado[chave] = aba.dataset[atributo];
        carregarColecoes();
      });
    });
  }

  ligarAbas('game', 'jogo');
  ligarAbas('tipo', 'tipo');

  let temporizadorBusca = null;
  campoBusca.addEventListener('input', () => {
    window.clearTimeout(temporizadorBusca);
    temporizadorBusca = window.setTimeout(() => {
      estado.busca = campoBusca.value;
      carregarColecoes();
    }, 350);
  });

  carregarColecoes();
})();
