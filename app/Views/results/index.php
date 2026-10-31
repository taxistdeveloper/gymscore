<?php
$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$scoring = $discipline['scoring_type'] ?? 'weight';
$isFolk = ($discipline['code'] ?? '') === \App\Models\FolkBench::CODE;
$unit = match ($scoring) {
    'reps' => 'раз',
    'arm'  => 'побед',
    default => 'кг',
};
$resultLabel = $isFolk || $scoring === 'reps' ? 'Поднял' : 'Результат';
$protocol = $protocol ?? [];
$catParam = isset($_GET['cat']) ? (string) $_GET['cat'] : '';
$activeCat = 0;

if ($catParam !== '' && preg_match('/^\d+$/', $catParam)) {
    $idx = (int) $catParam;
    if (isset($protocol[$idx])) {
        $activeCat = $idx;
    }
}

function place_class(int $place): string {
    return match ($place) {
        1 => 'place-1',
        2 => 'place-2',
        3 => 'place-3',
        default => '',
    };
}

function fmt_result(float $v, string $scoring): string {
    return $scoring === 'weight'
        ? number_format($v, 1, '.', '')
        : number_format($v, 0, '.', '');
}

?>

<section class="results" data-results>
  <header class="results-head">
    <div>
      <h2 class="results-title">Протокол</h2>
      <p class="results-sub">Места по категориям · при равенстве — меньше с/вес</p>
    </div>
    <div class="results-head-actions">
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/live?discipline=<?= $did ?>">Live</a>
      <?php if ($did > 0): ?>
        <a class="btn-primary-sm"
           href="<?= htmlspecialchars($base) ?>/results/pdf?discipline=<?= $did ?>&auto=1"
           target="_blank"
           rel="noopener">PDF</a>
      <?php endif; ?>
      <a class="btn-secondary-sm"
         href="<?= htmlspecialchars($base) ?>/results/pdf?all=1&auto=1"
         target="_blank"
         rel="noopener">PDF все</a>
    </div>
  </header>

  <div class="results-filters">
    <div class="results-filter-block">
      <span class="results-filter-label">Дисциплина</span>
      <nav class="results-discs" aria-label="Дисциплина">
        <?php foreach ($disciplines as $d): ?>
          <?php
            $id = (int) $d['id'];
            $on = $id === $did;
          ?>
          <a class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"
             href="<?= htmlspecialchars($base) ?>/results?discipline=<?= $id ?>"
             <?= $on ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($d['name_ru']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!empty($protocol)): ?>
      <div class="results-filter-block">
        <span class="results-filter-label">Весовая</span>
        <div class="results-cats" role="tablist" aria-label="Весовая категория">
          <?php foreach ($protocol as $i => $group): ?>
            <?php
              $on = $i === $activeCat;
              $n = count($group['rows']);
            ?>
            <button type="button"
                    class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"
                    role="tab"
                    id="results-tab-<?= $i ?>"
                    aria-selected="<?= $on ? 'true' : 'false' ?>"
                    aria-controls="results-panel-<?= $i ?>"
                    data-results-cat="<?= $i ?>"
                    <?= $on ? 'tabindex="0"' : 'tabindex="-1"' ?>>
              <?= htmlspecialchars($group['category']) ?>
              <span class="pill-count <?= $on ? 'bg-white/20 text-white' : 'bg-wash text-muted' ?>"><?= $n ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($protocol)): ?>
    <div class="panel results-empty">
      <p class="mb-0 text-muted">
        Пока нет зачётных попыток по «<?= htmlspecialchars($discipline['name_ru'] ?? '') ?>».
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($protocol as $i => $group): ?>
      <?php $on = $i === $activeCat; ?>
      <section class="panel results-panel"
               id="results-panel-<?= $i ?>"
               role="tabpanel"
               aria-labelledby="results-tab-<?= $i ?>"
               data-results-panel="<?= $i ?>"
               <?= $on ? '' : 'hidden' ?>>
        <header class="results-cat-head">
          <h3><?= htmlspecialchars($group['category']) ?></h3>
        </header>

        <div class="overflow-x-auto">
          <table class="table-ag results-table">
            <thead>
              <tr>
                <th class="results-col-place">Место</th>
                <th>Участник</th>
                <th><?= htmlspecialchars($resultLabel) ?></th>
                <th>С/вес</th>
                <th>Жребий</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($group['rows'] as $row): ?>
                <?php $pl = (int) $row['place']; ?>
                <tr class="<?= $pl <= 3 ? 'results-prize results-prize-' . $pl : '' ?>">
                  <td class="results-col-place">
                    <span class="results-place <?= place_class($pl) ?>"><?= $pl ?></span>
                  </td>
                  <td class="font-semibold"><?= htmlspecialchars($row['full_name']) ?></td>
                  <td>
                    <span class="results-score"><?= htmlspecialchars(fmt_result((float) $row['best_result'], $scoring)) ?></span>
                    <span class="text-muted"><?= htmlspecialchars($unit) ?></span>
                  </td>
                  <td class="text-muted"><?= number_format((float) $row['body_weight'], 2, '.', '') ?> кг</td>
                  <td class="text-muted">
                    <?= !empty($row['lot_number']) ? '#' . (int) $row['lot_number'] : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<script>
(function () {
  var root = document.querySelector('[data-results]');
  if (!root) return;

  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-results-cat]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-results-panel]'));
  if (!tabs.length) return;

  function setActive(index, pushUrl) {
    tabs.forEach(function (tab) {
      var on = Number(tab.getAttribute('data-results-cat')) === index;
      tab.classList.toggle('pill-on', on);
      tab.classList.toggle('pill-off', !on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      var count = tab.querySelector('.pill-count');
      if (count) {
        count.className = 'pill-count ' + (on ? 'bg-white/20 text-white' : 'bg-wash text-muted');
      }
    });

    panels.forEach(function (panel) {
      panel.hidden = Number(panel.getAttribute('data-results-panel')) !== index;
    });

    if (pushUrl) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('cat', String(index));
        history.replaceState(null, '', url.toString());
      } catch (e) {}
    }
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      setActive(Number(tab.getAttribute('data-results-cat')), true);
    });
  });
})();
</script>
