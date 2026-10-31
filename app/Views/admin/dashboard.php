<?php
$base = $config['base_url'] ?? '';
$q = $q ?? '';
$status = $status ?? 'pending';
$counts = $counts ?? ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$statuses = $statuses ?? [];
$pendingCount = (int) ($counts['pending'] ?? 0);

$tabs = [
    'pending'  => 'Новые',
    'approved' => 'Принятые',
    'rejected' => 'Отклонённые',
    'all'      => 'Все',
];

$statusHref = static function (string $key) use ($base, $q): string {
    $href = $base . '/admin?status=' . urlencode($key);
    if ($q !== '') {
        $href .= '&q=' . urlencode($q);
    }
    return $href;
};
?>

<section class="admin-dash">
  <header class="admin-dash-head">
    <div class="admin-dash-title">
      <h2>Заявки</h2>
      <p class="admin-dash-sub">
        <?= htmlspecialchars($adminName) ?>
        <?php if ($pendingCount > 0): ?>
          <span class="admin-dash-queue">· новых <?= $pendingCount ?></span>
        <?php endif; ?>
      </p>
    </div>

    <ul class="admin-dash-tabs" aria-label="Статус заявок">
      <?php foreach ($tabs as $key => $label): ?>
        <?php
          $cnt = (int) ($counts[$key] ?? 0);
          $on = $status === $key;
        ?>
        <li>
          <a class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"
             href="<?= htmlspecialchars($statusHref($key)) ?>"
             <?= $on ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($label) ?>
            <span class="pill-count <?= $on ? 'bg-white/20 text-white' : 'bg-wash text-muted' ?>"><?= $cnt ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="get" action="<?= htmlspecialchars($base) ?>/admin" class="admin-dash-search" role="search">
      <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <label class="sr-only" for="q">Поиск</label>
      <input class="field field-sm min-w-0 flex-1" id="q" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="ФИО / телефон" autocomplete="off">
      <button class="btn-primary-sm" type="submit">Найти</button>
      <?php if ($q !== ''): ?>
        <a class="btn-secondary-sm"
           href="<?= htmlspecialchars($base) ?>/admin?status=<?= urlencode($status) ?>">×</a>
      <?php endif; ?>
    </form>

    <nav class="admin-dash-nav" aria-label="Действия">
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin/users">Юзеры</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/register">+</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin/logout">Выйти</a>
    </nav>
  </header>

  <?php if (empty($participants)): ?>
    <div class="admin-empty panel">
      <p class="admin-empty-title">
        <?php if ($q !== ''): ?>
          Никого не найдено
        <?php elseif ($status === 'pending'): ?>
          Новых заявок нет
        <?php else: ?>
          Список пуст
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="table-ag admin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>ФИО</th>
            <th>Статус</th>
            <th>Пол</th>
            <th>Группа</th>
            <th>Г.р.</th>
            <th>С/вес</th>
            <th>Телефон</th>
            <th>Дисциплины</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $i => $p): ?>
            <?php
              $pid = (int) $p['id'];
              $st = $p['status'] ?? 'pending';
              $stLabel = $statuses[$st] ?? $st;
              $openHref = $base . '/admin/participants/' . $pid;
              $ageGroup = '';
              if (!empty($p['age_group'])) {
                  $ageGroup = $p['age_group'] === 'juniors' ? 'Юниоры' : 'Open';
              }
            ?>
            <tr class="admin-row--<?= htmlspecialchars($st) ?>">
              <td class="text-muted"><?= (int) $i + 1 ?></td>
              <td class="admin-td-name">
                <a href="<?= htmlspecialchars($openHref) ?>"><?= htmlspecialchars($p['full_name']) ?></a>
              </td>
              <td>
                <span class="badge-status badge-status--<?= htmlspecialchars($st) ?>">
                  <?= htmlspecialchars($stLabel) ?>
                </span>
              </td>
              <td><?= ($p['gender'] ?? '') === 'F' ? 'Ж' : 'М' ?></td>
              <td><?= $ageGroup !== '' ? htmlspecialchars($ageGroup) : '—' ?></td>
              <td><?= !empty($p['birth_year']) ? (int) $p['birth_year'] : '—' ?></td>
              <td class="whitespace-nowrap">
                <?= ($p['body_weight'] !== null && (float) $p['body_weight'] > 0)
                  ? number_format((float) $p['body_weight'], 2, '.', '')
                  : '—' ?>
              </td>
              <td class="whitespace-nowrap"><?= htmlspecialchars($p['phone'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['disciplines'] ?: '—') ?></td>
              <td class="admin-td-actions">
                <?php if ($st === 'pending'): ?>
                  <form method="post" action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>/status">
                    <input type="hidden" name="status" value="approved">
                    <input type="hidden" name="back" value="list">
                    <button class="btn-primary-sm" type="submit">OK</button>
                  </form>
                  <form method="post" action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>/status">
                    <input type="hidden" name="status" value="rejected">
                    <input type="hidden" name="back" value="list">
                    <button class="btn-danger-sm" type="submit">Нет</button>
                  </form>
                <?php endif; ?>
                <a class="btn-secondary-sm" href="<?= htmlspecialchars($openHref) ?>">→</a>
                <form method="post"
                      action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>/delete"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode("Удалить заявку #{$pid} ({$p['full_name']})?"), ENT_QUOTES, 'UTF-8') ?>);">
                  <button class="btn-danger-sm" type="submit" title="Удалить">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
