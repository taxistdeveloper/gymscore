<?php
$base = $config['base_url'] ?? '';
$p = $participant;
$selected = $selected ?? [];
$statuses = $statuses ?? [];
$pid = (int) $p['id'];
$st = $p['status'] ?? 'pending';
$stLabel = $statuses[$st] ?? $st;
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
        <h2 class="mb-3 text-lg font-semibold">Дисциплины</h2>
        <div class="flex flex-col gap-2">
          <?php foreach ($disciplines as $d): ?>
            <?php $did = (int) $d['id']; ?>
            <div class="flex items-center gap-2">
              <input class="check" type="checkbox"
                     name="disciplines[]" value="<?= $did ?>"
                     id="d<?= $did ?>"
                     <?= in_array($did, $selected, true) ? 'checked' : '' ?>>
              <label for="d<?= $did ?>"><?= htmlspecialchars($d['name_ru']) ?></label>
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
