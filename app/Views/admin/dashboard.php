<?php
$base = $config['base_url'] ?? '';
$q = $q ?? '';
$status = $status ?? 'pending';
$counts = $counts ?? ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$statuses = $statuses ?? [];
$groups = $groups ?? [];
$listedCount = (int) ($listedCount ?? 0);
$pendingCount = (int) ($counts['pending'] ?? 0);

$tabs = [
    'pending'  => 'Новые',
    'approved' => 'Принятые',
    'rejected' => 'Отклонённые',
    'all'      => 'Все',
];

$ageBuckets = [
    'juniors' => 'Юниоры',
    'open'    => 'Открытая',
    'other'   => 'Группа',
];

$statusHref = static function (string $key) use ($base, $q): string {
    $href = $base . '/admin?status=' . urlencode($key);
    if ($q !== '') {
        $href .= '&q=' . urlencode($q);
    }
    return $href;
};

$renderPeople = static function (array $people) use ($base, $statuses): void {
    ?>
    <ol class="apps-rows">
      <?php foreach ($people as $i => $p): ?>
        <?php
          $pid = (int) $p['id'];
          $st = $p['status'] ?? 'pending';
          $stLabel = $statuses[$st] ?? $st;
          $openHref = $base . '/admin/participants/' . $pid;
          $gender = ($p['gender'] ?? '') === 'F' ? 'Ж' : 'М';
          $meta = array_filter([
              $gender,
              !empty($p['birth_year']) ? (string) (int) $p['birth_year'] : null,
              !empty($p['disciplines']) && $p['disciplines'] !== '—' ? (string) $p['disciplines'] : null,
              $stLabel,
          ]);
        ?>
        <li class="apps-row admin-apps-row admin-apps-row--<?= htmlspecialchars($st) ?>" style="--i: <?= (int) $i ?>">
          <span class="apps-row-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div class="apps-row-body">
            <div class="apps-row-main">
              <a class="apps-row-name" href="<?= htmlspecialchars($openHref) ?>"><?= htmlspecialchars($p['full_name']) ?></a>
              <?php if ($meta): ?>
                <span class="apps-row-meta"><?= htmlspecialchars(implode(' · ', $meta)) ?></span>
              <?php endif; ?>
            </div>
            <div class="admin-apps-actions">
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
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="admin-table-wrap apps-table-wrap">
      <table class="table-ag admin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>ФИО</th>
            <th>Статус</th>
            <th>Пол</th>
            <th>Г.р.</th>
            <th>Категория</th>
            <th>Телефон</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($people as $i => $p): ?>
            <?php
              $pid = (int) $p['id'];
              $st = $p['status'] ?? 'pending';
              $stLabel = $statuses[$st] ?? $st;
              $openHref = $base . '/admin/participants/' . $pid;
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
              <td><?= !empty($p['birth_year']) ? (int) $p['birth_year'] : '—' ?></td>
              <td><?= htmlspecialchars($p['disciplines'] ?: '—') ?></td>
              <td class="whitespace-nowrap"><?= htmlspecialchars($p['phone'] ?: '—') ?></td>
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
    <?php
};
?>

<div class="apps-container mx-auto w-full max-w-[90rem] px-4 py-6 sm:py-8 lg:px-6">
  <section class="apps-list">
    <header class="apps-list-head">
      <div>
        <h2 class="apps-list-title">
          Заявки
          <?php if ($status === 'pending' && $pendingCount > 0): ?>
            <span class="apps-list-count"><?= str_pad((string) $pendingCount, 2, '0', STR_PAD_LEFT) ?></span>
          <?php elseif ($listedCount > 0): ?>
            <span class="apps-list-count"><?= str_pad((string) $listedCount, 2, '0', STR_PAD_LEFT) ?></span>
          <?php endif; ?>
        </h2>
        <p class="apps-kicker admin-apps-sub">
          <?= htmlspecialchars($adminName ?? 'Админ') ?>
        </p>
      </div>

      <div class="admin-apps-toolbar">
        <form method="get" action="<?= htmlspecialchars($base) ?>/admin" class="admin-apps-search" role="search">
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
        <nav class="admin-apps-nav" aria-label="Действия">
          <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin/users">Юзеры</a>
          <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/register">+</a>
          <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin/logout">Выйти</a>
        </nav>
      </div>
    </header>

    <div class="apps-tabs-scroll">
      <ul class="apps-tabs" aria-label="Статус заявок">
        <?php foreach ($tabs as $key => $label): ?>
          <?php
            $cnt = (int) ($counts[$key] ?? 0);
            $on = $status === $key;
          ?>
          <li>
            <a class="apps-tab <?= $on ? 'is-on' : '' ?>"
               href="<?= htmlspecialchars($statusHref($key)) ?>"
               <?= $on ? 'aria-current="page"' : '' ?>>
              <?= htmlspecialchars($label) ?>
              <span class="apps-tab-count"><?= $cnt ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($listedCount === 0): ?>
      <div class="apps-empty">
        <p>
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
      <div class="apps-groups">
        <?php foreach ($groups as $group): ?>
          <?php
            $people = $group['participants'] ?? [];
            if ($people === []) {
                continue;
            }
            $byAge = $group['by_age'] ?? [
                'juniors' => [],
                'open'    => [],
                'other'   => $people,
            ];
          ?>
          <section class="apps-group">
            <header class="apps-group-head">
              <h3 class="apps-group-title"><?= htmlspecialchars($group['name_ru']) ?></h3>
              <span class="apps-group-count"><?= count($people) ?></span>
            </header>

            <div class="apps-age-blocks">
              <?php foreach ($ageBuckets as $ageKey => $ageTitle): ?>
                <?php
                  $bucket = $byAge[$ageKey] ?? [];
                  if ($bucket === []) {
                      continue;
                  }
                ?>
                <div class="apps-age-block">
                  <h4 class="apps-age-title">
                    <span><?= htmlspecialchars($ageTitle) ?></span>
                    <span class="apps-age-count"><?= count($bucket) ?></span>
                  </h4>
                  <?php $renderPeople($bucket); ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
