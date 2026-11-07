<?php
/**
 * Публичный протокол армрестлинга — таблица мест и боёв.
 *
 * @var array $config
 * @var list<array> $disciplines
 * @var array|null $discipline
 * @var array $armProtocol
 * @var callable $t
 */

$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$totalAthletes = (int) ($armProtocol['total_athletes'] ?? 0);
$doneMatches = (int) ($armProtocol['done_matches'] ?? 0);
$totalMatches = (int) ($armProtocol['total_matches'] ?? 0);
?>

<section class="judge-shell proto proto--view proto--arm" data-base="<?= htmlspecialchars($base) ?>" data-did="<?= $did ?>">
  <header class="proto-public-bar">
    <a class="proto-public-logo" href="<?= htmlspecialchars($base) ?>/">
      <img src="<?= htmlspecialchars($base) ?>/img/alash-gym-logo.png"
           width="505"
           height="116"
           alt="Alash Gym">
    </a>
    <div class="proto-public-title">
      <strong>Протокол</strong>
      <span>армрестлинг · только просмотр</span>
    </div>
    <div class="proto-public-tools">
      <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="<?= htmlspecialchars($t('theme.toggle')) ?>">
        <span class="theme-toggle-track" aria-hidden="true"><span class="theme-toggle-knob"></span></span>
        <span class="theme-toggle-text"><span class="theme-label-light"><?= htmlspecialchars($t('theme.light')) ?></span><span class="theme-label-dark"><?= htmlspecialchars($t('theme.dark')) ?></span></span>
      </button>
    </div>
  </header>

  <div class="judge-toolbar proto-toolbar">
    <form method="get" action="<?= htmlspecialchars($base) ?>/protocol" class="judge-disc" id="discForm">
      <label class="sr-only" for="ptableDiscipline">Дисциплина</label>
      <select class="field field-sm" id="ptableDiscipline" name="discipline" onchange="this.form.submit()">
        <?php foreach ($disciplines as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $did ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name_ru']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="judge-search proto-search">
      <label class="sr-only" for="ptableSearch">Поиск атлета</label>
      <input type="search" id="ptableSearch" class="field field-sm" placeholder="Поиск атлета…" autocomplete="off">
    </div>

    <p class="judge-toolbar-hint proto-hint">
      <?= $totalAthletes ?> уч. · <?= $doneMatches ?>/<?= $totalMatches ?> боёв
    </p>

    <div class="proto-actions">
      <button type="button" class="btn-secondary-sm" id="btnFullscreen" title="На весь экран" aria-label="На весь экран">⛶</button>
    </div>
  </div>

  <?php
    $isJudge = false;
    require __DIR__ . '/../partials/arm_protocol_table.php';
  ?>
</section>

<script>
(function () {
  document.getElementById('ptableSearch')?.addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('#armStandings .pt-row').forEach(row => {
      const name = row.dataset.name || '';
      row.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });
  document.getElementById('btnFullscreen')?.addEventListener('click', () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
  });
})();
</script>
