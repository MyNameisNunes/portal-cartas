'use strict';

/** Tela de login: valida no cliente, autentica e leva para o painel. */
(() => {
  const formulario = document.getElementById('login-form');
  if (!formulario) return;

  const alerta = document.getElementById('login-alerta');
  const botao = document.getElementById('botao-entrar');

  const EMAIL_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /** Erros de preenchimento somem assim que o usuário corrige o campo. */
  formulario.addEventListener('input', (evento) => {
    const campo = evento.target;
    if (!campo.hasAttribute('aria-invalid')) return;

    campo.removeAttribute('aria-invalid');
    const destino = document.getElementById(`erro-${campo.name}`);
    if (destino) destino.textContent = '';
  });

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    Portal.esconderAlerta(alerta);
    Portal.limparErros(formulario);

    const email = formulario.email.value.trim();
    const senha = formulario.senha.value;

    const problemas = {};
    if (!email) {
      problemas.email = 'Informe seu e-mail.';
    } else if (!EMAIL_VALIDO.test(email)) {
      problemas.email = 'Informe um e-mail válido, como admin@cards.com.';
    }
    if (!senha) {
      problemas.senha = 'Informe sua senha.';
    }

    if (Object.keys(problemas).length > 0) {
      Portal.aplicarErros(formulario, problemas);
      return;
    }

    Portal.ocupar(botao, true, 'Entrando…');

    try {
      await Portal.api('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, senha }),
      });

      // Sucesso: o botão continua desabilitado de propósito, para não
      // permitir um segundo envio durante o redirecionamento.
      window.location.href = 'dashboard.php';
    } catch (falha) {
      Portal.ocupar(botao, false);
      Portal.mostrarAlerta(alerta, falha.message);

      if (falha.status === 401) {
        formulario.senha.value = '';
        formulario.senha.focus();
      }
    }
  });
})();
