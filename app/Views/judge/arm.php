<?php
/**
 * Совместимость: /judge/arm → протокол армрестлинга с открытой модалкой.
 * Реальный UI судейства — модалка на /judge/protocol.
 */
$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$href = $base . '/judge/protocol?discipline=' . $did . '&open_arm=1';
?>
<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($href) ?>">
<p class="panel" style="margin:2rem auto;max-width:28rem;text-align:center">
  Переход к судейству армрестлинга…
  <br>
  <a class="btn-primary-sm mt-3" href="<?= htmlspecialchars($href) ?>">Открыть</a>
</p>
