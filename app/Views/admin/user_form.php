<?php
$base = $config['base_url'] ?? '';
$mode = $mode ?? 'create';
$role = $role ?? 'admin';
$roles = $roles ?? [];
$user = $user ?? [];
$isEdit = $mode === 'edit';
$uid = $isEdit ? (int) ($user['id'] ?? 0) : 0;
$roleLabel = $roles[$role] ?? $role;
$action = $isEdit
    ? $base . '/admin/users/' . $role . '/' . $uid
    : $base . '/admin/users';
$title = $isEdit ? 'Изменить пользователя' : 'Новый пользователь';
?>

<div class="mx-auto w-full max-w-lg">
  <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
      <h1 class="mb-1 text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
      <p class="text-sm text-muted">
        <?= htmlspecialchars($adminName) ?> · <?= htmlspecialchars($roleLabel) ?>
      </p>
    </div>
    <a class="btn-secondary-sm"
       href="<?= htmlspecialchars($base) ?>/admin/users?role=<?= urlencode($role) ?>">К списку</a>
  </div>

  <form method="post" action="<?= htmlspecialchars($action) ?>" autocomplete="off" novalidate>
    <?php if (!$isEdit): ?>
      <div class="card mb-4">
        <div class="card-body">
          <fieldset>
            <legend class="field-label">Роль</legend>
            <div class="flex flex-wrap gap-4">
              <?php foreach ($roles as $key => $label): ?>
                <div class="flex items-center gap-2">
                  <input class="check" type="radio" name="role" id="role_<?= htmlspecialchars($key) ?>"
                         value="<?= htmlspecialchars($key) ?>"
                         <?= $role === $key ? 'checked' : '' ?>>
                  <label for="role_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </div>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-body">
        <div class="mb-4">
          <label class="field-label" for="display_name">Отображаемое имя</label>
          <input class="field w-full" id="display_name" name="display_name" required
                 value="<?= htmlspecialchars($user['display_name'] ?? '') ?>"
                 placeholder="Иван Иванов">
        </div>
        <div class="mb-4">
          <label class="field-label" for="username">Логин</label>
          <input class="field w-full" id="username" name="username" required
                 autocomplete="off"
                 pattern="[a-zA-Z0-9._\-]{3,50}"
                 value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                 placeholder="ivan">
          <p class="field-hint">Латиница, цифры, точка, _ или - (3–50 символов)</p>
        </div>
        <div class="mb-4">
          <label class="field-label" for="password">
            <?= $isEdit ? 'Новый пароль' : 'Пароль' ?>
          </label>
          <input class="field w-full" id="password" name="password" type="password"
                 autocomplete="new-password"
                 <?= $isEdit ? '' : 'required' ?>
                 minlength="6"
                 placeholder="<?= $isEdit ? 'Оставьте пустым, чтобы не менять' : 'минимум 6 символов' ?>">
        </div>
        <div>
          <label class="field-label" for="password_confirm">
            <?= $isEdit ? 'Повтор нового пароля' : 'Повтор пароля' ?>
          </label>
          <input class="field w-full" id="password_confirm" name="password_confirm" type="password"
                 autocomplete="new-password"
                 <?= $isEdit ? '' : 'required' ?>
                 minlength="6">
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <a class="btn-secondary"
         href="<?= htmlspecialchars($base) ?>/admin/users?role=<?= urlencode($role) ?>">Отмена</a>
      <button class="btn-primary" type="submit">
        <?= $isEdit ? 'Сохранить' : 'Создать' ?>
      </button>
    </div>
  </form>

  <?php if ($isEdit): ?>
    <?php $isSelf = $role === 'admin' && $uid === (int) ($adminId ?? 0); ?>
    <?php if (!$isSelf): ?>
      <section class="card mt-6">
        <div class="card-body">
          <h2 class="mb-2 text-lg font-semibold">Удаление</h2>
          <p class="mb-4 text-sm text-muted">Аккаунт будет удалён без возможности восстановления.</p>
          <form method="post"
                action="<?= htmlspecialchars($base) ?>/admin/users/<?= htmlspecialchars($role) ?>/<?= $uid ?>/delete"
                onsubmit="return confirm(<?= htmlspecialchars(json_encode('Удалить пользователя «' . ($user['username'] ?? '') . '»?'), ENT_QUOTES, 'UTF-8') ?>);">
            <button class="btn-danger" type="submit">Удалить пользователя</button>
          </form>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
