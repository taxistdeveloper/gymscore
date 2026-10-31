<?php
use App\Models\Participant;
use App\Models\WeightCategory;

$base = $config['base_url'] ?? '';
$p = $participant;
$pid = (int) $p['id'];
$filter = $filter ?? 'pending';
$q = $q ?? '';
$disciplineId = (int) ($disciplineId ?? 0);
$genderLabel = ($p['gender'] ?? '') === 'F' ? 'Женщина' : 'Мужчина';
$done = !empty($p['weighed_at']);
$bw = $done || ((float) ($p['body_weight'] ?? 0) > 0)
    ? number_format((float) $p['body_weight'], 2, '.', '')
    : '';
$registeredCategory = Participant::weighCategoryLabel($p);
$listHref = $base . '/judge/weigh-in?filter=' . urlencode($filter)
    . ($disciplineId > 0 ? '&discipline=' . $disciplineId : '')
    . ($q !== '' ? '&q=' . urlencode($q) : '');
?>

<div class="weigh-overlay">
  <div class="weigh-modal" role="dialog" aria-modal="true"
       aria-labelledby="weighModalTitle">
    <header class="weigh-modal-head">
      <div>
        <p class="weigh-modal-kicker">Взвешивание</p>
        <h1 class="weigh-modal-title" id="weighModalTitle">
          <?= htmlspecialchars($p['full_name']) ?>
          <span class="weigh-modal-gender"><?= $genderLabel ?></span>
        </h1>
      </div>
      <a class="weigh-modal-close" href="<?= htmlspecialchars($listHref) ?>"
         aria-label="Закрыть">×</a>
    </header>

    <form method="post" action="<?= htmlspecialchars($base) ?>/judge/weigh-in"
          class="weigh-modal-body" id="weighForm" novalidate>
      <input type="hidden" name="participant_id" value="<?= $pid ?>">
      <input type="hidden" name="from" value="card">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="hidden" name="list_discipline" value="<?= $disciplineId ?>">
      <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">

      <div class="weigh-field">
        <p class="weigh-label">Весовая категория (с регистрации)</p>
        <p class="weigh-cat-current"><?= htmlspecialchars($registeredCategory) ?></p>
        <p class="weigh-hint">Категория уже выбрана участником при заявке — на взвешивании не меняется.</p>
      </div>

      <div class="weigh-field">
        <label class="weigh-label" for="body_weight">Собственный вес на весах *</label>
        <div class="weigh-bw-row">
          <input class="weigh-bw-input" id="body_weight" name="body_weight"
                 type="number" step="0.01" min="20" max="300" required
                 inputmode="decimal" autocomplete="off"
                 value="<?= htmlspecialchars($bw) ?>"
                 placeholder="00.00">
          <span class="weigh-bw-unit">кг</span>
        </div>
        <p class="weigh-hint">Фиксируется для протокола и народного жима (½ с/в). Категорию не пересчитывает.</p>
      </div>

      <?php if (!empty($registrations)): ?>
        <section class="weigh-openings">
          <h2 class="weigh-openings-title">Стартовые веса на помост</h2>

          <div class="weigh-openings-list">
            <?php foreach ($registrations as $reg): ?>
              <?php
                $did = (int) $reg['discipline_id'];
                $scoring = $reg['scoring_type'] ?? 'weight';
                $isFolk = ($reg['code'] ?? '') === \App\Models\FolkBench::CODE;
                $unit = $isFolk ? 'кг' : match ($scoring) {
                    'reps' => 'раз',
                    'arm'  => 'побед',
                    default => 'кг',
                };
                $catLive = !empty($reg['weight_category'])
                    ? (string) $reg['weight_category']
                    : $registeredCategory;
                $catMax = WeightCategory::maxFromLabel($catLive);
                $opening = $reg['opening_weight'];

                if ($isFolk && $bw !== '') {
                    $openingVal = number_format(
                        \App\Models\FolkBench::barWeight((float) $bw),
                        1,
                        '.',
                        ''
                    );
                } elseif ($opening !== null && $opening !== '') {
                    $openingVal = number_format(
                        (float) $opening,
                        $scoring === 'weight' || $isFolk ? 1 : 0,
                        '.',
                        ''
                    );
                } elseif ($scoring === 'weight' && !$isFolk && $catMax !== null) {
                    // Стартовый вес «Жим лёжа» и т.п. — из категории регистрации
                    $openingVal = number_format($catMax, 1, '.', '');
                } else {
                    $openingVal = '';
                }
              ?>
              <div class="weigh-opening-row<?= $isFolk ? ' is-folk' : '' ?>">
                <div class="weigh-opening-info">
                  <div class="weigh-opening-name"><?= htmlspecialchars($reg['name_ru']) ?></div>
                  <div class="weigh-opening-cat"><?= htmlspecialchars($catLive) ?></div>
                  <?php if ($isFolk): ?>
                    <div class="weigh-opening-hint text-sm text-muted">
                      Штанга = ½ собственного веса · на помосте считают повторы
                    </div>
                  <?php elseif ($scoring === 'weight' && $opening === null && $catMax !== null): ?>
                    <div class="weigh-opening-hint text-sm text-muted">
                      Подставлено из категории регистрации — можно изменить
                    </div>
                  <?php endif; ?>
                </div>
                <div class="weigh-opening-input-wrap">
                  <?php if ($isFolk): ?>
                    <input class="weigh-opening-input" type="number"
                           name="opening[<?= $did ?>]"
                           data-folk-half
                           step="0.5" min="0" readonly tabindex="-1"
                           inputmode="decimal"
                           value="<?= htmlspecialchars($openingVal) ?>"
                           placeholder="—">
                  <?php else: ?>
                    <input class="weigh-opening-input" type="number"
                           name="opening[<?= $did ?>]"
                           step="<?= $scoring === 'weight' ? '0.5' : '1' ?>"
                           min="0" inputmode="decimal"
                           value="<?= htmlspecialchars($openingVal) ?>"
                           placeholder="—">
                  <?php endif; ?>
                  <span class="weigh-opening-unit"><?= htmlspecialchars($unit) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <div class="weigh-field">
        <label class="weigh-label" for="weigh_note">Заметка (необязательно)</label>
        <textarea class="weigh-note" id="weigh_note" name="weigh_note" rows="2"
                  placeholder="Экипировка, замечания…"><?= htmlspecialchars($p['weigh_note'] ?? '') ?></textarea>
      </div>

      <footer class="weigh-modal-actions">
        <a class="btn-secondary" href="<?= htmlspecialchars($listHref) ?>">Отмена</a>
        <button class="btn-primary" type="submit">Зафиксировать</button>
      </footer>
    </form>
  </div>
</div>

<script>
(function () {
  var input = document.getElementById('body_weight');
  if (!input) return;

  function halfBar(bw) {
    return Math.round(bw) / 2;
  }

  function updateFolk() {
    var raw = String(input.value || '').replace(',', '.');
    if (!raw || isNaN(raw) || Number(raw) <= 0) return;
    var bw = Number(raw);
    document.querySelectorAll('[data-folk-half]').forEach(function (el) {
      el.value = halfBar(bw).toFixed(1);
    });
  }

  input.addEventListener('input', updateFolk);
  input.focus();
  if (input.value) updateFolk();
})();
</script>
