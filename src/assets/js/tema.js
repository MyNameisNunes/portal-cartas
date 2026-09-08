'use strict';

/**
 * Tema claro/escuro.
 *
 * Regras:
 *   - sem escolha salva, vale o tema do sistema (prefers-color-scheme);
 *   - ao clicar, a escolha vira explicita e passa a valer em todas as paginas;
 *   - a escolha explicita continua valendo mesmo que o sistema mude depois.
 *
 * Quem aplica o tema na primeira pintura e o script inline do <head>; aqui
 * cuidamos do botao e das trocas em tempo real. O localStorage e a fonte da
 * verdade sobre existir ou nao uma escolha explicita — o data-tema sozinho
 * nao serve, porque ele esta sempre preenchido.
 */
(() => {
  const CHAVE = 'portal-tema';
  const raiz = document.documentElement;
  const botao = document.getElementById('botao-tema');
  const meta = document.getElementById('meta-tema');

  /* A barra do navegador acompanha o topo da pagina: vermelho da marca no
     claro, o fundo quase preto no escuro. */
  const COR_DA_BARRA = { claro: '#d81f2a', escuro: '#141013' };

  const sistemaEscuro = window.matchMedia('(prefers-color-scheme: dark)');

  /** A escolha explicita gravada, ou null enquanto ninguem escolheu. */
  function escolhaSalva() {
    try {
      const valor = localStorage.getItem(CHAVE);
      return valor === 'claro' || valor === 'escuro' ? valor : null;
    } catch (erro) {
      return null;
    }
  }

  function aplicar(tema) {
    raiz.dataset.tema = tema;

    if (meta) meta.setAttribute('content', COR_DA_BARRA[tema]);
    if (!botao) return;

    // O nome acessivel do botao e fixo ("Tema escuro"); quem descreve o estado
    // e o aria-pressed. O title repete a acao para quem usa o mouse.
    botao.setAttribute('aria-pressed', String(tema === 'escuro'));
    botao.title = tema === 'escuro' ? 'Voltar ao tema claro' : 'Mudar para o tema escuro';
  }

  if (botao) {
    botao.addEventListener('click', () => {
      const proximo = raiz.dataset.tema === 'escuro' ? 'claro' : 'escuro';

      try {
        localStorage.setItem(CHAVE, proximo);
      } catch (erro) {
        /* Sem persistencia: o tema vale so nesta aba, o que e melhor que nada. */
      }

      aplicar(proximo);
    });
  }

  // Enquanto ninguem escolheu, acompanhar o sistema em tempo real.
  sistemaEscuro.addEventListener('change', (evento) => {
    if (!escolhaSalva()) aplicar(evento.matches ? 'escuro' : 'claro');
  });

  aplicar(raiz.dataset.tema === 'escuro' ? 'escuro' : 'claro');
})();
