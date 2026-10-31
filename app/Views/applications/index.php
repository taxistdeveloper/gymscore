<?php

use App\Lang\Lang;

$t = $t ?? static fn(string $key, ?string $fallback = null): string => Lang::t($key, $fallback);
$participants = $participants ?? [];
$count = count($participants);
?>

<div class="apps-container mx-auto w-full max-w-[90rem] px-4 py-8 lg:px-6">
  <section class="apps-list">
    <header class="apps-list-head">
      <div>
        <h2 class="apps-list-title"><?= htmlspecialchars($t('apps.title')) ?></h2>
        <p class="apps-list-sub">
          <?= htmlspecialchars($t('apps.sub')) ?>
          <?php if ($count > 0): ?>
            <span class="apps-list-count">· <?= $count ?></span>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <?php if ($count === 0): ?>
      <div class="admin-empty panel">
        <p class="admin-empty-title"><?= htmlspecialchars($t('apps.empty')) ?></p>
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
  </section>
</div>
