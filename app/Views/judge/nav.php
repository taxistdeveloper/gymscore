<?php
/** Shared judge toolbar. Expects $judgeNav, optional $judgeDid / $did, $judgeName. */
$base = $config['base_url'] ?? '';
$judgeNav = $judgeNav ?? 'platform';
$judgeDid = (int) ($judgeDid ?? $did ?? 0);
$judgeName = $judgeName ?? 'Судья';
$discQs = $judgeDid > 0 ? '?discipline=' . $judgeDid : '';

$modes = [
    'platform' => ['Помост', $base . '/judge' . $discQs],
    'weigh'    => ['Взвешивание', $base . '/judge/weigh-in'],
    'protocol' => ['Протокол', $base . '/judge/protocol' . $discQs],
    // /judge/arm → протокол армрестлинга с открытой модалкой судейства
    'arm'      => ['Арм', $base . '/judge/arm'],
];
?>
<header class="judge-bar">
  <div class="judge-bar-brand">
    <span class="judge-bar-mark" aria-hidden="true"></span>
    <div>
      <strong class="judge-bar-title">Судейская</strong>
      <span class="judge-bar-who"><?= htmlspecialchars($judgeName) ?></span>
    </div>
  </div>

  <nav class="judge-bar-modes" aria-label="Разделы судейской">
    <?php foreach ($modes as $key => [$label, $href]): ?>
      <?php $on = $judgeNav === $key; ?>
      <a class="judge-mode<?= $on ? ' is-on' : '' ?>"
         href="<?= htmlspecialchars($href) ?>"
         <?= $on ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="judge-bar-actions">
    <?php if ($judgeDid > 0): ?>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/live?discipline=<?= $judgeDid ?>"
         target="_blank" rel="noopener">Live</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/results?discipline=<?= $judgeDid ?>">Места</a>
    <?php endif; ?>
    <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/judge/logout">Выйти</a>
  </div>
</header>
