'use strict';

/**
 * Painel de cartas: listagem, filtros, formulário de inclusão/edição
 * (com o select de edições dependente do card game) e exclusão confirmada.
 */
(() => {
  const estado = {
    cartas: [],
    jogo: 'all',
    busca: '',
    primeiraCarga: true,
    exclusaoAlvo: null,
  };

  const grade = document.getElementById('grade-cartas');
  const status = document.getElementById('catalogo-status');
  const esqueleto = document.getElementById('catalogo-carregando');
  const campoBusca = document.getElementById('busca');

  const modalCarta = document.getElementById('modal-carta');
  const modalExclusao = document.getElementById('modal-exclusao');
  const formulario = document.getElementById('form-carta');
  const alertaFormulario = document.getElementById('form-alerta');

  const selectJogo = document.getElementById('card_game');
  const selectEdicao = document.getElementById('edicao_id');
  const edicaoWrap = document.getElementById('edicao-wrap');
  const edicaoRetry = document.getElementById('edicao-retry');

  const campoArquivo = document.getElementById('imagem');
  const campoUrlImagem = document.getElementById('imagem_url');
  const previa = document.getElementById('imagem-previa');
  const botaoRemoverImagem = document.getElementById('botao-remover-imagem');
  const campoRemoverImagem = document.getElementById('remover_imagem');

  const botaoSalvar = document.getElementById('botao-salvar');
  const botaoConfirmarExclusao = document.getElementById('botao-confirmar-exclusao');

  const SEM_IMAGEM = '<span>Sem imagem</span>';

  let elementoFocadoAntesDoModal = null;

  // ================================================================
  // Listagem
  // ================================================================

  function mostrarStatus(titulo, descricao) {
    status.innerHTML = `<strong>${Portal.escaparHtml(titulo)}</strong>${Portal.escaparHtml(descricao)}`;
    status.hidden = false;
  }

  /** "Blue-Eyes White Dragon" -> "BW". Usado no placeholder de carta sem imagem. */
  function iniciaisDaCarta(nome) {
    return String(nome || '?')
      .split(/[\s,.-]+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((parte) => parte.charAt(0))
      .join('')
      .toUpperCase();
  }

  function cartaHtml(carta) {
    // O placeholder fica sempre no DOM, atrás da imagem. Se a carta não tem
    // imagem, ou se a URL externa falhar, ele é o que aparece — em vez de uma
    // caixa cinza vazia, que passa impressão de defeito.
    const placeholder = `<span class="card-sem-imagem" aria-hidden="true">${Portal.escaparHtml(iniciaisDaCarta(carta.nome_en))}</span>`;

    const imagem = carta.imagem_url
      ? `<img src="${Portal.escaparHtml(carta.imagem_url)}" alt="Imagem de ${Portal.escaparHtml(carta.nome_en)}" loading="lazy">`
      : '';

    const nomePt = carta.nome_pt
      ? `<span class="card-nome-pt">${Portal.escaparHtml(carta.nome_pt)}</span>`
      : '';

    return `
      <article class="card">
        <div class="card-image">
          ${placeholder}${imagem}
          <span class="rarity" data-raridade="${Portal.escaparHtml(Portal.slug(carta.raridade))}">${Portal.escaparHtml(carta.raridade)}</span>
        </div>
        <div class="card-body">
          <p class="card-game">${Portal.escaparHtml(carta.card_game_nome)}</p>
          <h3 title="${Portal.escaparHtml(carta.nome_en)}">${Portal.escaparHtml(carta.nome_en)}</h3>
          ${nomePt}
          <span class="card-edition">${Portal.escaparHtml(carta.edicao_nome)}</span>
          <div class="card-actions">
            <button type="button" data-editar="${carta.id}">Editar</button>
            <button type="button" data-excluir="${carta.id}">Excluir</button>
          </div>
        </div>
      </article>`;
  }

  function renderizar() {
    if (estado.cartas.length === 0) {
      grade.innerHTML = '';
      const filtrando = estado.jogo !== 'all' || estado.busca.trim() !== '';
      mostrarStatus(
        filtrando ? 'Nenhuma carta encontrada' : 'Seu acervo está vazio',
        filtrando
          ? 'Ajuste a busca ou escolha outro card game no filtro.'
          : 'Cadastre a primeira carta usando o botão “Nova carta”.'
      );
      return;
    }

    status.hidden = true;
    grade.innerHTML = estado.cartas.map(cartaHtml).join('');

    // URLs externas podem estar quebradas: cai para o placeholder.
    grade.querySelectorAll('img').forEach((imagem) => {
      imagem.addEventListener('error', () => imagem.remove(), { once: true });
    });
  }

  function atualizarIndicadores(resumo) {
    if (!resumo) return;
    document.getElementById('stat-total').textContent = resumo.total;
    document.getElementById('stat-jogos').textContent = resumo.jogos;
    document.getElementById('stat-edicoes').textContent = resumo.edicoes;
  }

  async function carregarCartas() {
    // O esqueleto só aparece na primeira carga; refiltros mantêm a lista
    // visível para a tela não "piscar" a cada tecla digitada.
    if (estado.primeiraCarga) {
      esqueleto.hidden = false;
      grade.hidden = true;
    } else {
      grade.style.opacity = '0.55';
    }
    status.hidden = true;

    const parametros = new URLSearchParams({ limit: '100' });
    if (estado.jogo !== 'all') parametros.set('game', estado.jogo);
    if (estado.busca.trim() !== '') parametros.set('search', estado.busca.trim());

    try {
      const resposta = await Portal.api(`api/cartas.php?${parametros.toString()}`);
      estado.cartas = resposta.cartas || [];
      renderizar();
      atualizarIndicadores(resposta.resumo);
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      grade.innerHTML = '';
      mostrarStatus('Não foi possível carregar as cartas', falha.message);
    } finally {
      estado.primeiraCarga = false;
      esqueleto.hidden = true;
      grade.hidden = false;
      grade.style.opacity = '';
    }
  }

  // ================================================================
  // Select de edições — o estado de carregamento é o ponto central
  // ================================================================

  const cacheEdicoes = new Map();
  let controladorEdicoes = null;

  function definirOpcoes(opcoes) {
    selectEdicao.innerHTML = opcoes
      .map((opcao) => `<option value="${Portal.escaparHtml(opcao.valor)}">${Portal.escaparHtml(opcao.texto)}</option>`)
      .join('');
  }

  function preencherEdicoes(edicoes, idSelecionado) {
    edicaoWrap.dataset.estado = 'pronto';
    definirOpcoes([
      { valor: '', texto: 'Selecione a edição' },
      ...edicoes.map((edicao) => ({ valor: edicao.id, texto: edicao.name })),
    ]);
    selectEdicao.disabled = false;
    selectEdicao.value = idSelecionado || '';
  }

  /**
   * Busca as edições do card game escolhido.
   *
   * 1. sem jogo -> select desabilitado com instrução
   * 2. com jogo -> desabilita, mostra "Carregando edições…" e o spinner
   * 3. resposta -> popula e reabilita
   * 4. falha    -> estado de erro com botão de nova tentativa
   *
   * Uma troca rápida de card game aborta a requisição anterior, para que uma
   * resposta atrasada não sobrescreva a lista do jogo escolhido por último.
   */
  async function carregarEdicoes(jogo, idSelecionado = '') {
    if (controladorEdicoes) controladorEdicoes.abort();

    edicaoRetry.hidden = true;
    selectEdicao.removeAttribute('aria-invalid');
    document.getElementById('erro-edicao_id').textContent = '';

    if (!jogo) {
      edicaoWrap.dataset.estado = 'vazio';
      selectEdicao.disabled = true;
      definirOpcoes([{ valor: '', texto: 'Selecione o card game primeiro' }]);
      return;
    }

    if (cacheEdicoes.has(jogo)) {
      preencherEdicoes(cacheEdicoes.get(jogo), idSelecionado);
      return;
    }

    edicaoWrap.dataset.estado = 'carregando';
    selectEdicao.disabled = true;
    definirOpcoes([{ valor: '', texto: 'Carregando edições…' }]);

    const controlador = new AbortController();
    controladorEdicoes = controlador;

    try {
      const resposta = await Portal.api(
        `api/edicoes.php?game=${encodeURIComponent(jogo)}`,
        { signal: controlador.signal }
      );

      if (controlador !== controladorEdicoes) return; // resposta obsoleta

      const edicoes = resposta.edicoes || [];
      cacheEdicoes.set(jogo, edicoes);
      preencherEdicoes(edicoes, idSelecionado);
    } catch (falha) {
      if (falha.name === 'AbortError') return;
      if (Portal.sessaoExpirou(falha)) return;

      edicaoWrap.dataset.estado = 'erro';
      selectEdicao.disabled = true;
      definirOpcoes([{ valor: '', texto: 'Não foi possível carregar as edições' }]);
      edicaoRetry.hidden = false;
      edicaoRetry.dataset.jogo = jogo;
    } finally {
      if (controlador === controladorEdicoes) controladorEdicoes = null;
    }
  }

  selectJogo.addEventListener('change', () => {
    // Trocar o card game descarta a edição escolhida antes e refaz a busca.
    carregarEdicoes(selectJogo.value, '');
  });

  edicaoRetry.addEventListener('click', () => {
    cacheEdicoes.delete(edicaoRetry.dataset.jogo);
    carregarEdicoes(selectJogo.value, '');
  });

  // ================================================================
  // Pré-visualização da imagem
  // ================================================================

  function definirPrevia(origem) {
    if (!origem) {
      previa.innerHTML = SEM_IMAGEM;
      botaoRemoverImagem.hidden = true;
      return;
    }

    previa.innerHTML = `<img src="${Portal.escaparHtml(origem)}" alt="Pré-visualização da carta">`;
    previa.querySelector('img').addEventListener('error', () => {
      previa.innerHTML = '<span>Imagem indisponível</span>';
    }, { once: true });
    botaoRemoverImagem.hidden = false;
  }

  campoArquivo.addEventListener('change', () => {
    const arquivo = campoArquivo.files && campoArquivo.files[0];
    if (!arquivo) return;

    if (arquivo.size > 5 * 1024 * 1024) {
      Portal.aplicarErros(formulario, { imagem_url: 'A imagem deve ter no máximo 5 MB.' });
      campoArquivo.value = '';
      return;
    }

    campoUrlImagem.value = '';
    campoRemoverImagem.value = '';
    definirPrevia(URL.createObjectURL(arquivo));
  });

  campoUrlImagem.addEventListener('change', () => {
    const url = campoUrlImagem.value.trim();
    if (!url) return;
    campoArquivo.value = '';
    campoRemoverImagem.value = '';
    definirPrevia(url);
  });

  botaoRemoverImagem.addEventListener('click', () => {
    campoArquivo.value = '';
    campoUrlImagem.value = '';
    campoRemoverImagem.value = '1';
    definirPrevia(null);
  });

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

  function abrirFormulario(carta) {
    formulario.reset();
    Portal.limparErros(formulario);
    Portal.esconderAlerta(alertaFormulario);
    campoRemoverImagem.value = '';
    definirPrevia(null);

    const kicker = document.getElementById('modal-carta-kicker');
    const titulo = document.getElementById('modal-carta-titulo');

    if (carta) {
      kicker.textContent = 'EDITAR CARTA';
      titulo.textContent = 'Atualize os detalhes.';

      formulario.elements.id.value = carta.id;
      formulario.elements.nome_en.value = carta.nome_en || '';
      formulario.elements.nome_pt.value = carta.nome_pt || '';
      formulario.elements.card_game.value = carta.card_game;
      formulario.elements.raridade.value = carta.raridade || '';

      if (carta.imagem_url) {
        // Uploads locais não voltam para o campo de URL; a prévia já mostra
        // a imagem atual e ela é preservada se nada for alterado.
        if (!carta.imagem_url.startsWith('uploads/')) {
          formulario.elements.imagem_url.value = carta.imagem_url;
        }
        definirPrevia(carta.imagem_url);
      }

      carregarEdicoes(carta.card_game, carta.edicao_id);
    } else {
      kicker.textContent = 'NOVA CARTA';
      titulo.textContent = 'Adicione ao acervo.';
      formulario.elements.id.value = '';
      carregarEdicoes('', '');
    }

    abrirModal(modalCarta);
    formulario.elements.nome_en.focus();
  }

  document.querySelectorAll('[data-fechar-modal]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalCarta));
  });

  document.querySelectorAll('[data-fechar-exclusao]').forEach((botao) => {
    botao.addEventListener('click', () => fecharModal(modalExclusao));
  });

  [modalCarta, modalExclusao].forEach((modal) => {
    modal.addEventListener('mousedown', (evento) => {
      if (evento.target === modal) fecharModal(modal);
    });
  });

  document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') return;
    if (!modalExclusao.hidden) fecharModal(modalExclusao);
    else if (!modalCarta.hidden) fecharModal(modalCarta);
  });

  // ================================================================
  // Salvar (inclusão e edição)
  // ================================================================

  function validarFormulario() {
    const problemas = {};

    if (!formulario.elements.nome_en.value.trim()) {
      problemas.nome_en = 'Informe o nome da carta em inglês.';
    }
    if (!formulario.elements.card_game.value) {
      problemas.card_game = 'Selecione o card game.';
    }
    if (!formulario.elements.edicao_id.value) {
      problemas.edicao_id = selectEdicao.disabled
        ? 'Escolha o card game para carregar as edições.'
        : 'Selecione a edição da carta.';
    }
    if (!formulario.elements.raridade.value) {
      problemas.raridade = 'Selecione a raridade.';
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
    const dados = new FormData(formulario);

    // multipart não funciona em um PUT real no PHP, então a edição vai como
    // POST + _method=PUT, que o backend reconhece.
    let caminho = 'api/cartas.php';
    if (id) {
      dados.set('_method', 'PUT');
      caminho += `?id=${encodeURIComponent(id)}`;
    }

    Portal.ocupar(botaoSalvar, true, id ? 'Salvando…' : 'Cadastrando…');

    try {
      const resposta = await Portal.api(caminho, { method: 'POST', body: dados });
      fecharModal(modalCarta);
      Portal.notificar(resposta.message || 'Carta salva com sucesso.');
      await carregarCartas();
    } catch (falha) {
      if (Portal.sessaoExpirou(falha)) return;
      Portal.mostrarAlerta(alertaFormulario, falha.message);
      if (falha.campos) Portal.aplicarErros(formulario, falha.campos);
    } finally {
      Portal.ocupar(botaoSalvar, false);
    }
  });

  // ================================================================
  // Exclusão confirmada
  // ================================================================

  function pedirConfirmacao(carta) {
    estado.exclusaoAlvo = carta;
    document.getElementById('exclusao-nome').textContent = carta.nome_pt
      ? `${carta.nome_en} (${carta.nome_pt})`
      : carta.nome_en;

    abrirModal(modalExclusao);
    botaoConfirmarExclusao.focus();
  }

  botaoConfirmarExclusao.addEventListener('click', async () => {
    const carta = estado.exclusaoAlvo;
    if (!carta) return;

    Portal.ocupar(botaoConfirmarExclusao, true, 'Excluindo…');

    try {
      await Portal.api(`api/cartas.php?id=${encodeURIComponent(carta.id)}`, { method: 'DELETE' });
      fecharModal(modalExclusao);
      Portal.notificar(`“${carta.nome_en}” foi excluída do acervo.`);
      estado.exclusaoAlvo = null;
      await carregarCartas();
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
    const botao = evento.target.closest('[data-editar], [data-excluir]');
    if (!botao) return;

    const id = Number(botao.dataset.editar || botao.dataset.excluir);
    const carta = estado.cartas.find((item) => item.id === id);
    if (!carta) return;

    if (botao.dataset.editar) abrirFormulario(carta);
    else pedirConfirmacao(carta);
  });

  document.getElementById('botao-nova-carta').addEventListener('click', () => abrirFormulario(null));

  document.querySelectorAll('.filter-tab').forEach((aba) => {
    aba.addEventListener('click', () => {
      document.querySelectorAll('.filter-tab').forEach((outra) => outra.classList.remove('active'));
      aba.classList.add('active');
      estado.jogo = aba.dataset.game;
      carregarCartas();
    });
  });

  let temporizadorBusca = null;
  campoBusca.addEventListener('input', () => {
    window.clearTimeout(temporizadorBusca);
    temporizadorBusca = window.setTimeout(() => {
      estado.busca = campoBusca.value;
      carregarCartas();
    }, 350);
  });

  carregarCartas();
})();
