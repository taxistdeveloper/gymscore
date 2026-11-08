<?php
/**
 * Блок абсолютки протокола.
 *
 * @var list<array> $absolute
 * @var bool $isFolk
 * @var bool $isReps
 * @var string $unit
 * @var string $bestColTitle
 */
$absolute = $absolute ?? [];
$isFolk = !empty($isFolk);
$isReps = !empty($isReps) || $isFolk;
$unit = $unit ?? ($isReps ? 'раз' : 'кг');
$bestColTitle = $bestColTitle ?? ($isReps ? 'Повторы' : 'Результат');
$useWilks = !$isReps;

if ($absolute === []) {
    return;
}
?>
<div class="proto-abs panel">
  <header class="proto-abs-head">
    <h3 class="proto-abs-title">Абсолютка</h3>
    <p class="proto-abs-sub">
      <?php if ($useWilks): ?>
        Места по коэффициенту Wilks · при равенстве — меньше с/вес
      <?php else: ?>
        Места по результату · при равенстве — меньше с/вес
      <?php endif; ?>
    </p>
  </header>

  <?php foreach ($absolute as $block): ?>
    <?php
      $ageLabel = (string) ($block['age_group'] ?? '');
      $rows = $block['rows'] ?? [];
      if ($rows === []) {
          continue;
      }
    ?>
    <section class="proto-abs-block">
      <?php if ($ageLabel !== ''): ?>
        <h4 class="proto-abs-age">
          <span class="proto-age-label<?= $ageLabel === 'Юниоры' ? ' proto-age-label--jr' : ' proto-age-label--open' ?>"><?= htmlspecialchars($ageLabel) ?></span>
          <span class="proto-abs-count"><?= count($rows) ?></span>
        </h4>
      <?php endif; ?>

      <div class="proto-abs-scroll">
        <table class="table-ag table-sm proto-abs-table">
          <thead>
            <tr>
              <th class="proto-abs-col-place">М</th>
              <th class="text-left">Спортсмен</th>
              <th>ВК</th>
              <th>С/вес</th>
              <th><?= htmlspecialchars($bestColTitle) ?></th>
              <?php if ($useWilks): ?>
                <th>Коэф</th>
              <?php endif; ?>
              <th title="Место в весовой">ВК-М</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php $pl = (int) ($row['abs_place'] ?? 0); ?>
              <tr class="<?= $pl >= 1 && $pl <= 3 ? 'proto-abs-prize proto-abs-prize-' . $pl : '' ?>">
                <td class="proto-abs-col-place">
                  <?php if ($pl === 1): ?>
                    <span class="proto-place is-1">1</span>
                  <?php elseif ($pl === 2): ?>
                    <span class="proto-place is-2">2</span>
                  <?php elseif ($pl === 3): ?>
                    <span class="proto-place is-3">3</span>
                  <?php else: ?>
                    <?= htmlspecialchars((string) ($row['abs_place'] ?? '—')) ?>
                  <?php endif; ?>
                </td>
                <td class="text-left font-semibold"><?= htmlspecialchars(mb_strtoupper((string) $row['full_name'])) ?></td>
                <td><strong><?= htmlspecialchars((string) ($row['category_short'] ?? '—')) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars((string) ($row['body_weight_fmt'] ?? '—')) ?></td>
                <td>
                  <strong><?= htmlspecialchars((string) ($row['best_fmt'] ?? '—')) ?></strong>
                  <span class="text-muted"><?= htmlspecialchars($unit) ?></span>
                </td>
                <?php if ($useWilks): ?>
                  <td><strong><?= htmlspecialchars((string) ($row['score_fmt'] ?? $row['wilks_fmt'] ?? '—')) ?></strong></td>
                <?php endif; ?>
                <td class="text-muted"><?= htmlspecialchars((string) ($row['cat_place'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endforeach; ?>
</div>
