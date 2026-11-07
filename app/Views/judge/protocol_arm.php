<?php
/**
 * Судейский протокол армрестлинга — таблица мест и боёв + модалка судейства.
 *
 * @var array $config
 * @var list<array> $disciplines
 * @var array|null $discipline
 * @var array $armProtocol
 * @var array $byCategory
 * @var array $wins
 * @var string $judgeName
 */

$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$totalAthletes = (int) ($armProtocol['total_athletes'] ?? 0);
$doneMatches = (int) ($armProtocol['done_matches'] ?? 0);
$totalMatches = (int) ($armProtocol['total_matches'] ?? 0);
$openArm = !empty($_GET['open_arm']);
$byCategory = $byCategory ?? [];
$wins = $wins ?? [];

$judgeNav = 'protocol';
$judgeDid = $did;
require __DIR__ . '/nav.php';
?>

<section class="judge-shell proto proto--arm" data-base="<?= htmlspecialchars($base) ?>" data-did="<?= $did ?>">
  <div class="judge-toolbar proto-toolbar">
    <form method="get" action="<?= htmlspecialchars($base) ?>/judge/protocol" class="judge-disc" id="discForm">
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
      <button type="button" class="btn-primary-sm" id="btnOpenArmJudge">Судить арм</button>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/judge/protocol?discipline=<?= $did ?>">Обновить</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/results?discipline=<?= $did ?>" target="_blank" rel="noopener">Места</a>
      <a class="btn-primary-sm" href="<?= htmlspecialchars($base) ?>/live?discipline=<?= $did ?>" target="_blank" rel="noopener">Live</a>
      <button type="button" class="btn-secondary-sm" id="btnFullscreen" title="На весь экран" aria-label="На весь экран">⛶</button>
    </div>
  </div>

  <?php
    $isJudge = true;
    require __DIR__ . '/../partials/arm_protocol_table.php';
  ?>
</section>

<div class="proto-stage-backdrop<?= $openArm ? ' is-open' : '' ?>" id="armJudgeModal" aria-hidden="<?= $openArm ? 'false' : 'true' ?>">
  <div class="proto-stage-dialog arm-judge-dialog" role="dialog" aria-modal="true" aria-labelledby="armJudgeTitle">
    <header class="proto-stage-dialog-head arm-judge-dialog-head">
      <div>
        <h3 class="proto-stage-dialog-title" id="armJudgeTitle">Армрестлинг</h3>
        <p class="proto-stage-dialog-sub">Судейский стол · пары и победы</p>
      </div>
      <button type="button" class="proto-stage-dialog-close" id="btnCloseArmJudge" aria-label="Закрыть">×</button>
    </header>
    <div class="proto-stage-dialog-body arm-judge-dialog-body">
      <?php
        $returnTo = 'protocol';
        require __DIR__ . '/../partials/arm_judge_board.php';
      ?>
    </div>
  </div>
</div>

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

  const modal = document.getElementById('armJudgeModal');
  const openBtn = document.getElementById('btnOpenArmJudge');
  const closeBtn = document.getElementById('btnCloseArmJudge');

  function openArm() {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('proto-stage-lock');
  }

  function closeArm() {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('proto-stage-lock');
    const url = new URL(window.location.href);
    if (url.searchParams.has('open_arm')) {
      url.searchParams.delete('open_arm');
      window.history.replaceState({}, '', url.pathname + url.search);
    }
  }

  openBtn?.addEventListener('click', openArm);
  closeBtn?.addEventListener('click', closeArm);
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeArm();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal?.classList.contains('is-open')) closeArm();
  });

  if (modal?.classList.contains('is-open')) {
    document.documentElement.classList.add('proto-stage-lock');
  }
})();
</script>
