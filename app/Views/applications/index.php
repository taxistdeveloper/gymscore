<?php

use App\Lang\Lang;

$t = $t ?? static fn(string $key, ?string $fallback = null): string => Lang::t($key, $fallback);
$base = $config['base_url'] ?? '';
$participants = $participants ?? [];
$disciplines = $disciplines ?? [];
$discipline = $discipline ?? null;
$disciplineId = (int) ($disciplineId ?? 0);
$totalPending = (int) ($totalPending ?? 0);
$hasDiscipline = $disciplineId > 0;
$count = count($participants);
?>

<div class="apps-container mx-auto w-full max-w-[90rem] px-4 py-8 lg:px-6">
  <section class="apps-list">
    <header class="apps-list-head">
      <div>
        <h2 class="apps-list-title"><?= htmlspecialchars($t('apps.title')) ?></h2>
        <p class="apps-list-sub">
          <?= htmlspecialchars($t('apps.sub')) ?>
          <?php if ($totalPending > 0): ?>
            <span class="apps-list-count">· <?= $totalPending ?></span>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <?php if ($totalPending === 0): ?>
      <div class="admin-empty panel">
        <p class="admin-empty-title"><?= htmlspecialchars($t('apps.empty')) ?></p>
      </div>

    <?php elseif (!$hasDiscipline): ?>
      <p class="judge-lead"><?= htmlspecialchars($t('apps.choose_discipline')) ?></p>
      <div class="weigh-cat-grid">
        <?php foreach ($disciplines as $d): ?>
          <?php
            $did = (int) $d['id'];
            $cnt = (int) $d['count'];
            $empty = $cnt === 0;
            $href = $base . '/applications?discipline=' . $did;
          ?>
          <a class="weigh-cat-card<?= $empty ? ' is-empty' : '' ?>" href="<?= htmlspecialchars($href) ?>">
            <span class="weigh-cat-name"><?= htmlspecialchars($d['name_ru']) ?></span>
            <span class="weigh-cat-count">
              <?= $cnt ?> <?= htmlspecialchars($t('apps.pending_count')) ?>
            </span>
            <span class="weigh-card-cta"><?= htmlspecialchars($t('apps.open')) ?> →</span>
          </a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="judge-crumb">
        <a class="btn-secondary-sm" href="<?= htmlspecialchars($base . '/applications') ?>">
          ← <?= htmlspecialchars($t('apps.back_disciplines')) ?>
        </a>
        <h2 class="judge-crumb-title"><?= htmlspecialchars($discipline['name_ru'] ?? '') ?></h2>
        <?php if ($count > 0): ?>
          <span class="apps-list-count"><?= $count ?></span>
        <?php endif; ?>
      </div>

      <?php if ($count === 0): ?>
        <div class="admin-empty panel">
          <p class="admin-empty-title"><?= htmlspecialchars($t('apps.empty_discipline')) ?></p>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="table-ag admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th><?= htmlspecialchars($t('apps.col_name')) ?></th>
                <th><?= htmlspecialchars($t('apps.col_gender')) ?></th>
                <th><?= htmlspecialchars($t('apps.col_group')) ?></th>
                <th><?= htmlspecialchars($t('apps.col_birth')) ?></th>
                <th><?= htmlspecialchars($t('apps.col_disciplines')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($participants as $i => $p): ?>
                <?php
                  $ageGroup = '';
                  if (!empty($p['age_group'])) {
                      $ageGroup = $p['age_group'] === 'juniors'
                          ? $t('apps.group_juniors')
                          : $t('apps.group_open');
                  }
                ?>
                <tr>
                  <td class="text-muted"><?= (int) $i + 1 ?></td>
                  <td class="admin-td-name"><?= htmlspecialchars($p['full_name']) ?></td>
                  <td><?= ($p['gender'] ?? '') === 'F' ? $t('apps.gender_f') : $t('apps.gender_m') ?></td>
                  <td><?= $ageGroup !== '' ? htmlspecialchars($ageGroup) : '—' ?></td>
                  <td><?= !empty($p['birth_year']) ? (int) $p['birth_year'] : '—' ?></td>
                  <td><?= htmlspecialchars($p['disciplines'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
