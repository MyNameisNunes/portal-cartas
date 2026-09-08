<?php
declare(strict_types=1);

/**
 * Cabecalho compartilhado pelas paginas do painel.
 *
 * Espera duas variaveis no escopo de quem inclui:
 *   $usuario      array da sessao (usuario_atual())
 *   $paginaAtual  chave do item de menu que fica marcado como ativo
 *
 * As tres paginas usam o mesmo markup para que a navegacao nao "pule" entre
 * elas e para que incluir uma categoria nova seja mexer em um arquivo so.
 */

require_once __DIR__ . '/../config/vista.php';

/** @var array{id:int,nome:string,email:string} $usuario */
/** @var string $paginaAtual */

$menu = [
    'painel'      => ['rotulo' => 'Painel',      'href' => 'dashboard.php'],
    'colecoes'    => ['rotulo' => 'Coleções',    'href' => 'colecoes.php'],
    'importacoes' => ['rotulo' => 'Importações', 'href' => 'importacoes.php'],
];
?>
<header class="site-header">
  <a class="brand" href="dashboard.php">
    <span class="brand-mark">CV</span>
    <span>Card<span>Vault</span></span>
  </a>

  <nav class="main-nav" aria-label="Navegação principal">
    <?php foreach ($menu as $chave => $item): ?>
      <a class="<?= $chave === $paginaAtual ? 'active' : '' ?>"
         href="<?= h($item['href']) ?>"
         <?= $chave === $paginaAtual ? 'aria-current="page"' : '' ?>><?= h($item['rotulo']) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="acoes-do-topo">
    <!-- O icone (sol/lua) e desenhado pelo CSS a partir do tema em vigor, entao
         o botao nao precisa de JS so para trocar de figura. O nome acessivel
         vem do texto escondido; o estado, do aria-pressed. -->
    <button class="tema-toggle" id="botao-tema" type="button" aria-pressed="false">
      <span class="apenas-leitor">Tema escuro</span>
    </button>

    <button class="profile-button" id="botao-sair" type="button">
      <span class="avatar" aria-hidden="true"><?= h(iniciais($usuario['nome'])) ?></span>
      <span class="profile-copy">
        <strong><?= h($usuario['nome']) ?></strong>
        <small>Sair do portal</small>
      </span>
      <span aria-hidden="true">⌄</span>
    </button>
  </div>
</header>
