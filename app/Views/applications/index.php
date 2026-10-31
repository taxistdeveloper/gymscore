<?php

use App\Lang\Lang;

$t = $t ?? static fn(string $key, ?string $fallback = null): string => Lang::t($key, $fallback);
$groups = $groups ?? [];
$totalPending = (int) ($totalPending ?? 0);
$listedCount = (int) ($listedCount ?? 0);

$ageBuckets = [
    'juniors' => $t('apps.group_juniors'),
    'open'    => $t('apps.group_open'),
    'other'   => $t('apps.col_group'),
];

$renderPeople = static function (array $people) use ($t): void {
    ?>
    <ol class="apps-rows">
      <?php foreach ($people as $i => $p): ?>
        <?php
          $gender = ($p['gender'] ?? '') === 'F' ? $t('apps.gender_f') : $t('apps.gender_m');
          $meta = array_filter([
              $gender,
              !empty($p['birth_year']) ? (string) (int) $p['birth_year'] : null,
              !empty($p['disciplines']) && $p['disciplines'] !== '—' ? (string) $p['disciplines'] : null,
          ]);
        ?>
        <li class="apps-row" style="--i: <?= (int) $i ?>">
          <span class="apps-row-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div class="apps-row-body">
            <div class="apps-row-main">
              <span class="apps-row-name"><?= htmlspecialchars($p['full_name']) ?></span>
              <?php if ($meta): ?>
                <span class="apps-row-meta"><?= htmlspecialchars(implode(' · ', $meta)) ?></span>
              <?php endif; ?>
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
            <th><?= htmlspecialchars($t('apps.col_name')) ?></th>
            <th><?= htmlspecialchars($t('apps.col_gender')) ?></th>
            <th><?= htmlspecialchars($t('apps.col_birth')) ?></th>
            <th><?= htmlspecialchars($t('apps.col_category')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($people as $i => $p): ?>
            <tr>
              <td class="text-muted"><?= (int) $i + 1 ?></td>
              <td class="admin-td-name"><?= htmlspecialchars($p['full_name']) ?></td>
              <td><?= ($p['gender'] ?? '') === 'F' ? $t('apps.gender_f') : $t('apps.gender_m') ?></td>
              <td><?= !empty($p['birth_year']) ? (int) $p['birth_year'] : '—' ?></td>
              <td><?= htmlspecialchars($p['disciplines'] ?: '—') ?></td>
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
          <?= htmlspecialchars($t('apps.title')) ?>
          <?php if ($totalPending > 0): ?>
            <span class="apps-list-count"><?= str_pad((string) $totalPending, 2, '0', STR_PAD_LEFT) ?></span>
          <?php endif; ?>
        </h2>
      </div>
    </header>

    <?php if ($listedCount === 0): ?>
      <div class="apps-empty">
        <p><?= htmlspecialchars($t('apps.empty')) ?></p>
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
