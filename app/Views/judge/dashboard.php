<?php
$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$attemptsAllowed = (int) ($discipline['attempts_allowed'] ?? 3);
$scoring = $discipline['scoring_type'] ?? 'weight';
$isFolk = ($discipline['code'] ?? '') === \App\Models\FolkBench::CODE;
$unit = match ($scoring) {
    'reps' => 'раз',
    'arm'  => 'побед',
    default => 'кг',
};
$resultHint = $isFolk || $scoring === 'reps'
    ? 'сколько раз поднял'
    : ('результат в ' . $unit);

$judgeNav = 'protocol';
$judgeDid = $did;
require __DIR__ . '/nav.php';
?>

<section class="judge-shell">
  <div class="judge-toolbar">
    <form method="get" action="<?= htmlspecialchars($base) ?>/judge/protocol" class="judge-disc">
      <label class="sr-only" for="discipline">Дисциплина</label>
      <select class="field field-sm" id="discipline" name="discipline" onchange="this.form.submit()">
        <?php foreach ($disciplines as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $did ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name_ru']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if ($discipline): ?>
      <p class="judge-toolbar-hint"><?= htmlspecialchars($resultHint) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!$discipline): ?>
    <div class="judge-empty panel">
      <p class="judge-empty-title">Нет дисциплин</p>
      <p class="text-muted mb-0">Добавьте дисциплины в админке.</p>
    </div>
  <?php elseif (empty($participants)): ?>
    <div class="judge-empty panel">
      <p class="judge-empty-title">Нет участников</p>
      <p class="text-muted mb-0">На «<?= htmlspecialchars($discipline['name_ru']) ?>» пока никто не зарегистрирован.</p>
    </div>
  <?php else: ?>
    <div class="judge-protocol-wrap">
      <table class="table-ag judge-protocol">
        <thead>
          <tr>
            <th class="judge-protocol-ath">Участник</th>
            <th class="judge-protocol-bw">С/вес</th>
            <?php for ($i = 1; $i <= $attemptsAllowed; $i++): ?>
              <th>Попытка <?= $i ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $p): ?>
            <?php $pid = (int) $p['id']; ?>
            <tr>
              <td class="judge-protocol-ath">
                <div class="judge-ath-name"><?= htmlspecialchars($p['full_name']) ?></div>
                <div class="judge-ath-meta">
                  <?php if (!empty($p['weighed_at'])): ?>
                    <span class="badge-status badge-status--approved">взвешен</span>
                  <?php else: ?>
                    <span class="badge-status badge-status--pending">не взвешен</span>
                  <?php endif; ?>
                  <?php if (!empty($p['weight_category'])): ?>
                    <span><?= htmlspecialchars($p['weight_category']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="judge-protocol-bw">
                <div class="judge-bw-cell">
                  <span><?= ($p['body_weight'] !== null && (float) $p['body_weight'] > 0)
                    ? number_format((float) $p['body_weight'], 2, '.', '')
                    : '—' ?></span>
                  <a class="btn-secondary-sm"
                     href="<?= htmlspecialchars($base) ?>/judge/weigh-in/<?= $pid ?>"
                     title="Карточка взвешивания">⚖</a>
                </div>
              </td>
              <?php for ($i = 1; $i <= $attemptsAllowed; $i++): ?>
                <?php
                  $a = $attemptsMap[$pid][$i] ?? null;
                  $val = $a['value'] ?? '';
                  $valid = !isset($a) || (int) ($a['is_valid'] ?? 1) === 1;
                  $hasVal = $val !== '' && $val !== null;
                ?>
                <td class="judge-attempt<?= $hasVal ? ($valid ? ' is-good' : ' is-bad') : '' ?>">
                  <form method="post" action="<?= htmlspecialchars($base) ?>/judge/attempt" class="judge-attempt-form">
                    <input type="hidden" name="participant_id" value="<?= $pid ?>">
                    <input type="hidden" name="discipline_id" value="<?= $did ?>">
                    <input type="hidden" name="attempt_no" value="<?= $i ?>">
                    <div class="judge-attempt-row">
                      <input class="field-sm attempt-input" type="number" step="0.01"
                             name="value" value="<?= $hasVal ? htmlspecialchars((string) $val) : '' ?>"
                             placeholder="<?= htmlspecialchars($unit) ?>"
                             aria-label="Попытка <?= $i ?> · <?= htmlspecialchars($p['full_name']) ?>">
                      <button class="btn-primary-sm" type="submit">OK</button>
                    </div>
                    <label class="judge-valid">
                      <input class="check" type="checkbox" name="is_valid" value="1"
                             <?= $valid ? 'checked' : '' ?>>
                      Зачёт
                    </label>
                  </form>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="judge-footnote">
      Снимите «Зачёт» при неудачной попытке. В рейтинг идёт максимальный зачётный результат;
      при равенстве — меньший собственный вес.
    </p>
  <?php endif; ?>
</section>
