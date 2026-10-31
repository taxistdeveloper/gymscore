<?php
$base = $config['base_url'] ?? '';
$filter = $filter ?? 'pending';
$q = $q ?? '';
$discipline = $discipline ?? null;
$disciplineId = (int) ($discipline['id'] ?? 0);
$counts = $counts ?? ['all' => 0, 'pending' => 0, 'done' => 0];
$disciplines = $disciplines ?? [];
$participants = $participants ?? [];
$hasDiscipline = $disciplineId > 0;
$showPeople = $hasDiscipline || $q !== '';

$tabs = [
    'pending' => 'Ждут',
    'done'    => 'Готово',
    'all'     => 'Все',
];

$weighQuery = static function (array $extra = []) use ($filter, $q, $disciplineId): string {
    $params = array_merge([
        'filter'     => $filter,
        'discipline' => $disciplineId > 0 ? $disciplineId : '',
        'q'          => $q,
    ], $extra);
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            continue;
        }
        $parts[] = urlencode((string) $key) . '=' . urlencode((string) $value);
    }
    return $parts ? '?' . implode('&', $parts) : '';
};

$judgeNav = 'weigh';
$judgeDid = $disciplineId;
require __DIR__ . '/nav.php';
?>

<section class="judge-shell">
  <div class="judge-toolbar">
    <ul class="judge-tabs" aria-label="Фильтр взвешивания">
      <?php foreach ($tabs as $key => $label): ?>
        <?php
          $href = $base . '/judge/weigh-in' . $weighQuery(['filter' => $key]);
          $cnt = (int) ($counts[$key] ?? 0);
          $on = $filter === $key;
        ?>
        <li>
          <a class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"
             href="<?= htmlspecialchars($href) ?>"
             <?= $on ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($label) ?>
            <span class="pill-count <?= $on ? 'bg-white/20 text-white' : 'bg-wash text-muted' ?>"><?= $cnt ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="get" action="<?= htmlspecialchars($base) ?>/judge/weigh-in" class="judge-search" role="search">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <?php if ($hasDiscipline): ?>
        <input type="hidden" name="discipline" value="<?= $disciplineId ?>">
      <?php endif; ?>
      <label class="sr-only" for="q">Поиск</label>
      <input class="field field-sm min-w-0 flex-1" id="q" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="ФИО или телефон" autocomplete="off">
      <button class="btn-primary-sm" type="submit">Найти</button>
      <?php if ($q !== ''): ?>
        <a class="btn-secondary-sm"
           href="<?= htmlspecialchars($base . '/judge/weigh-in' . $weighQuery(['q' => ''])) ?>">×</a>
      <?php endif; ?>
    </form>

    <p class="judge-toolbar-hint">
      <?= (int) $counts['done'] ?> / <?= (int) $counts['all'] ?> взвешено
      <?php if ($hasDiscipline): ?>
        · <?= htmlspecialchars($discipline['name_ru']) ?>
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$showPeople): ?>
    <p class="judge-lead">Выберите дисциплину</p>
    <div class="weigh-cat-grid">
      <?php foreach ($disciplines as $d): ?>
        <?php
          $did = (int) $d['id'];
          $cnt = (int) $d['count'];
          $href = $base . '/judge/weigh-in' . $weighQuery(['discipline' => $did, 'q' => '']);
          $empty = $cnt === 0;
        ?>
        <a class="weigh-cat-card<?= $empty ? ' is-empty' : '' ?>" href="<?= htmlspecialchars($href) ?>">
          <span class="weigh-cat-name"><?= htmlspecialchars($d['name_ru']) ?></span>
          <span class="weigh-cat-count">
            <?php if ($filter === 'pending'): ?>
              <?= $cnt ?> ждут
            <?php elseif ($filter === 'done'): ?>
              <?= $cnt ?> готово
            <?php else: ?>
              <?= $cnt ?>
              <span class="weigh-cat-count-sub">
                · <?= (int) $d['pending'] ?> ждут · <?= (int) $d['done'] ?> ок
              </span>
            <?php endif; ?>
          </span>
          <span class="weigh-card-cta">Открыть →</span>
        </a>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="judge-crumb">
      <a class="btn-secondary-sm"
         href="<?= htmlspecialchars($base . '/judge/weigh-in' . $weighQuery(['discipline' => '', 'q' => $q])) ?>">
        ← Дисциплины
      </a>
      <?php if ($hasDiscipline): ?>
        <h2 class="judge-crumb-title"><?= htmlspecialchars($discipline['name_ru']) ?></h2>
      <?php elseif ($q !== ''): ?>
        <h2 class="judge-crumb-title">Поиск: «<?= htmlspecialchars($q) ?>»</h2>
      <?php endif; ?>
    </div>

    <?php if (empty($participants)): ?>
      <div class="judge-empty panel">
        <p class="judge-empty-title">
          <?php if ($q !== ''): ?>
            Никого не найдено
          <?php elseif ($filter === 'pending'): ?>
            Все уже взвешены
          <?php elseif ($filter === 'done'): ?>
            Пока никто не взвешен
          <?php else: ?>
            Нет участников
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <div class="weigh-list">
        <?php foreach ($participants as $p): ?>
          <?php
            $pid = (int) $p['id'];
            $done = !empty($p['weighed_at']);
            $hasBw = $p['body_weight'] !== null && (float) $p['body_weight'] > 0;
            $bw = $hasBw ? number_format((float) $p['body_weight'], 2, '.', '') : '';
            $cardHref = $base . '/judge/weigh-in/' . $pid . $weighQuery();
            $genderLabel = ($p['gender'] ?? '') === 'F' ? 'Ж' : 'М';
            $weightCat = \App\Models\Participant::weighCategoryLabel($p);
          ?>
          <a class="weigh-card<?= $done ? ' is-done' : '' ?>" href="<?= htmlspecialchars($cardHref) ?>">
            <div class="weigh-card-main">
              <div>
                <h3 class="weigh-name">
                  <?= htmlspecialchars($p['full_name']) ?>
                  <span class="weigh-card-gender"><?= $genderLabel ?></span>
                </h3>
                <p class="weigh-meta">
                  <?= htmlspecialchars($weightCat) ?>
                  <?php if (!empty($p['disciplines'])): ?>
                    · <?= htmlspecialchars($p['disciplines']) ?>
                  <?php endif; ?>
                </p>
                <?php if ($done): ?>
                  <span class="badge-status badge-status--approved mt-2">
                    <?= $bw !== '' ? $bw . ' кг · ' : '' ?><?= htmlspecialchars(date('H:i', strtotime($p['weighed_at']))) ?>
                  </span>
                <?php else: ?>
                  <span class="badge-status badge-status--pending mt-2">
                    Кат. <?= htmlspecialchars($weightCat) ?>
                  </span>
                <?php endif; ?>
              </div>
              <span class="weigh-card-cta"><?= $done ? 'Изменить' : 'Взвесить' ?> →</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
