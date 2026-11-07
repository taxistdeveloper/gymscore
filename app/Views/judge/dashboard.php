<?php
/**
 * Судейский протокол — плотная рабочая таблица в стиле Alash Gym.
 *
 * @var array $config
 * @var list<array> $disciplines
 * @var array|null $discipline
 * @var list<array> $participants
 * @var array<int, array<int, array>> $attemptsMap
 * @var array $protocolData
 * @var list<array> $records
 * @var list<array> $normatives
 * @var list<array> $queue
 * @var array|null $current
 * @var array|null $flash
 * @var string $judgeName
 */

$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$scoring = $discipline['scoring_type'] ?? 'weight';
$code = $discipline['code'] ?? 'bench';
$isFolk = ($code === \App\Models\FolkBench::CODE);
$isReps = $scoring === 'reps' || $isFolk;
$unit = match (true) {
    $isFolk, $scoring === 'reps' => 'раз',
    $scoring === 'arm' => 'побед',
    default => 'кг',
};

// Народный жим — не путать с обычным bench (str_contains('bench') ловил bench_folk)
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

$judgeNav = 'protocol';
$judgeDid = $did;
require __DIR__ . '/nav.php';

$queue = $queue ?? [];
$current = $current ?? null;
$bar = $bar ?? null;
$category = $category ?? null;
$attemptsAllowed = (int) ($attemptsAllowed ?? ($discipline['attempts_allowed'] ?? 3));
$isFolk = !empty($isFolk) || ($code === \App\Models\FolkBench::CODE);
$isReps = $isReps || $isFolk;
$orderPid = (int) ($orderPid ?? 0);
$orderAttempt = (int) ($orderAttempt ?? 0);
$orderBase = $orderBase ?? null;
$orderName = $orderName ?? '';
$categories = $protocolData['categories'] ?? [];
$totalAthletes = $protocolData['total_athletes'] ?? count($participants);
$judgedAttempts = $protocolData['judged_attempts'] ?? 0;
?>

<section class="judge-shell proto" data-ptable data-base="<?= htmlspecialchars($base) ?>" data-did="<?= $did ?>">
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
      <?php if ($discipline): ?>
        <?php require __DIR__ . '/protocol_stage.php'; ?>
      <?php endif; ?>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/judge/protocol?discipline=<?= $did ?>">Обновить</a>
      <a class="btn-secondary-sm" href="<?= htmlspecialchars($base) ?>/results?discipline=<?= $did ?>" target="_blank" rel="noopener">Места</a>
      <a class="btn-primary-sm" href="<?= htmlspecialchars($base) ?>/live?discipline=<?= $did ?>" target="_blank" rel="noopener">Live</a>
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
                  <td class="proto-cell proto-col-rank proto-rank-<?= htmlspecialchars(mb_strtolower($ath['sport_rank'])) ?>"
                      data-rank-for="<?= $pid ?>">
                    <?= htmlspecialchars($ath['sport_rank'] ?: '—') ?>
                  </td>
                <?php endif; ?>

                <td class="proto-cell proto-col-bw">
                  <a href="<?= htmlspecialchars($base) ?>/judge/weigh-in/<?= $pid ?>"
                     class="proto-bw"
                     title="<?= $ath['weighed'] ? 'Взвешен' : 'Не взвешен' ?>">
                    <?= htmlspecialchars($ath['body_weight_fmt']) ?>
                  </a>
                </td>

                <td class="proto-cell proto-col-cat"><strong><?= htmlspecialchars($catShort) ?></strong></td>

                <td class="proto-cell proto-col-queue" data-queue-cell-for="<?= $pid ?>">
                  <?php
                    $qAtt = (int) ($ath['queue_att_no'] ?? 0);
                    $qW = isset($ath['queue_weight']) && $ath['queue_weight'] !== null && $ath['queue_weight'] !== ''
                      ? round((float) $ath['queue_weight'], 1)
                      : null;
                    // Народный: в очереди показываем кг на штанге, не «раз»
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
                    <?php if ($ath['queue_status'] === 'deferred'): ?>
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
                    $attNo = 1;
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
                    $folkVal = $att['value'] !== null
                      ? (string) (int) round((float) $att['value'])
                      : '';
                  ?>
                  <td class="proto-cell proto-col-att <?= $cellClass ?>"
                      data-att-cell
                      data-pid="<?= $pid ?>"
                      data-name="<?= htmlspecialchars($ath['full_name']) ?>"
                      data-did="<?= $did ?>"
                      data-cat="<?= htmlspecialchars($catShort) ?>"
                      data-bw="<?= htmlspecialchars((string)($ath['body_weight'] ?? '')) ?>"
                      data-att-no="<?= $attNo ?>"
                      data-val="<?= htmlspecialchars($folkVal) ?>"
                      data-valid="<?= $att['is_valid'] ? '1' : '0' ?>"
                      data-status="<?= $st ?>"
                      id="cell-<?= $pid ?>-<?= $attNo ?>"
                      title="Судить повторы">
                    <span class="pt-att-val"><?= $att['text'] !== '' ? htmlspecialchars($att['text']) : '—' ?></span>
                  </td>
                  <td class="proto-cell proto-col-place" data-place-for="<?= $pid ?>">
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
                        data-att-cell
                        data-pid="<?= $pid ?>"
                        data-name="<?= htmlspecialchars($ath['full_name']) ?>"
                        data-did="<?= $did ?>"
                        data-cat="<?= htmlspecialchars($catShort) ?>"
                        data-bw="<?= htmlspecialchars((string)($ath['body_weight'] ?? '')) ?>"
                        data-att-no="<?= $attNo ?>"
                        data-val="<?= $att['value'] !== null ? htmlspecialchars((string)$att['value']) : ($st === 'stage_now' || $st === 'stage_next' || $st === 'declared' ? htmlspecialchars((string)($ath['queue_weight'] ?? '')) : '') ?>"
                        data-valid="<?= $att['is_valid'] ? '1' : '0' ?>"
                        data-status="<?= $st ?>"
                        id="cell-<?= $pid ?>-<?= $attNo ?>"
                        title="Судить попытку <?= $attNo ?>">
                      <span class="pt-att-val"><?= $att['text'] !== '' ? htmlspecialchars($att['text']) : '—' ?></span>
                    </td>
                  <?php endfor; ?>

                  <td class="proto-cell proto-col-best" data-best-for="<?= $pid ?>">
                    <strong><?= htmlspecialchars($ath['best_fmt']) ?></strong>
                  </td>
                  <td class="proto-cell proto-col-sum" data-sum-for="<?= $pid ?>">
                    <strong><?= htmlspecialchars($ath['sum_fmt']) ?></strong>
                  </td>
                  <td class="proto-cell proto-col-place" data-place-for="<?= $pid ?>">
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
                  <td class="proto-cell proto-col-wilks" data-wilks-for="<?= $pid ?>">
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

<div class="proto-modal-backdrop" id="judgeModal" aria-hidden="true">
  <div class="proto-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <header class="proto-modal-head">
      <div>
        <h4 class="proto-modal-title" id="modalTitle"><?= $isFolk ? 'Судейство повторов' : 'Судейство попытки' ?></h4>
        <p class="proto-modal-sub" id="modalAthleteInfo">Атлет · Категория</p>
      </div>
      <button type="button" class="proto-modal-close" id="modalCloseBtn" aria-label="Закрыть">×</button>
    </header>

    <form method="post" action="<?= htmlspecialchars($base) ?>/judge/attempt" id="judgeAttemptForm" class="proto-modal-body">
      <input type="hidden" name="participant_id" id="modalPid" value="">
      <input type="hidden" name="discipline_id" id="modalDid" value="<?= $did ?>">
      <input type="hidden" name="attempt_no" id="modalAttNo" value="">
      <input type="hidden" name="is_valid" id="modalIsValid" value="1">
      <input type="hidden" name="action" id="modalAction" value="">
      <input type="hidden" name="ajax" value="1">

      <label for="modalWeightInput" class="proto-weight-label">
        <?= $isFolk ? 'Повторы (раз)' : 'Вес / результат (' . htmlspecialchars($unit) . ')' ?>
      </label>
      <div class="proto-weight-row">
        <?php if ($isFolk): ?>
          <button type="button" class="proto-step pt-step-btn" data-step="-5">−5</button>
          <button type="button" class="proto-step pt-step-btn" data-step="-1">−1</button>
          <input type="number" step="1" min="0" class="field proto-weight-input" id="modalWeightInput" name="value" placeholder="0" required>
          <button type="button" class="proto-step pt-step-btn" data-step="+1">+1</button>
          <button type="button" class="proto-step pt-step-btn" data-step="+5">+5</button>
        <?php else: ?>
          <button type="button" class="proto-step pt-step-btn" data-step="-5">−5</button>
          <button type="button" class="proto-step pt-step-btn" data-step="-2.5">−2.5</button>
          <input type="number" step="0.5" class="field proto-weight-input" id="modalWeightInput" name="value" placeholder="0.0" required>
          <button type="button" class="proto-step pt-step-btn" data-step="+2.5">+2.5</button>
          <button type="button" class="proto-step pt-step-btn" data-step="+5">+5</button>
        <?php endif; ?>
      </div>

      <div class="proto-decide">
        <button type="button" class="proto-decide-good" id="btnJudgeGood">
          <span class="proto-decide-mark">✓</span>
          <span><strong>Зачёт</strong><small>Good lift</small></span>
        </button>
        <button type="button" class="proto-decide-bad" id="btnJudgeBad">
          <span class="proto-decide-mark">✕</span>
          <span><strong>Незачёт</strong><small>No lift</small></span>
        </button>
      </div>

      <div class="proto-modal-foot">
        <button type="button" class="proto-clear" id="btnJudgeClear">Очистить</button>
        <button type="button" class="btn-secondary-sm" id="btnJudgeCancel">Отмена</button>
      </div>
    </form>
  </div>
</div>

<div class="proto-toast" id="ptToast" role="alert" aria-live="polite"></div>

<script>
(function() {
  const container = document.querySelector('[data-ptable]');
  const base = container ? container.dataset.base : '';
  const did = container ? container.dataset.did : '';

  const modal = document.getElementById('judgeModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalAthleteInfo = document.getElementById('modalAthleteInfo');
  const modalWeightInput = document.getElementById('modalWeightInput');
  const modalPid = document.getElementById('modalPid');
  const modalDid = document.getElementById('modalDid');
  const modalAttNo = document.getElementById('modalAttNo');
  const modalIsValid = document.getElementById('modalIsValid');
  const modalAction = document.getElementById('modalAction');
  const form = document.getElementById('judgeAttemptForm');
  const toast = document.getElementById('ptToast');
  const tbody = document.getElementById('ptableTbody');
  const isFolkMode = <?= $isFolk ? 'true' : 'false' ?>;

  let activeCell = null;

  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('is-show');
    setTimeout(() => toast.classList.remove('is-show'), 2600);
  }

  function openModal(cell) {
    activeCell = cell;
    modalPid.value = cell.dataset.pid;
    modalDid.value = cell.dataset.did || did;
    modalAttNo.value = cell.dataset.attNo;
    modalAction.value = '';
    modalTitle.textContent = isFolkMode
      ? `Повторы · ${cell.dataset.name}`
      : `Попытка ${cell.dataset.attNo} · ${cell.dataset.name}`;
    modalAthleteInfo.textContent = `Категория: ${cell.dataset.cat}${cell.dataset.bw ? ' · Вес: ' + cell.dataset.bw + ' кг' : ''}`;
    modalWeightInput.value = cell.dataset.val || '';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
      modalWeightInput.focus();
      modalWeightInput.select();
    }, 50);
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeCell = null;
  }

  document.querySelectorAll('[data-att-cell]').forEach(cell => {
    cell.addEventListener('click', () => openModal(cell));
  });

  document.getElementById('modalCloseBtn')?.addEventListener('click', closeModal);
  document.getElementById('btnJudgeCancel')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  document.querySelectorAll('.pt-step-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const step = parseFloat(btn.dataset.step) || 0;
      let val = parseFloat(modalWeightInput.value) || 0;
      val = Math.max(0, val + step);
      modalWeightInput.value = isFolkMode
        ? String(Math.round(val))
        : (val % 1 === 0 ? val : val.toFixed(1));
    });
  });

  async function submitDecision(isValid, isClear = false) {
    if (isClear) {
      modalAction.value = 'clear';
    } else {
      modalAction.value = '';
      modalIsValid.value = isValid ? '1' : '0';
      if (!modalWeightInput.value || parseFloat(modalWeightInput.value) <= 0) {
        if (!isValid) {
          modalWeightInput.value = '0';
        } else {
          modalWeightInput.focus();
          return;
        }
      }
    }

    const formData = new FormData(form);

    try {
      const resp = await fetch(form.action, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: formData
      });

      if (resp.ok) {
        const data = await resp.json();
        if (data.ok) {
          if (activeCell) {
            if (isClear || data.action === 'cleared') {
              activeCell.className = 'proto-cell proto-col-att is-empty';
              activeCell.dataset.val = '';
              activeCell.dataset.valid = '1';
              activeCell.dataset.status = 'empty';
              activeCell.querySelector('.pt-att-val').textContent = '—';
              showToast('Попытка очищена');
            } else {
              const valFmt = data.value_fmt || (data.value + '');
              activeCell.className = 'proto-cell proto-col-att ' + (data.is_valid ? 'is-good' : 'is-bad');
              activeCell.dataset.valid = data.is_valid ? '1' : '0';
              activeCell.dataset.status = data.is_valid ? 'valid' : 'invalid';
              activeCell.dataset.val = data.value;
              activeCell.querySelector('.pt-att-val').textContent = valFmt;
              showToast(`Сохранено: ${valFmt} (${data.is_valid ? 'зачёт' : 'незачёт'})`);
            }
          }
          closeModal();
          setTimeout(() => window.location.reload(), 450);
          return;
        }
      }
    } catch (e) {
      console.warn('AJAX error, fallback to form submit', e);
    }

    form.submit();
  }

  document.getElementById('btnJudgeGood')?.addEventListener('click', () => submitDecision(true));
  document.getElementById('btnJudgeBad')?.addEventListener('click', () => submitDecision(false));
  document.getElementById('btnJudgeClear')?.addEventListener('click', () => submitDecision(false, true));

  let currentSortMode = 'queue';
  const btnSortByCat = document.getElementById('btnSortByCat');
  const btnSortByQueue = document.getElementById('btnSortByQueue');
  const attPrefix = <?= json_encode($attemptPrefix, JSON_UNESCAPED_UNICODE) ?>;
  const unitLabel = <?= json_encode($unit, JSON_UNESCAPED_UNICODE) ?>;
  const queueUnitLabel = isFolkMode ? 'кг' : unitLabel;

  function syncCatHeaders(mode) {
    document.querySelectorAll('.proto-cat-row').forEach(row => {
      row.style.display = mode === 'queue' ? 'none' : '';
    });
  }

  function sortTableRows(mode, silent) {
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
      if (!silent) showToast('Сортировка по очереди помоста');
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
      headers.forEach(h => {
        byCat[h.dataset.catHeader] = h;
      });
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
      if (!silent) showToast('Группировка по весовым категориям');
    }
  }

  btnSortByCat?.addEventListener('click', () => sortTableRows('category'));
  btnSortByQueue?.addEventListener('click', () => sortTableRows('queue'));
  // Сразу показываем реальную очередь помоста в таблице
  sortTableRows('queue', true);

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

  async function pollLiveTableQueue() {
    if (!did) return;
    try {
      const resp = await fetch(`${base}/live/data?discipline=${did}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!resp.ok) return;
      const data = await resp.json();
      if (!data) return;

      const cur = data.current;
      const fullQueue = Array.isArray(data.queue) && data.queue.length
        ? data.queue
        : (data.upcoming || []);
      const qPosMap = {};

      // Полная очередь: позиция 1 = сейчас на помосте, дальше по порядку
      if (cur && cur.participant_id) {
        qPosMap[String(cur.participant_id)] = {
          pos: 0,
          status: 'now',
          detail: queueDetail(cur)
        };
      }

      fullQueue.forEach((item, idx) => {
        const pid = String(item.participant_id || '');
        if (!pid || qPosMap[pid]) return;
        const absPos = item.pos != null ? parseInt(item.pos, 10) : (idx + 1);
        qPosMap[pid] = {
          pos: absPos,
          status: item.deferred ? 'defer' : 'item',
          detail: queueDetail(item),
          absPos: absPos
        };
      });

      // Следующий = ближайший после текущего в полной очереди
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
      if (nextPid && qPosMap[nextPid]) {
        qPosMap[nextPid].status = 'next';
      }

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
          // Для сортировки: помост=0, следующий=1, остальные = реальный №
          const sortPos = qInfo.status === 'now' ? 0 : (qInfo.status === 'next' ? 1 : qInfo.absPos + 100);
          row.dataset.queuePos = String(sortPos);

          if (qInfo.status === 'now') {
            row.classList.add('is-stage');
            if (queueCell) queueCell.innerHTML = queueBadgeHtml('now', qInfo.detail);
            if (nameMeta) {
              nameMeta.insertAdjacentHTML('beforeend', '<span class="proto-pill proto-pill--now">на помосте</span>');
            }
          } else if (qInfo.status === 'next') {
            row.classList.add('is-next');
            if (queueCell) queueCell.innerHTML = queueBadgeHtml('next', qInfo.detail);
            if (nameMeta) {
              nameMeta.insertAdjacentHTML('beforeend', '<span class="proto-pill proto-pill--next">следующий</span>');
            }
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

      if (currentSortMode === 'queue') {
        sortTableRows('queue', true);
      }
    } catch (e) {
      /* ignore poll errors */
    }
  }

  setInterval(pollLiveTableQueue, 3500);
  pollLiveTableQueue();

  const catFilter = document.getElementById('catFilter');
  catFilter?.addEventListener('change', () => {
    const val = catFilter.value;
    document.querySelectorAll('.pt-row').forEach(row => {
      row.style.display = (val === 'all' || row.dataset.cat === val) ? '' : 'none';
    });
    document.querySelectorAll('.proto-cat-row').forEach(row => {
      row.style.display = (val === 'all' || row.dataset.catHeader === val) ? '' : 'none';
    });
  });

  const searchInput = document.getElementById('ptableSearch');
  searchInput?.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    document.querySelectorAll('.pt-row').forEach(row => {
      const name = row.dataset.name || '';
      row.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });

  document.getElementById('btnFullscreen')?.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen?.();
    } else {
      document.exitFullscreen?.();
    }
  });
})();
</script>
