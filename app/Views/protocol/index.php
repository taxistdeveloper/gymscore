<?php
/**
 * Публичный протокол — только просмотр, без авторизации.
 *
 * @var array $config
 * @var list<array> $disciplines
 * @var array|null $discipline
 * @var list<array> $participants
 * @var array $protocolData
 * @var list<array> $records
 * @var list<array> $normatives
 */

$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$scoring = $discipline['scoring_type'] ?? 'weight';
$code = $discipline['code'] ?? 'bench';
$isFolk = ($code === \App\Models\FolkBench::CODE) || !empty($isFolk);
$isReps = $scoring === 'reps' || $isFolk;
$unit = match (true) {
    $isFolk, $scoring === 'reps' => 'раз',
    $scoring === 'arm' => 'побед',
    default => 'кг',
};

// Народный жим — не путать с обычным bench
$bestColTitle = match (true) {
    $isFolk || $scoring === 'reps' => 'Повторы',
    $code === 'bench' => 'Жим',
    $code === 'curl_strict' || $code === 'curl_extreme' => 'Бицепс',
    $scoring === 'arm' => 'Побед',
    default => 'Лучший',
};

$attemptPrefix = match (true) {
    $isFolk || $scoring === 'reps' => 'П',
    $code === 'bench' => 'Ж',
    $code === 'curl_strict' || $code === 'curl_extreme' => 'Б',
    default => 'П',
};

$colSpan = $isFolk ? 10 : 16;

$categories = $protocolData['categories'] ?? [];
$totalAthletes = $protocolData['total_athletes'] ?? count($participants ?? []);
$judgedAttempts = $protocolData['judged_attempts'] ?? 0;
?>

<section class="judge-shell proto proto--view" data-ptable data-base="<?= htmlspecialchars($base) ?>" data-did="<?= $did ?>">
  <header class="proto-public-bar">
    <a class="proto-public-logo" href="<?= htmlspecialchars($base) ?>/">
      <img src="<?= htmlspecialchars($base) ?>/img/alash-gym-logo.png"
           width="505"
           height="116"
           alt="Alash Gym">
    </a>
    <div class="proto-public-title">
      <strong>Протокол</strong>
      <span>только просмотр</span>
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

    <?php if (!empty($categories)): ?>
      <div class="proto-filter">
        <label class="sr-only" for="catFilter">Весовая категория</label>
        <select class="field field-sm" id="catFilter">
          <option value="all">Все ВК (<?= count($categories) ?>)</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['category_short']) ?>">
              <?= htmlspecialchars($cat['category_short']) ?> · <?= count($cat['athletes']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="proto-modes" role="group" aria-label="Режим таблицы">
        <button type="button" class="proto-mode" id="btnSortByCat">По категориям</button>
        <button type="button" class="proto-mode is-active" id="btnSortByQueue">
          <span class="proto-live-dot" aria-hidden="true"></span> По очереди
        </button>
      </div>

      <div class="judge-search proto-search">
        <label class="sr-only" for="ptableSearch">Поиск атлета</label>
        <input type="search" id="ptableSearch" class="field field-sm" placeholder="Поиск атлета…" autocomplete="off">
      </div>
    <?php endif; ?>

    <p class="judge-toolbar-hint proto-hint">
      <?php if ($discipline): ?>
        <?= (int) $totalAthletes ?> уч. · <?= (int) $judgedAttempts ?> попыток
      <?php endif; ?>
    </p>

    <div class="proto-actions">
      <button type="button" class="btn-secondary-sm" id="btnFullscreen" title="На весь экран" aria-label="На весь экран">⛶</button>
    </div>
  </div>

  <?php if (!$discipline): ?>
    <div class="judge-empty panel">
      <p class="judge-empty-title">Дисциплина не выбрана</p>
    </div>
  <?php elseif (empty($categories)): ?>
    <div class="judge-empty panel">
      <p class="judge-empty-title">Нет участников</p>
      <p class="text-muted mt-2">На «<?= htmlspecialchars($discipline['name_ru']) ?>» пока никто не утверждён.</p>
    </div>
  <?php else: ?>
    <div class="proto-scroll" id="tableScrollWrap">
      <table class="proto-table" id="ptableGrid">
        <thead>
          <tr>
            <th class="proto-col-lot" title="Жребий">Жр</th>
            <th class="proto-col-name text-left">Спортсмен</th>
            <th class="proto-col-team">Команда</th>
            <th class="proto-col-year">Год</th>
            <?php if (!$isFolk): ?>
              <th class="proto-col-rank">Разр</th>
            <?php endif; ?>
            <th class="proto-col-bw">Вес</th>
            <th class="proto-col-cat">ВК</th>
            <th class="proto-col-queue" title="Очередь на помост">Очередь</th>
            <?php if ($isFolk): ?>
              <th class="proto-col-bar" title="Вес на штанге = ½ собственного веса">Штанга</th>
              <th class="proto-col-att" title="Число повторений"><?= htmlspecialchars($bestColTitle) ?></th>
              <th class="proto-col-place">М</th>
            <?php else: ?>
              <th class="proto-col-att"><?= htmlspecialchars($attemptPrefix) ?>1</th>
              <th class="proto-col-att"><?= htmlspecialchars($attemptPrefix) ?>2</th>
              <th class="proto-col-att"><?= htmlspecialchars($attemptPrefix) ?>3</th>
              <th class="proto-col-att" title="Рекордная попытка"><?= htmlspecialchars($attemptPrefix) ?>R</th>
              <th class="proto-col-best"><?= htmlspecialchars($bestColTitle) ?></th>
              <th class="proto-col-sum">Σ</th>
              <th class="proto-col-place">М</th>
              <th class="proto-col-wilks">Коэф</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="ptableTbody">
          <?php foreach ($categories as $catIdx => $catGroup): ?>
            <?php
              $catShort = $catGroup['category_short'];
              $athletesCount = count($catGroup['athletes']);
            ?>
            <tr class="proto-cat-row" data-cat-header="<?= htmlspecialchars($catShort) ?>">
              <td colspan="<?= (int) $colSpan ?>">
                <span class="proto-cat-label"><?= htmlspecialchars($catShort) ?></span>
                <span class="proto-cat-count"><?= $athletesCount ?></span>
              </td>
            </tr>
            <?php foreach ($catGroup['athletes'] as $athIdx => $ath): ?>
              <?php
                $pid = (int) $ath['id'];
                $isOnStage = !empty($ath['is_on_stage']);
                $isNextUp = !empty($ath['is_next_up']);

                $rowClasses = ['proto-row', 'pt-row'];
                if ($athIdx % 2 === 1) $rowClasses[] = 'is-alt';
                if ($isOnStage) $rowClasses[] = 'is-stage';
                if ($isNextUp) $rowClasses[] = 'is-next';
              ?>
              <tr class="<?= implode(' ', $rowClasses) ?>"
                  data-cat="<?= htmlspecialchars($catShort) ?>"
                  data-name="<?= htmlspecialchars(mb_strtolower($ath['full_name'])) ?>"
                  data-pid="<?= $pid ?>"
                  data-queue-pos="<?= (int) ($ath['queue_pos'] ?? 9999) ?>"
                  data-cat-idx="<?= $catIdx ?>"
                  data-ath-idx="<?= $athIdx ?>"
                  id="ath-row-<?= $pid ?>">

                <td class="proto-cell proto-col-lot"><?= htmlspecialchars((string) $ath['lot_number']) ?></td>

                <td class="proto-cell proto-col-name text-left">
                  <div class="proto-name">
                    <strong class="proto-ath"><?= htmlspecialchars(mb_strtoupper($ath['full_name'])) ?></strong>
                    <span class="proto-meta">
                      <?= htmlspecialchars($ath['age_group']) ?>
                      <?php if ($isOnStage): ?>
                        <span class="proto-pill proto-pill--now">на помосте</span>
                      <?php elseif ($isNextUp): ?>
                        <span class="proto-pill proto-pill--next">следующий</span>
                      <?php endif; ?>
                      <?php if ($ath['age_group'] === 'Юниоры'): ?>
                        <span class="proto-pill proto-pill--jr">ю</span>
                      <?php endif; ?>
                    </span>
                  </div>
                </td>

                <td class="proto-cell proto-col-team" title="<?= htmlspecialchars($ath['team']) ?>">
                  <?= htmlspecialchars($ath['team'] ?: '—') ?>
                </td>

                <td class="proto-cell proto-col-year"><?= htmlspecialchars((string) $ath['birth_year']) ?></td>

                <?php if (!$isFolk): ?>
                  <td class="proto-cell proto-col-rank proto-rank-<?= htmlspecialchars(mb_strtolower($ath['sport_rank'])) ?>">
                    <?= htmlspecialchars($ath['sport_rank'] ?: '—') ?>
                  </td>
                <?php endif; ?>

                <td class="proto-cell proto-col-bw">
                  <span class="proto-bw" title="<?= !empty($ath['weighed']) ? 'Взвешен' : 'Не взвешен' ?>">
                    <?= htmlspecialchars($ath['body_weight_fmt']) ?>
                  </span>
                </td>

                <td class="proto-cell proto-col-cat"><strong><?= htmlspecialchars($catShort) ?></strong></td>

                <td class="proto-cell proto-col-queue" data-queue-cell-for="<?= $pid ?>">
                  <?php
                    $qAtt = (int) ($ath['queue_att_no'] ?? 0);
                    $qW = isset($ath['queue_weight']) && $ath['queue_weight'] !== null && $ath['queue_weight'] !== ''
                      ? round((float) $ath['queue_weight'], 1)
                      : null;
                    $qUnit = $isFolk ? 'кг' : $unit;
                    $qDetail = trim(
                      ($isFolk
                        ? ($qW !== null ? $qW . ' ' . $qUnit : '')
                        : (($qAtt > 0 ? $attemptPrefix . $qAtt : '')
                          . ($qW !== null ? ($qAtt > 0 ? ' · ' : '') . $qW . ' ' . $qUnit : '')))
                    );
                  ?>
                  <?php if ($isOnStage): ?>
                    <span class="proto-q proto-q--now" title="Сейчас на помосте<?= $qDetail !== '' ? ': ' . $qDetail : '' ?>">
                      <span class="proto-q-top"><span class="proto-live-dot"></span> ПОМОСТ</span>
                      <?php if ($qDetail !== ''): ?>
                        <span class="proto-q-sub"><?= htmlspecialchars($qDetail) ?></span>
                      <?php endif; ?>
                    </span>
                  <?php elseif ($isNextUp): ?>
                    <span class="proto-q proto-q--next" title="Следующий<?= $qDetail !== '' ? ': ' . $qDetail : '' ?>">
                      <span class="proto-q-top">ДАЛЕЕ</span>
                      <?php if ($qDetail !== ''): ?>
                        <span class="proto-q-sub"><?= htmlspecialchars($qDetail) ?></span>
                      <?php endif; ?>
                    </span>
                  <?php elseif (!empty($ath['queue_pos']) && $ath['queue_pos'] < 9000): ?>
                    <?php if (($ath['queue_status'] ?? '') === 'deferred'): ?>
                      <span class="proto-q proto-q--defer">не пришёл</span>
                    <?php else: ?>
                      <span class="proto-q proto-q--pos" title="Позиция #<?= $ath['queue_pos'] ?>">
                        <span class="proto-q-top">#<?= $ath['queue_pos'] ?></span>
                        <?php if ($qDetail !== ''): ?>
                          <span class="proto-q-sub"><?= htmlspecialchars($qDetail) ?></span>
                        <?php endif; ?>
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>

                <?php if ($isFolk): ?>
                  <td class="proto-cell proto-col-bar" title="½ собственного веса">
                    <strong><?= htmlspecialchars($ath['bar_weight_fmt'] ?? '—') ?></strong>
                    <?php if (!empty($ath['bar_weight'])): ?>
                      <span class="text-muted">кг</span>
                    <?php endif; ?>
                  </td>
                  <?php
                    $att = $ath['attempts'][1] ?? [
                        'no' => 1,
                        'value' => null,
                        'is_valid' => true,
                        'status' => 'empty',
                        'text' => '',
                    ];
                    $st = $att['status'];
                    $cellClass = match ($st) {
                        'valid'      => 'is-good',
                        'invalid'    => 'is-bad',
                        'stage_now'  => 'is-stage-att',
                        'stage_next' => 'is-next-att',
                        'declared'   => 'is-declared',
                        default      => 'is-empty',
                    };
                  ?>
                  <td class="proto-cell proto-col-att <?= $cellClass ?>"
                      data-pid="<?= $pid ?>"
                      data-att-no="1"
                      data-status="<?= $st ?>"
                      id="cell-<?= $pid ?>-1">
                    <span class="pt-att-val"><?= $att['text'] !== '' ? htmlspecialchars($att['text']) : '—' ?></span>
                  </td>
                  <td class="proto-cell proto-col-place">
                    <?php if ((int) $ath['place'] === 1): ?>
                      <span class="proto-place is-1">1</span>
                    <?php elseif ((int) $ath['place'] === 2): ?>
                      <span class="proto-place is-2">2</span>
                    <?php elseif ((int) $ath['place'] === 3): ?>
                      <span class="proto-place is-3">3</span>
                    <?php else: ?>
                      <?= htmlspecialchars((string) $ath['place']) ?>
                    <?php endif; ?>
                  </td>
                <?php else: ?>
                  <?php for ($attNo = 1; $attNo <= 4; $attNo++): ?>
                    <?php
                      $att = $ath['attempts'][$attNo] ?? [
                          'no' => $attNo,
                          'value' => null,
                          'is_valid' => true,
                          'status' => 'empty',
                          'text' => '',
                      ];
                      $st = $att['status'];
                      $cellClass = match ($st) {
                          'valid'      => 'is-good',
                          'invalid'    => 'is-bad',
                          'stage_now'  => 'is-stage-att',
                          'stage_next' => 'is-next-att',
                          'declared'   => 'is-declared',
                          default      => 'is-empty',
                      };
                    ?>
                    <td class="proto-cell proto-col-att <?= $cellClass ?>"
                        data-pid="<?= $pid ?>"
                        data-att-no="<?= $attNo ?>"
                        data-status="<?= $st ?>"
                        id="cell-<?= $pid ?>-<?= $attNo ?>">
                      <span class="pt-att-val"><?= $att['text'] !== '' ? htmlspecialchars($att['text']) : '—' ?></span>
                    </td>
                  <?php endfor; ?>

                  <td class="proto-cell proto-col-best">
                    <strong><?= htmlspecialchars($ath['best_fmt']) ?></strong>
                  </td>
                  <td class="proto-cell proto-col-sum">
                    <strong><?= htmlspecialchars($ath['sum_fmt']) ?></strong>
                  </td>
                  <td class="proto-cell proto-col-place">
                    <?php if ((int) $ath['place'] === 1): ?>
                      <span class="proto-place is-1">1</span>
                    <?php elseif ((int) $ath['place'] === 2): ?>
                      <span class="proto-place is-2">2</span>
                    <?php elseif ((int) $ath['place'] === 3): ?>
                      <span class="proto-place is-3">3</span>
                    <?php else: ?>
                      <?= htmlspecialchars((string) $ath['place']) ?>
                    <?php endif; ?>
                  </td>
                  <td class="proto-cell proto-col-wilks">
                    <?= htmlspecialchars($ath['wilks_fmt']) ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$isFolk): ?>
    <div class="proto-refs">
      <details class="proto-ref panel">
        <summary class="proto-ref-head">
          <span class="proto-ref-title">Рекорды</span>
          <span class="proto-ref-chevron" aria-hidden="true"></span>
        </summary>
        <div class="proto-ref-scroll">
          <table class="table-ag table-sm">
            <thead>
              <tr>
                <th>Возраст</th>
                <th>Дисциплина</th>
                <th>ВК</th>
                <th>Уровень</th>
                <th class="text-right">Рекорд</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['age_group']) ?></td>
                  <td><?= htmlspecialchars($r['discipline']) ?></td>
                  <td><strong><?= htmlspecialchars($r['category']) ?></strong></td>
                  <td><?= htmlspecialchars($r['level']) ?> (<?= htmlspecialchars($r['country']) ?>)</td>
                  <td class="text-right"><strong><?= htmlspecialchars($r['record']) ?> кг</strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>

      <details class="proto-ref panel">
        <summary class="proto-ref-head">
          <span class="proto-ref-head-text">
            <span class="proto-ref-title">Разрядные нормативы</span>
            <span class="proto-ref-sub"><?= htmlspecialchars($discipline['name_ru']) ?></span>
          </span>
          <span class="proto-ref-chevron" aria-hidden="true"></span>
        </summary>
        <div class="proto-ref-scroll">
          <table class="table-ag table-sm">
            <thead>
              <tr>
                <th>Категория</th>
                <th>МСМК</th>
                <th>МС</th>
                <th>КМС</th>
                <th>1</th>
                <th>2</th>
                <th>3</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($normatives as $n): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($n['category']) ?></strong></td>
                  <td><?= number_format($n['msmk'], 1, ',', '') ?></td>
                  <td><?= number_format($n['ms'], 1, ',', '') ?></td>
                  <td><?= number_format($n['kms'], 1, ',', '') ?></td>
                  <td><?= number_format($n['r1'], 1, ',', '') ?></td>
                  <td><?= number_format($n['r2'], 1, ',', '') ?></td>
                  <td><?= number_format($n['r3'], 1, ',', '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<script>
(function() {
  const container = document.querySelector('[data-ptable]');
  const base = container ? container.dataset.base : '';
  const did = container ? container.dataset.did : '';
  const tbody = document.getElementById('ptableTbody');
  const attPrefix = <?= json_encode($attemptPrefix, JSON_UNESCAPED_UNICODE) ?>;
  const unitLabel = <?= json_encode($unit, JSON_UNESCAPED_UNICODE) ?>;
  const isFolkMode = <?= $isFolk ? 'true' : 'false' ?>;
  const queueUnitLabel = isFolkMode ? 'кг' : unitLabel;

  let currentSortMode = 'queue';
  let lastRankSig = '';
  const btnSortByCat = document.getElementById('btnSortByCat');
  const btnSortByQueue = document.getElementById('btnSortByQueue');

  function syncCatHeaders(mode) {
    document.querySelectorAll('.proto-cat-row').forEach(row => {
      row.style.display = mode === 'queue' ? 'none' : '';
    });
  }

  function sortTableRows(mode) {
    if (!tbody) return;
    currentSortMode = mode;
    const rows = Array.from(tbody.querySelectorAll('.pt-row'));

    if (mode === 'queue') {
      btnSortByQueue?.classList.add('is-active');
      btnSortByCat?.classList.remove('is-active');
      rows.sort((a, b) => {
        const qa = parseInt(a.dataset.queuePos || '9999', 10);
        const qb = parseInt(b.dataset.queuePos || '9999', 10);
        if (qa !== qb) return qa - qb;
        const la = parseInt(a.querySelector('.proto-col-lot')?.textContent || '999', 10);
        const lb = parseInt(b.querySelector('.proto-col-lot')?.textContent || '999', 10);
        return la - lb;
      });
      rows.forEach(r => tbody.appendChild(r));
      syncCatHeaders('queue');
    } else {
      btnSortByCat?.classList.add('is-active');
      btnSortByQueue?.classList.remove('is-active');
      const headers = Array.from(tbody.querySelectorAll('.proto-cat-row'));
      const byCat = {};
      rows.sort((a, b) => {
        const ca = parseInt(a.dataset.catIdx || '0', 10);
        const cb = parseInt(b.dataset.catIdx || '0', 10);
        if (ca !== cb) return ca - cb;
        return parseInt(a.dataset.athIdx || '0', 10) - parseInt(b.dataset.athIdx || '0', 10);
      });
      headers.forEach(h => { byCat[h.dataset.catHeader] = h; });
      tbody.innerHTML = '';
      let lastCat = null;
      rows.forEach(r => {
        const cat = r.dataset.cat;
        if (cat !== lastCat && byCat[cat]) {
          tbody.appendChild(byCat[cat]);
          lastCat = cat;
        }
        tbody.appendChild(r);
      });
      syncCatHeaders('category');
    }
  }

  btnSortByCat?.addEventListener('click', () => sortTableRows('category'));
  btnSortByQueue?.addEventListener('click', () => sortTableRows('queue'));
  sortTableRows('queue');

  function queueDetail(item) {
    if (!item) return '';
    const w = item.declared_weight != null && item.declared_weight !== ''
      ? (parseFloat(item.declared_weight) + ' ' + queueUnitLabel)
      : '';
    if (isFolkMode) return w;
    const att = item.attempt_no ? (attPrefix + item.attempt_no) : '';
    return [att, w].filter(Boolean).join(' · ');
  }

  function queueBadgeHtml(kind, detail, pos) {
    if (kind === 'now') {
      return '<span class="proto-q proto-q--now" title="' + (detail ? ('Сейчас: ' + detail) : 'На помосте') + '">'
        + '<span class="proto-q-top"><span class="proto-live-dot"></span> ПОМОСТ</span>'
        + (detail ? '<span class="proto-q-sub">' + detail + '</span>' : '')
        + '</span>';
    }
    if (kind === 'next') {
      return '<span class="proto-q proto-q--next" title="' + (detail ? ('Далее: ' + detail) : 'Следующий') + '">'
        + '<span class="proto-q-top">ДАЛЕЕ</span>'
        + (detail ? '<span class="proto-q-sub">' + detail + '</span>' : '')
        + '</span>';
    }
    if (kind === 'defer') {
      return '<span class="proto-q proto-q--defer">не пришёл</span>';
    }
    return '<span class="proto-q proto-q--pos">'
      + '<span class="proto-q-top">#' + pos + '</span>'
      + (detail ? '<span class="proto-q-sub">' + detail + '</span>' : '')
      + '</span>';
  }

  async function pollLive() {
    if (!did) return;
    try {
      const resp = await fetch(base + '/live/data?discipline=' + did, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (!resp.ok) return;
      const data = await resp.json();
      if (!data || !data.ok) return;

      const rankSig = JSON.stringify((data.ranking || []).slice(0, 12).map(i => [i.place, i.full_name, i.best_result]));
      if (lastRankSig && lastRankSig !== rankSig) {
        window.location.reload();
        return;
      }
      lastRankSig = rankSig;

      const cur = data.current;
      const fullQueue = Array.isArray(data.queue) && data.queue.length
        ? data.queue
        : (data.upcoming || []);
      const qPosMap = {};

      if (cur && cur.participant_id) {
        qPosMap[String(cur.participant_id)] = {
          status: 'now',
          detail: queueDetail(cur),
          absPos: 0
        };
      }

      fullQueue.forEach((item, idx) => {
        const pid = String(item.participant_id || '');
        if (!pid || qPosMap[pid]) return;
        const absPos = item.pos != null ? parseInt(item.pos, 10) : (idx + 1);
        qPosMap[pid] = {
          status: item.deferred ? 'defer' : 'item',
          detail: queueDetail(item),
          absPos: absPos
        };
      });

      let nextPid = null;
      let nextAbs = 9999;
      Object.keys(qPosMap).forEach(pid => {
        const info = qPosMap[pid];
        if (info.status === 'now' || info.status === 'defer') return;
        if (info.absPos < nextAbs) {
          nextAbs = info.absPos;
          nextPid = pid;
        }
      });
      if (nextPid && qPosMap[nextPid]) qPosMap[nextPid].status = 'next';

      document.querySelectorAll('.pt-row').forEach(row => {
        const pid = String(row.dataset.pid || '');
        const qInfo = qPosMap[pid];
        row.classList.remove('is-stage', 'is-next');
        const queueCell = row.querySelector('.proto-col-queue');
        const nameMeta = row.querySelector('.proto-meta');
        if (nameMeta) {
          nameMeta.querySelectorAll('.proto-pill--now, .proto-pill--next').forEach(el => el.remove());
        }

        if (qInfo) {
          const sortPos = qInfo.status === 'now' ? 0 : (qInfo.status === 'next' ? 1 : qInfo.absPos + 100);
          row.dataset.queuePos = String(sortPos);

          if (qInfo.status === 'now') {
            row.classList.add('is-stage');
            if (queueCell) queueCell.innerHTML = queueBadgeHtml('now', qInfo.detail);
            if (nameMeta) nameMeta.insertAdjacentHTML('beforeend', '<span class="proto-pill proto-pill--now">на помосте</span>');
          } else if (qInfo.status === 'next') {
            row.classList.add('is-next');
            if (queueCell) queueCell.innerHTML = queueBadgeHtml('next', qInfo.detail);
            if (nameMeta) nameMeta.insertAdjacentHTML('beforeend', '<span class="proto-pill proto-pill--next">следующий</span>');
          } else if (queueCell) {
            queueCell.innerHTML = queueBadgeHtml(
              qInfo.status === 'defer' ? 'defer' : 'pos',
              qInfo.detail,
              qInfo.absPos
            );
          }
        } else {
          row.dataset.queuePos = '9999';
          if (queueCell) queueCell.innerHTML = '<span class="text-muted">—</span>';
        }
      });

      if (currentSortMode === 'queue') sortTableRows('queue');
    } catch (e) { /* ignore */ }
  }

  setInterval(pollLive, 3500);
  pollLive();

  document.getElementById('catFilter')?.addEventListener('change', (e) => {
    const val = e.target.value;
    document.querySelectorAll('.pt-row').forEach(row => {
      row.style.display = (val === 'all' || row.dataset.cat === val) ? '' : 'none';
    });
    document.querySelectorAll('.proto-cat-row').forEach(row => {
      row.style.display = (val === 'all' || row.dataset.catHeader === val) ? '' : 'none';
    });
  });

  document.getElementById('ptableSearch')?.addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('.pt-row').forEach(row => {
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
