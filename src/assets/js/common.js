'use strict';

/**
 * Helpers compartilhados por todas as páginas do portal.
 * JavaScript puro, sem dependências externas.
 */
const Portal = (() => {
  const toast = document.getElementById('toast');
  let temporizadorToast = null;

  /** Escapa texto antes de interpolar em HTML. */
  function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[caractere]));
  }

  /** "Ultra Rara" -> "ultra-rara". Usado nos badges de raridade. */
  function slug(valor) {
    return String(valor ?? '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  /** Aviso temporário no canto da tela. */
  function notificar(mensagem, tipo = 'ok') {
    if (!toast) return;

    toast.textContent = mensagem;
    toast.className = `toast visible${tipo === 'erro' ? ' error' : ''}`;

    window.clearTimeout(temporizadorToast);
    temporizadorToast = window.setTimeout(() => {
      toast.className = 'toast';
    }, 3600);
  }

  /**
   * Marca um botão como ocupado: desabilita, aplica aria-busy (o CSS
   * desenha o spinner) e troca o rótulo. É o que impede o clique duplo
   * enquanto a requisição está no ar.
   */
  function ocupar(botao, ocupado, rotuloOcupado) {
    if (!botao) return;

    const rotulo = botao.querySelector('.rotulo');

    if (ocupado) {
      if (rotulo) {
        botao.dataset.rotuloOriginal = rotulo.textContent;
        if (rotuloOcupado) rotulo.textContent = rotuloOcupado;
      }
      botao.setAttribute('aria-busy', 'true');
      botao.disabled = true;
      return;
    }

    if (rotulo && botao.dataset.rotuloOriginal) {
      rotulo.textContent = botao.dataset.rotuloOriginal;
      delete botao.dataset.rotuloOriginal;
    }
    botao.removeAttribute('aria-busy');
    botao.disabled = false;
  }

  /**
   * Wrapper de fetch que normaliza a resposta da API.
   * Erros viram exceções com .status e .campos (erros por campo do formulário).
   */
  async function api(caminho, opcoes = {}) {
    const resposta = await fetch(caminho, { credentials: 'same-origin', ...opcoes });

    let corpo = null;
    if (resposta.status !== 204) {
      try {
        corpo = await resposta.json();
      } catch (erroDeParse) {
        corpo = null;
      }
    }

    if (!resposta.ok) {
      const falha = new Error(
        (corpo && corpo.error) || 'Não foi possível concluir a operação. Tente novamente.'
      );
      falha.status = resposta.status;
      falha.campos = (corpo && corpo.campos) || null;
      throw falha;
    }

    return corpo;
  }

  function mostrarAlerta(elemento, mensagem, tipo = 'erro') {
    if (!elemento) return;
    elemento.textContent = mensagem;
    elemento.className = `alerta ${tipo}`;
    elemento.hidden = false;
  }

  function esconderAlerta(elemento) {
    if (!elemento) return;
    elemento.hidden = true;
    elemento.textContent = '';
  }

  /** Limpa marcações de erro de todos os campos do formulário. */
  function limparErros(formulario) {
    if (!formulario) return;

    formulario.querySelectorAll('[aria-invalid="true"]').forEach((campo) => {
      campo.removeAttribute('aria-invalid');
    });
    formulario.querySelectorAll('.field-error').forEach((elemento) => {
      elemento.textContent = '';
    });
  }

  /** Aplica mensagens de erro por campo e foca o primeiro problema. */
  function aplicarErros(formulario, campos) {
    if (!formulario || !campos) return;

    let primeiro = null;

    Object.entries(campos).forEach(([nome, mensagem]) => {
      const campo = formulario.elements[nome];
      const destino = document.getElementById(`erro-${nome}`);

      if (campo && typeof campo.setAttribute === 'function') {
        campo.setAttribute('aria-invalid', 'true');
        if (!primeiro) primeiro = campo;
      }
      if (destino) destino.textContent = mensagem;
    });

    if (primeiro && typeof primeiro.focus === 'function') {
      primeiro.focus();
    }
  }

  /** Volta para o login preservando o aviso de sessão expirada. */
  function voltarParaLogin() {
    window.location.href = 'index.php?expirada=1';
  }

  /**
   * Trata 401 de forma centralizada: a sessão morreu, então não adianta
   * mostrar erro na tela — o destino é o login.
   */
  function sessaoExpirou(falha) {
    if (falha.status !== 401) return false;
    voltarParaLogin();
    return true;
  }

  // O botão de sair vem do header compartilhado, então o vínculo é feito aqui
  // uma vez só, em vez de repetido no script de cada página.
  const botaoSair = document.getElementById('botao-sair');

  if (botaoSair) {
    botaoSair.addEventListener('click', async () => {
      try {
        await api('api/logout.php', { method: 'POST' });
      } catch (falha) {
        // Mesmo se a chamada falhar, o destino é o login.
      }
      window.location.href = 'index.php';
    });
  }

  return {
    api,
    aplicarErros,
    escaparHtml,
    esconderAlerta,
    limparErros,
    mostrarAlerta,
    notificar,
    ocupar,
    sessaoExpirou,
    slug,
    voltarParaLogin,
  };
})();
