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

$regByDid = [];
foreach ($registrations ?? [] as $reg) {
    $regByDid[(int) $reg['discipline_id']] = $reg;
}
$allDisciplines = $disciplines ?? [];
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

      <?php if (!empty($allDisciplines)): ?>
        <section class="weigh-openings">
          <h2 class="weigh-openings-title">Дисциплины и стартовые веса</h2>
          <p class="weigh-hint" style="margin-top:-0.35rem;margin-bottom:0.75rem">
            Можно снять заявленную дисциплину и отметить другую — прямо на взвешивании.
          </p>

          <div class="weigh-openings-list">
            <?php foreach ($allDisciplines as $d): ?>
              <?php
                $did = (int) $d['id'];
                $reg = $regByDid[$did] ?? null;
                $checked = $reg !== null;
                $scoring = $d['scoring_type'] ?? 'weight';
                $isFolk = ($d['code'] ?? '') === \App\Models\FolkBench::CODE;
                $showOpening = $isFolk || $scoring === 'weight';
                $unit = $isFolk ? 'кг' : match ($scoring) {
                    'reps' => 'раз',
                    'arm'  => 'побед',
                    default => 'кг',
                };
                $catLive = !empty($reg['weight_category'])
                    ? (string) $reg['weight_category']
                    : $registeredCategory;
                $catMax = WeightCategory::maxFromLabel($catLive);
                $opening = $reg['opening_weight'] ?? null;

                if ($opening !== null && $opening !== '') {
                    $openingVal = number_format(
                        (float) $opening,
                        $scoring === 'weight' || $isFolk ? 1 : 0,
                        '.',
                        ''
                    );
                } elseif ($isFolk && $bw !== '') {
                    $openingVal = number_format(
                        \App\Models\FolkBench::barWeight((float) $bw),
                        1,
                        '.',
                        ''
                    );
                } elseif ($scoring === 'weight' && !$isFolk && $catMax !== null) {
                    $openingVal = number_format($catMax, 1, '.', '');
                } else {
                    $openingVal = '';
                }
              ?>
              <div class="weigh-opening-row<?= $isFolk ? ' is-folk' : '' ?><?= $checked ? '' : ' is-off' ?>"
                   data-disc-row
                   data-folk="<?= $isFolk ? '1' : '0' ?>"
                   data-scoring="<?= htmlspecialchars($scoring) ?>"
                   data-cat-max="<?= $catMax !== null ? htmlspecialchars((string) $catMax) : '' ?>">
                <div class="weigh-opening-info">
                  <label class="weigh-opening-name" for="disc<?= $did ?>">
                    <input class="weigh-check" type="checkbox"
                           name="disciplines[]" value="<?= $did ?>"
                           id="disc<?= $did ?>"
                           data-disc-check
                           <?= $checked ? 'checked' : '' ?>>
                    <?= htmlspecialchars($d['name_ru']) ?>
                  </label>
                  <div class="weigh-opening-cat"><?= htmlspecialchars($catLive) ?></div>
                  <?php if ($isFolk): ?>
                    <div class="weigh-opening-hint text-sm text-muted" data-opening-hint>
                      По умолчанию ½ с/в (шаг 2.5 кг) — можно изменить · на помосте считают повторы
                    </div>
                  <?php elseif ($scoring === 'weight'): ?>
                    <div class="weigh-opening-hint text-sm text-muted" data-opening-hint>
                      Подставлено из категории регистрации — можно изменить
                    </div>
                  <?php endif; ?>
                </div>
                <?php if ($showOpening): ?>
                  <div class="weigh-opening-input-wrap" data-opening-wrap
                       style="<?= $checked ? '' : 'display:none' ?>">
                    <?php if ($isFolk): ?>
                      <input class="weigh-opening-input" type="number"
                             name="opening[<?= $did ?>]"
                             data-folk-half
                             step="2.5" min="0"
                             inputmode="decimal"
                             value="<?= htmlspecialchars($openingVal) ?>"
                             placeholder="—">
                    <?php else: ?>
                      <input class="weigh-opening-input" type="number"
                             name="opening[<?= $did ?>]"
                             step="0.5"
                             min="0" inputmode="decimal"
                             value="<?= htmlspecialchars($openingVal) ?>"
                             placeholder="—">
                    <?php endif; ?>
                    <span class="weigh-opening-unit"><?= htmlspecialchars($unit) ?></span>
                  </div>
                <?php endif; ?>
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
  var form = document.getElementById('weighForm');

  function halfBar(bw) {
    var half = bw / 2;
    return Math.round(half / 2.5) * 2.5;
  }

  document.querySelectorAll('[data-disc-row]').forEach(function (row) {
    var check = row.querySelector('[data-disc-check]');
    var wrap = row.querySelector('[data-opening-wrap]');
    if (!check) return;

    function syncRow() {
      row.classList.toggle('is-off', !check.checked);
      if (wrap) wrap.style.display = check.checked ? '' : 'none';
      if (check.checked && wrap) {
        var opening = wrap.querySelector('.weigh-opening-input');
        if (opening && !opening.value) {
          if (row.dataset.folk === '1' && input && input.value) {
            var raw = String(input.value).replace(',', '.');
            if (raw && !isNaN(raw) && Number(raw) > 0) {
              opening.value = halfBar(Number(raw)).toFixed(1);
              opening.dataset.auto = '1';
              opening.dataset.lastAuto = opening.value;
            }
          } else if (row.dataset.scoring === 'weight' && row.dataset.catMax) {
            opening.value = Number(row.dataset.catMax).toFixed(1);
          }
        }
      }
    }

    check.addEventListener('change', syncRow);
  });

  document.querySelectorAll('[data-folk-half]').forEach(function (el) {
    var initial = el.value || '';
    el.dataset.lastAuto = initial;
    el.dataset.auto = initial ? '0' : '1';
    el.addEventListener('input', function () {
      el.dataset.auto = '0';
    });
  });

  function updateFolk(force) {
    if (!input) return;
    var raw = String(input.value || '').replace(',', '.');
    if (!raw || isNaN(raw) || Number(raw) <= 0) return;
    var suggested = halfBar(Number(raw)).toFixed(1);
    document.querySelectorAll('[data-disc-row]').forEach(function (row) {
      var check = row.querySelector('[data-disc-check]');
      if (!check || !check.checked) return;
      var el = row.querySelector('[data-folk-half]');
      if (!el) return;
      if (!force && el.dataset.auto === '0' && el.value !== '') {
        return;
      }
      el.value = suggested;
      el.dataset.lastAuto = suggested;
      el.dataset.auto = '1';
    });
  }

  if (input) {
    input.addEventListener('input', function () {
      updateFolk(false);
    });
    input.focus();
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      var any = form.querySelector('[data-disc-check]:checked');
      if (!any) {
        e.preventDefault();
        alert('Выберите хотя бы одну дисциплину');
      }
    });
  }
})();
</script>
