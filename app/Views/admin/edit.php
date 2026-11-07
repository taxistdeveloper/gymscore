<?php
$base = $config['base_url'] ?? '';
$p = $participant;
$selected = $selected ?? [];
$statuses = $statuses ?? [];
$registrations = $registrations ?? [];
$pid = (int) $p['id'];
$st = $p['status'] ?? 'pending';
$stLabel = $statuses[$st] ?? $st;

$regByDid = [];
foreach ($registrations as $reg) {
    $regByDid[(int) $reg['discipline_id']] = $reg;
}
$bwNum = ($p['body_weight'] !== null && (float) $p['body_weight'] > 0)
    ? (float) $p['body_weight']
    : null;
?>

<div class="mx-auto w-full max-w-3xl">
  <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="mb-1 text-2xl font-semibold"><?= htmlspecialchars($p['full_name'] ?? 'Заявка') ?></h1>
      <p class="text-sm text-muted">
        #<?= $pid ?> · <?= htmlspecialchars($adminName) ?> ·
        <span class="badge-status badge-status--<?= htmlspecialchars($st) ?>">
          <?= htmlspecialchars($stLabel) ?>
        </span>
      </p>
    </div>
    <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin">← К списку</a>
  </div>

  <section class="card mb-4">
    <div class="card-body">
      <h2 class="mb-3 text-lg font-semibold">Решение по заявке</h2>
      <form method="post" action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>/status">
        <div class="mb-4">
          <label class="field-label" for="status_note">Комментарий</label>
          <input class="field w-full" id="status_note" name="status_note"
                 value="<?= htmlspecialchars($p['status_note'] ?? '') ?>"
                 placeholder="Например: неполный возраст / дубль">
        </div>
        <div class="flex flex-wrap gap-2">
          <?php if ($st !== 'approved'): ?>
            <button class="btn-primary" type="submit" name="status" value="approved">Принять в протокол</button>
          <?php endif; ?>
          <?php if ($st !== 'rejected'): ?>
            <button class="btn-danger" type="submit" name="status" value="rejected">Отклонить</button>
          <?php endif; ?>
          <?php if ($st !== 'pending'): ?>
            <button class="btn-secondary" type="submit" name="status" value="pending">Вернуть в новые</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>

  <form method="post" action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>" novalidate>
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="mb-3 text-lg font-semibold">Данные</h2>

        <div class="mb-4">
          <label class="field-label" for="full_name">ФИО</label>
          <input class="field w-full" id="full_name" name="full_name" required
                 value="<?= htmlspecialchars($p['full_name'] ?? '') ?>">
        </div>

        <fieldset class="mb-4">
          <legend class="field-label">Пол</legend>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <input class="check" type="radio" name="gender" id="gender_m" value="M"
                     <?= ($p['gender'] ?? 'M') === 'M' ? 'checked' : '' ?>>
              <label for="gender_m">Мужской</label>
            </div>
            <div class="flex items-center gap-2">
              <input class="check" type="radio" name="gender" id="gender_f" value="F"
                     <?= ($p['gender'] ?? '') === 'F' ? 'checked' : '' ?>>
              <label for="gender_f">Женский</label>
            </div>
          </div>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="phone">Телефон</label>
            <input class="field w-full" id="phone" name="phone" type="tel"
                   autocomplete="tel" inputmode="tel" maxlength="16"
                   value="<?= htmlspecialchars($p['phone'] ?? '') ?>"
                   placeholder="+7 700 000 00 00">
          </div>
          <div>
            <label class="field-label" for="body_weight">Собственный вес, кг</label>
            <input class="field w-full" id="body_weight" name="body_weight" type="number"
                   step="0.01" min="20" max="300" inputmode="decimal"
                   value="<?= ($p['body_weight'] !== null && (float) $p['body_weight'] > 0) ? number_format((float) $p['body_weight'], 2, '.', '') : '' ?>"
                   placeholder="Взвешивание на месте">
          </div>
          <div>
            <label class="field-label" for="birth_year">Год рождения</label>
            <input class="field w-full" id="birth_year" name="birth_year" type="number"
                   min="1920" max="2026" inputmode="numeric"
                   value="<?= htmlspecialchars((string) ($p['birth_year'] ?? '')) ?>"
                   placeholder="1995">
          </div>
          <div>
            <label class="field-label" for="age_group">Возрастная группа</label>
            <select class="field w-full" id="age_group" name="age_group">
              <option value="">— Не выбрана —</option>
              <?php foreach (($ageGroups ?? []) as $code => $ag): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= ($p['age_group'] ?? '') === $code ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ag['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h2 class="mb-3 text-lg font-semibold">Дисциплины и стартовые веса</h2>
        <p class="mb-4 text-sm text-muted">
          Для народного жима можно задать вес штанги вручную (шаг 2.5 кг). Пустое поле при наличии с/в — авто ½ с/в.
        </p>
        <div class="flex flex-col gap-3">
          <?php foreach ($disciplines as $d): ?>
            <?php
              $did = (int) $d['id'];
              $code = (string) ($d['code'] ?? '');
              $scoring = (string) ($d['scoring_type'] ?? 'weight');
              $isFolk = $code === \App\Models\FolkBench::CODE;
              $showOpening = $isFolk || $scoring === 'weight';
              $checked = in_array($did, $selected, true);
              $reg = $regByDid[$did] ?? null;
              $openingRaw = $reg['opening_weight'] ?? null;
              if ($isFolk && ($openingRaw === null || $openingRaw === '') && $bwNum !== null) {
                  $openingRaw = \App\Models\FolkBench::barWeight($bwNum);
              }
              $openingVal = ($openingRaw !== null && $openingRaw !== '')
                  ? number_format((float) $openingRaw, 1, '.', '')
                  : '';
              $step = $isFolk ? '2.5' : ($scoring === 'weight' ? '0.5' : '1');
            ?>
            <div class="admin-disc-row rounded-lg border border-[color-mix(in_srgb,var(--ink)_12%,transparent)] p-3"
                 data-disc-row
                 data-folk="<?= $isFolk ? '1' : '0' ?>">
              <div class="flex flex-wrap items-center gap-3">
                <div class="flex min-w-[12rem] flex-1 items-center gap-2">
                  <input class="check" type="checkbox"
                         name="disciplines[]" value="<?= $did ?>"
                         id="d<?= $did ?>"
                         data-disc-check
                         <?= $checked ? 'checked' : '' ?>>
                  <label for="d<?= $did ?>"><?= htmlspecialchars($d['name_ru']) ?></label>
                </div>
                <?php if ($showOpening): ?>
                  <div class="flex items-center gap-2" data-opening-wrap
                       style="<?= $checked ? '' : 'display:none' ?>">
                    <label class="text-sm text-muted whitespace-nowrap" for="opening<?= $did ?>">
                      <?= $isFolk ? 'Штанга, кг' : 'Старт, кг' ?>
                    </label>
                    <input class="field w-24" type="number"
                           id="opening<?= $did ?>"
                           name="opening[<?= $did ?>]"
                           step="<?= $step ?>" min="0" inputmode="decimal"
                           <?= $isFolk ? 'data-folk-opening' : '' ?>
                           value="<?= htmlspecialchars($openingVal) ?>"
                           placeholder="<?= $isFolk ? '½ с/в' : '—' ?>">
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($isFolk): ?>
                <p class="mt-2 text-xs text-muted">½ собственного веса, округление до 2.5 кг — можно изменить</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button type="submit" class="btn-primary">Сохранить данные</button>
      <a href="<?= htmlspecialchars($base) ?>/admin" class="btn-secondary">К списку</a>
    </div>
  </form>

  <section class="card mt-6">
    <div class="card-body">
      <h2 class="mb-2 text-lg font-semibold">Удаление</h2>
      <p class="mb-4 text-sm text-muted">
        Заявка и связанные данные (регистрации, попытки) будут удалены без возможности восстановления.
      </p>
      <form method="post"
            action="<?= htmlspecialchars($base) ?>/admin/participants/<?= $pid ?>/delete"
            onsubmit="return confirm(<?= htmlspecialchars(json_encode("Удалить заявку #{$pid} (" . ($p['full_name'] ?? '') . ")? Это нельзя отменить."), ENT_QUOTES, 'UTF-8') ?>);">
        <button class="btn-danger" type="submit">Удалить заявку</button>
      </form>
    </div>
  </section>
</div>

<script src="<?= htmlspecialchars($base) ?>/js/phone-mask.js"></script>
<script>
(function () {
  function halfBar(bw) {
    var half = bw / 2;
    return Math.round(half / 2.5) * 2.5;
  }

  document.querySelectorAll('[data-disc-row]').forEach(function (row) {
    var check = row.querySelector('[data-disc-check]');
    var wrap = row.querySelector('[data-opening-wrap]');
    if (!check || !wrap) return;
    check.addEventListener('change', function () {
      wrap.style.display = check.checked ? '' : 'none';
    });
  });

  var bwInput = document.getElementById('body_weight');
  if (!bwInput) return;

  function syncFolk() {
    var raw = String(bwInput.value || '').replace(',', '.');
    if (!raw || isNaN(raw) || Number(raw) <= 0) return;
    var suggested = halfBar(Number(raw)).toFixed(1);
    document.querySelectorAll('[data-folk-opening]').forEach(function (el) {
      var row = el.closest('[data-disc-row]');
      var check = row && row.querySelector('[data-disc-check]');
      if (check && !check.checked) return;
      // Не затираем ручной ввод, если уже отличается от прошлой авто-подсказки
      if (el.dataset.auto === '0' && el.value !== '' && el.value !== el.dataset.lastAuto) {
        return;
      }
      el.value = suggested;
      el.dataset.lastAuto = suggested;
      el.dataset.auto = '1';
    });
  }

  document.querySelectorAll('[data-folk-opening]').forEach(function (el) {
    el.dataset.lastAuto = el.value || '';
    el.dataset.auto = '1';
    el.addEventListener('input', function () {
      el.dataset.auto = '0';
    });
  });

  bwInput.addEventListener('input', syncFolk);
})();
</script>
