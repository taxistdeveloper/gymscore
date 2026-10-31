<?php $base = $config['base_url'] ?? ''; ?>

<section class="auth-login" style="--auth-login-image: url('<?= htmlspecialchars($base) ?>/img/home-barbell-hero.jpg')">
  <div class="auth-login-veil" aria-hidden="true"></div>
  <div class="auth-login-panel">
    <a class="auth-login-brand" href="<?= htmlspecialchars($base) ?>/">
      <span class="auth-login-brand-mark" aria-hidden="true"></span>
      Alash Gym
    </a>
    <p class="kicker mb-3">Админка</p>
    <div class="card card-accent">
      <div class="card-body">
        <h1 class="mb-4 text-xl font-semibold">Вход администратора</h1>
        <form method="post" action="<?= htmlspecialchars($base) ?>/admin/login">
          <div class="mb-4">
            <label class="field-label" for="username">Логин</label>
            <input class="field w-full" id="username" name="username" value="admin" required autocomplete="username">
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
