<?php
declare(strict_types=1);

/** Tela de login. Quem já tem sessão vai direto para o painel. */

require_once __DIR__ . '/config/sessao.php';

if (usuario_atual() !== null) {
    header('Location: dashboard.php');
    exit;
}

$sessaoExpirada = isset($_GET['expirada']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <?php $tituloDaPagina = 'Entrar'; require __DIR__ . '/partials/documento.php'; ?>
</head>
<body>
  <main class="auth-page">
    <a class="brand auth-brand" href="index.php">
      <span class="brand-mark">CV</span>
      <span>Card<span>Vault</span></span>
    </a>

    <section class="auth-panel">
      <p class="auth-kicker">PORTAL ADMINISTRATIVO</p>
      <h1>Gestão de cartas.</h1>
      <p class="auth-subtitle">Entre para cadastrar, editar e organizar as cartas do acervo.</p>

      <?php if ($sessaoExpirada): ?>
        <div class="alerta info" role="status" style="margin-bottom:16px">
          Sua sessão expirou por inatividade. Entre novamente para continuar.
        </div>
      <?php endif; ?>

      <div class="alerta erro" id="login-alerta" role="alert" hidden></div>

      <form id="login-form" class="auth-form" novalidate>
        <label class="field" for="email">
          <span>E-mail<span class="obrigatorio" aria-hidden="true">*</span></span>
          <input id="email" name="email" type="email" inputmode="email"
                 autocomplete="username" placeholder="admin@cards.com"
                 required aria-describedby="erro-email">
          <span class="field-error" id="erro-email" aria-live="polite"></span>
        </label>

        <label class="field" for="senha">
          <span>Senha<span class="obrigatorio" aria-hidden="true">*</span></span>
          <input id="senha" name="senha" type="password"
                 autocomplete="current-password" placeholder="••••••••"
                 required aria-describedby="erro-senha">
          <span class="field-error" id="erro-senha" aria-live="polite"></span>
        </label>

        <button class="primary-button full" type="submit" id="botao-entrar">
          <span class="rotulo">Entrar</span>
        </button>
      </form>

      <div class="alerta info" style="margin-top:20px">
        <span>
          <strong>Acesso de avaliação</strong><br>
          admin@cards.com &nbsp;·&nbsp; admin123password
        </span>
      </div>
    </section>

    <!-- A tela de login nao tem o cabecalho do painel, entao o botao de tema
         mora aqui: quem prefere escuro escolhe antes mesmo de entrar. -->
    <button class="tema-toggle tema-toggle-solto" id="botao-tema" type="button" aria-pressed="false">
      <span class="apenas-leitor">Tema escuro</span>
    </button>

    <p class="auth-footer">Liga Magic · portal administrativo de cartas.</p>
  </main>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script src="assets/js/tema.js"></script>
  <script src="assets/js/common.js"></script>
  <script src="assets/js/auth.js"></script>
</body>
</html>
