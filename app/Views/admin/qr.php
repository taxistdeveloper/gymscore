<?php
$base = $config['base_url'] ?? '';
$defaultUrl = $defaultUrl ?? '';
$presets = $presets ?? [];
$qrJsV = @filemtime(dirname(__DIR__, 3) . '/public/js/qrcode.min.js') ?: 1;
$adminQrV = @filemtime(dirname(__DIR__, 3) . '/public/js/admin-qr.js') ?: 1;
?>

<div class="mx-auto w-full max-w-2xl">
  <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
      <h1 class="mb-1 text-2xl font-semibold">QR-код</h1>
      <p class="text-sm text-muted"><?= htmlspecialchars($adminName ?? 'Админ') ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin">Заявки</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/admin/users">Юзеры</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <label class="field-label" for="qrUrl">Ссылка</label>
      <input class="field w-full" id="qrUrl" type="url" inputmode="url" autocomplete="off"
             value="<?= htmlspecialchars($defaultUrl) ?>"
             placeholder="https://…">

      <?php if ($presets): ?>
        <div class="admin-qr-presets" role="group" aria-label="Быстрые ссылки">
          <?php foreach ($presets as $preset): ?>
            <button class="btn-secondary-sm" type="button" data-qr-preset="<?= htmlspecialchars($preset['url']) ?>">
              <?= htmlspecialchars($preset['label']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body admin-qr-preview">
      <canvas id="qrCanvas" width="320" height="320" aria-label="QR-код"></canvas>
      <p class="admin-qr-hint text-sm text-muted" id="qrHint">Вставьте ссылку — QR обновится сам</p>
      <div class="admin-qr-actions">
        <button class="btn-primary-sm" type="button" id="qrDownload" disabled>Скачать PNG</button>
        <button class="btn-secondary-sm" type="button" id="qrPrint" disabled>Печать</button>
        <button class="btn-secondary-sm" type="button" id="qrCopy" disabled>Копировать ссылку</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars($base) ?>/js/qrcode.min.js?v=<?= (int) $qrJsV ?>"></script>
<script src="<?= htmlspecialchars($base) ?>/js/admin-qr.js?v=<?= (int) $adminQrV ?>"></script>
