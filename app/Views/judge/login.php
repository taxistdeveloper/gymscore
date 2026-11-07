<?php $base = $config['base_url'] ?? ''; ?>

<section class="auth-login" style="--auth-login-image: url('<?= htmlspecialchars($base) ?>/img/home-barbell-hero.jpg')">
  <div class="auth-login-veil" aria-hidden="true"></div>
  <div class="auth-login-panel">
    <a class="auth-login-brand" href="<?= htmlspecialchars($base) ?>/">
      <img class="auth-login-logo"
           src="<?= htmlspecialchars($base) ?>/img/alash-gym-logo.png"
           width="505"
           height="116"
           alt="Alash Gym">
    </a>
    <p class="kicker mb-3">Судейская</p>
    <div class="card card-accent">
      <div class="card-body">
        <h1 class="mb-1 text-xl font-semibold">Вход судьи</h1>
        <p class="mb-4 text-sm text-muted">Помост · взвешивание · протокол · арм</p>
        <form method="post" action="<?= htmlspecialchars($base) ?>/judge/login">
          <div class="mb-4">
            <label class="field-label" for="username">Логин</label>
            <input class="field w-full" id="username" name="username" required autocomplete="username" autofocus>
          </div>
          <div class="mb-4">
            <label class="field-label" for="password">Пароль</label>
            <input class="field w-full" id="password" name="password" type="password" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn-primary w-full">Войти</button>
        </form>
      </div>
    </div>
  </div>
</section>
