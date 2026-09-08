<?php
declare(strict_types=1);

/**
 * <head> compartilhado pelas quatro paginas.
 *
 * Antes cada pagina repetia meta, links de fonte e folha de estilo. Isso ja
 * incomodava para manter (quatro lugares para trocar uma familia tipografica)
 * e passou a ser um risco com o tema escuro: o script que aplica o tema salvo
 * precisa rodar antes da primeira pintura em TODAS as paginas, senao a tela
 * pisca clara por um quadro antes de escurecer.
 *
 * Espera uma variavel no escopo de quem inclui:
 *   $tituloDaPagina  texto que vai no <title>, sem o sufixo do portal
 */

require_once __DIR__ . '/../config/vista.php';

/** @var string $tituloDaPagina */
$titulo = $tituloDaPagina ?? 'Portal';

/**
 * Fontes do portal, declaradas num lugar so:
 *   Space Grotesk  titulos, numeros e rotulos curtos
 *   DM Sans        texto corrido, campos e botoes
 * Os pesos sao exatamente os que a folha de estilo usa — pedir mais
 * peso do que se usa custa download a toa.
 */
const FONTES_DO_PORTAL = 'https://fonts.googleapis.com/css2'
    . '?family=DM+Sans:wght@400;500;600;700'
    . '&family=Space+Grotesk:wght@500;600;700'
    . '&display=swap';
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#d81f2a" id="meta-tema">
  <title><?= h($titulo) ?> | Portal de Cartas</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= h(FONTES_DO_PORTAL) ?>">
  <link rel="stylesheet" href="assets/css/style.css">

  <script>
    /* Tema aplicado antes da primeira pintura. Fica inline de proposito: um
       arquivo externo so carregaria depois do CSS e a tela piscaria clara
       antes de escurecer.

       Este script e o unico lugar que decide o tema — a folha de estilo so
       olha para html[data-tema="escuro"]. Foi o que evitou repetir a lista
       inteira de tokens dentro de uma media query de prefers-color-scheme. */
    (function () {
      var escolhido = null;

      try {
        escolhido = localStorage.getItem('portal-tema');
      } catch (erro) {
        /* localStorage bloqueado (aba anonima, cookies desligados): segue o sistema. */
      }

      document.documentElement.dataset.tema =
        (escolhido === 'claro' || escolhido === 'escuro')
          ? escolhido
          : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro');
    })();
  </script>
