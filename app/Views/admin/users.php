<?php
$base = $config['base_url'] ?? '';
$role = $role ?? 'admin';
$roles = $roles ?? [];
$users = $users ?? [];
$counts = $counts ?? [];
$adminId = (int) ($adminId ?? 0);
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-2">
  <div>
    <h2 class="mb-1 text-2xl font-semibold">Пользователи</h2>
    <p class="text-sm text-muted"><?= htmlspecialchars($adminName) ?></p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin">Заявки</a>
    <a class="btn-primary-sm"
       href="<?= htmlspecialchars($base) ?>/admin/users/new?role=<?= urlencode($role) ?>">+ Добавить</a>
  </div>
</div>

<ul class="mb-6 flex flex-wrap gap-2" aria-label="Роли">
  <?php foreach ($roles as $key => $label): ?>
    <?php
      $on = $role === $key;
      $cnt = (int) ($counts[$key] ?? 0);
    ?>
    <li>
      <a class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"
         href="<?= htmlspecialchars($base) ?>/admin/users?role=<?= urlencode($key) ?>">
        <?= htmlspecialchars($label) ?>
        <span class="pill-count <?= $on ? 'bg-white/20 text-white' : 'bg-wash text-muted' ?>"><?= $cnt ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (empty($users)): ?>
  <div class="panel">
    <p class="text-muted">Пользователей пока нет.</p>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="overflow-x-auto">
      <table class="table-ag">
        <thead>
          <tr>
            <th>#</th>
            <th>Имя</th>
            <th>Логин</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php
              $uid = (int) $u['id'];
              $isSelf = $role === 'admin' && $uid === $adminId;
            ?>
            <tr>
              <td class="text-muted"><?= $uid ?></td>
              <td class="font-semibold">
                <?= htmlspecialchars($u['display_name']) ?>
                <?php if ($isSelf): ?>
                  <span class="ml-1 text-sm font-normal text-muted">(вы)</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td class="whitespace-nowrap">
                <div class="flex flex-wrap items-center gap-1">
                  <a class="btn-secondary-sm"
                     href="<?= htmlspecialchars($base) ?>/admin/users/<?= htmlspecialchars($role) ?>/<?= $uid ?>">
                    Изменить
                  </a>
                  <?php if (!$isSelf): ?>
                    <form method="post"
                          action="<?= htmlspecialchars($base) ?>/admin/users/<?= htmlspecialchars($role) ?>/<?= $uid ?>/delete"
                          onsubmit="return confirm(<?= htmlspecialchars(json_encode('Удалить пользователя «' . $u['username'] . '»?'), ENT_QUOTES, 'UTF-8') ?>);">
                      <button class="btn-danger-sm" type="submit">Удалить</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
