<?php
$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$attemptsAllowed = (int) ($attemptsAllowed ?? 3);
$scoring = $discipline['scoring_type'] ?? 'weight';
$isFolk = !empty($isFolk) || (($discipline['code'] ?? '') === \App\Models\FolkBench::CODE);
// На помосте для народного жима в очереди/штанге — кг, результат — повторы
$displayUnit = $isFolk ? 'кг' : match ($scoring) {
    'reps' => 'раз',
    'arm'  => 'побед',
    default => 'кг',
};
$unit = $displayUnit;
$step = $scoring === 'weight' ? '2.5' : '1';
$currentKey = $current
    ? ((int) $current['participant_id'] . '-' . (int) $current['attempt_no'])
    : '';
$discCode = (string) ($discipline['code'] ?? '');
$allowLastReorder = in_array($discCode, ['curl_strict', 'curl_extreme'], true);
$currentAttemptNo = $current ? (int) $current['attempt_no'] : 0;
$nextAttempt = $current ? ($currentAttemptNo + 1) : 2;
$canOrderNext = $current && $nextAttempt <= $attemptsAllowed && $scoring === 'weight';
// Строгий / экстремальный бицепс: последний подход можно перезаказать на помосте
$canReOrderLast = $current
    && $allowLastReorder
    && $scoring === 'weight'
    && $currentAttemptNo >= $attemptsAllowed
    && $currentAttemptNo >= 2;
$canOrder = $canOrderNext || $canReOrderLast;
$orderTargetAttempt = $canOrderNext ? $nextAttempt : $currentAttemptNo;
$orderIsReorder = $canReOrderLast && !$canOrderNext;
$orderPid = (int) ($orderPid ?? 0);
$orderAttempt = (int) ($orderAttempt ?? 0);
$orderBase = $orderBase ?? null;
$orderName = $orderName ?? '';
$autoOrder = $orderPid > 0 && $orderAttempt >= 2 && $scoring === 'weight';
$weightFmt = static function ($w) use ($scoring, $isFolk): string {
    $n = (float) $w;
    return ($scoring === 'weight' || $isFolk)
        ? number_format($n, 1, '.', '')
        : number_format($n, 0, '.', '');
};

$queueIndex = 0;
$prevHref = null;
$nextHref = null;
if ($current && $queue) {
    foreach ($queue as $i => $item) {
        $key = (int) $item['participant_id'] . '-' . (int) $item['attempt_no'];
        if ($key === $currentKey) {
            $queueIndex = $i;
            if ($i > 0) {
                $p = $queue[$i - 1];
                $prevHref = $base . '/judge?discipline=' . $did . '&current='
                    . urlencode((int) $p['participant_id'] . '-' . (int) $p['attempt_no']);
            }
            if (isset($queue[$i + 1])) {
                $n = $queue[$i + 1];
                $nextHref = $base . '/judge?discipline=' . $did . '&current='
                    . urlencode((int) $n['participant_id'] . '-' . (int) $n['attempt_no']);
            }
            break;
        }
    }
}
$upcoming = ($current && isset($queue[$queueIndex + 1])) ? $queue[$queueIndex + 1] : null;
$orderDefault = $orderBase !== null
    ? $weightFmt($orderBase)
    : ($current ? $weightFmt($current['declared_weight']) : '');

$judgeNav = 'platform';
$judgeDid = $did;
require __DIR__ . '/nav.php';
?>

<section class="judge-shell">
  <div class="judge-toolbar">
    <form method="get" action="<?= htmlspecialchars($base) ?>/judge" class="judge-disc">
      <label class="sr-only" for="platform_discipline">Дисциплина</label>
      <select class="field field-sm" id="platform_discipline" name="discipline" onchange="this.form.submit()">
        <?php foreach ($disciplines as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $did ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name_ru']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if ($current && $queue): ?>
      <p class="judge-toolbar-hint">
        <?= $queueIndex + 1 ?> / <?= count($queue) ?> в очереди
      </p>
    <?php endif; ?>
  </div>

<?php if (!$discipline): ?>
  <div class="judge-empty panel">
    <p class="judge-empty-title">Нет дисциплин</p>
  </div>
<?php elseif (!$current && !$autoOrder): ?>
  <div class="judge-empty panel">
    <p class="judge-empty-title">Очередь пуста</p>
    <p class="text-muted mb-3">«<?= htmlspecialchars($discipline['name_ru']) ?>»</p>
    <p class="text-sm text-muted mb-3">
      <?php if ($isFolk): ?>
        Зафиксируйте собственный вес во взвешивании — штанга ½ с/в подставится сама.
      <?php else: ?>
        Укажите стартовый вес во взвешивании — участник появится в очереди.
      <?php endif; ?>
    </p>
    <a class="btn-primary-sm" href="<?= htmlspecialchars($base) ?>/judge/weigh-in">К взвешиванию</a>
  </div>
<?php else: ?>
  <?php if ($autoOrder): ?>
    <div class="platform-order-banner panel mb-4" id="orderBanner">
      <div class="platform-order-banner-text">
        <strong>Заказ веса</strong>
        · <?= htmlspecialchars($orderName ?: 'участник') ?>
        · попытка #<?= $orderAttempt ?>
      </div>
      <form method="post" action="<?= htmlspecialchars($base) ?>/judge/order"
            class="platform-order-quick" id="orderQuickForm">
        <input type="hidden" name="participant_id" value="<?= $orderPid ?>">
        <input type="hidden" name="discipline_id" value="<?= $did ?>">
        <input type="hidden" name="attempt_no" value="<?= $orderAttempt ?>">
        <div class="platform-order-stepper">
          <button type="button" class="platform-step" data-delta="-<?= $step ?>" aria-label="Меньше">−</button>
          <input class="field-lg platform-order-input" id="order_weight_quick"
                 name="weight" type="number" step="<?= $step ?>" min="0" required
                 inputmode="decimal" value="<?= htmlspecialchars($orderDefault) ?>">
          <button type="button" class="platform-step" data-delta="<?= $step ?>" aria-label="Больше">+</button>
          <span class="platform-order-unit"><?= htmlspecialchars($unit) ?></span>
        </div>
        <div class="platform-order-presets">
          <button type="button" class="btn-secondary-sm platform-preset" data-delta="<?= $step ?>">+<?= $step ?></button>
          <button type="button" class="btn-secondary-sm platform-preset" data-delta="5">+5</button>
          <button type="button" class="btn-secondary-sm platform-preset" data-delta="10">+10</button>
        </div>
        <div class="platform-order-banner-actions">
          <a class="btn-secondary" href="<?= htmlspecialchars($base) ?>/judge?discipline=<?= $did ?>">Пропустить</a>
          <button class="btn-primary" type="submit">Заказать и дальше</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($current): ?>
  <div class="platform-grid" data-platform
       data-athlete-key="<?= htmlspecialchars($currentKey) ?>">
    <section class="platform-main">
      <div class="platform-nav-row">
        <?php if ($prevHref): ?>
          <a class="btn-secondary-sm" href="<?= htmlspecialchars($prevHref) ?>" title="Предыдущий (←)">← Пред</a>
        <?php else: ?>
          <span class="btn-secondary-sm disabled" aria-disabled="true">← Пред</span>
        <?php endif; ?>
        <span class="platform-nav-pos text-sm text-muted">
          <?= $queueIndex + 1 ?> / <?= count($queue) ?> в очереди
        </span>
        <?php if ($nextHref): ?>
          <a class="btn-secondary-sm" href="<?= htmlspecialchars($nextHref) ?>" title="Следующий (→)">След →</a>
        <?php else: ?>
          <span class="btn-secondary-sm disabled" aria-disabled="true">След →</span>
        <?php endif; ?>
      </div>

      <div class="platform-hero">
        <div class="platform-hero-left">
          <div class="platform-attempt-badge">
            Попытка <?= (int) $current['attempt_no'] ?> из <?= $attemptsAllowed ?>
          </div>
          <p class="platform-meta">
            <?= htmlspecialchars($discipline['name_ru']) ?>
            <?php if ($category): ?>
              · <?= htmlspecialchars($category) ?>
            <?php endif; ?>
          </p>
          <h1 class="platform-name"><?= htmlspecialchars($current['full_name']) ?></h1>
          <p class="platform-sub">
            ALASH GYM
            · С/вес: <?= number_format((float) $current['body_weight'], 2, '.', '') ?> кг
            <?php if (!empty($current['lot_number'])): ?>
              · Жребий #<?= (int) $current['lot_number'] ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="platform-hero-weight">
          <div class="platform-weight-value">
            <?= htmlspecialchars($weightFmt($current['declared_weight'])) ?>
            <span><?= htmlspecialchars($unit) ?></span>
          </div>
          <div class="platform-weight-label">
            <?= $isFolk ? 'Вес на штанге (½ с/в)' : ($scoring === 'reps' ? 'Заявка' : 'Вес на штанге') ?>
          </div>
        </div>
      </div>

      <?php if (($scoring === 'weight' || $isFolk) && $bar): ?>
        <div class="platform-bar-block">
          <div class="platform-bar-head">
            <span>Наборка (гриф <?= number_format((float) $bar['bar'], 0) ?> кг)</span>
            <span class="platform-bar-side">
              На сторону: <?= number_format((float) $bar['per_side'], 2, '.', '') ?> кг
            </span>
          </div>
          <div class="platform-bar" aria-label="Наборка штанги">
            <div class="platform-bar-side-plates">
              <?php foreach (array_reverse($bar['plates']) as $plate): ?>
                <span class="plate"
                      style="--plate-color: <?= htmlspecialchars($plate['color']) ?>;
                             <?= !empty($plate['dark']) ? 'color:#111;' : '' ?>">
                  <?= htmlspecialchars($plate['label']) ?>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="platform-bar-sleeve"></div>
            <div class="platform-bar-shaft"></div>
            <div class="platform-bar-sleeve"></div>
            <div class="platform-bar-side-plates">
              <?php foreach ($bar['plates'] as $plate): ?>
                <span class="plate"
                      style="--plate-color: <?= htmlspecialchars($plate['color']) ?>;
                             <?= !empty($plate['dark']) ? 'color:#111;' : '' ?>">
                  <?= htmlspecialchars($plate['label']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if (!$bar['ok'] && $bar['note']): ?>
            <p class="mb-0 mt-2 text-sm text-signal"><?= htmlspecialchars($bar['note']) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($scoring === 'reps'): ?>
        <form method="post" action="<?= htmlspecialchars($base) ?>/judge/lift"
              class="platform-confirm-form platform-reps-form" id="liftForm">
          <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
          <input type="hidden" name="discipline_id" value="<?= $did ?>">
          <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
          <input type="hidden" name="mode" value="reps">
          <input type="hidden" name="weight" value="<?= htmlspecialchars((string) $current['declared_weight']) ?>">

          <div class="platform-reps-block panel">
            <label class="field-label" for="reps_count">
              Сколько раз поднял
            </label>
            <div class="platform-order-stepper mb-2">
              <button type="button" class="platform-step" data-delta="-1" data-target="reps_count">−</button>
              <input class="field-lg platform-order-input" id="reps_count"
                     name="reps" type="number" step="1" min="0" required
                     inputmode="numeric" value="0" autofocus>
              <button type="button" class="platform-step" data-delta="1" data-target="reps_count">+</button>
              <span class="platform-order-unit">раз</span>
            </div>
            <div class="platform-order-presets">
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="1" data-target="reps_count">+1</button>
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="5" data-target="reps_count">+5</button>
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="10" data-target="reps_count">+10</button>
            </div>
          </div>

          <div class="platform-actions platform-actions--reps" id="platformActions">
            <button class="platform-btn-confirm is-good" type="submit" id="btnConfirm">
              Зафиксировать повторы
            </button>
            <?php if ($nextHref): ?>
              <a class="platform-btn-order" href="<?= htmlspecialchars($nextHref) ?>">Следующий →</a>
            <?php endif; ?>
          </div>
        </form>
      <?php else: ?>
      <div class="platform-lights" id="judgeLights">
        <?php
          $lights = [
              'left'   => 'Боковой левый',
              'center' => 'Старший помоста',
              'right'  => 'Боковой правый',
          ];
        ?>
        <?php foreach ($lights as $key => $label): ?>
          <div class="platform-light" data-light="<?= $key ?>" data-value="">
            <div class="platform-light-label"><?= htmlspecialchars($label) ?></div>
            <div class="platform-light-choices" role="group" aria-label="<?= htmlspecialchars($label) ?>">
              <button type="button" class="platform-choice platform-choice--white"
                      data-choice="white" aria-pressed="false">
                <span class="platform-choice-orb" aria-hidden="true"></span>
                Белая
              </button>
              <button type="button" class="platform-choice platform-choice--red"
                      data-choice="red" aria-pressed="false">
                <span class="platform-choice-orb" aria-hidden="true"></span>
                Красная
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="platform-lights-hint">
        У каждого судьи нажмите <strong>Белая</strong> или <strong class="text-red-700">Красная</strong>,
        затем кнопку внизу
      </p>

      <div class="platform-verdict" id="lightsVerdict">
        <span id="verdictText">Выберите три сигнала</span>
      </div>

      <form method="post" action="<?= htmlspecialchars($base) ?>/judge/lift"
            class="platform-confirm-form" id="liftForm" data-lift-form>
        <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
        <input type="hidden" name="discipline_id" value="<?= $did ?>">
        <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
        <input type="hidden" name="weight" value="<?= htmlspecialchars((string) $current['declared_weight']) ?>">
        <input type="hidden" name="result" id="liftResult" value="">

        <div class="platform-actions" id="platformActions">
          <button class="platform-btn-confirm" type="submit" id="btnConfirm" disabled>
            Зафиксировать результат
          </button>
          <?php if ($canOrder && !$autoOrder): ?>
            <button class="platform-btn-order" type="button" id="btnOrder">
              <?php if ($orderIsReorder): ?>
                ↻ Перезаказать #<?= $orderTargetAttempt ?>
              <?php else: ?>
                ↗ Попытка #<?= $orderTargetAttempt ?>
              <?php endif; ?>
            </button>
          <?php elseif ($nextHref): ?>
            <a class="platform-btn-order" href="<?= htmlspecialchars($nextHref) ?>">Следующий →</a>
          <?php endif; ?>
        </div>
      </form>
      <?php endif; ?>

      <form method="post" action="<?= htmlspecialchars($base) ?>/judge/defer" class="platform-defer-form">
        <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
        <input type="hidden" name="discipline_id" value="<?= $did ?>">
        <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
        <?php if (!empty($current['deferred'])): ?>
          <input type="hidden" name="action" value="undefer">
          <button class="platform-btn-defer is-back" type="submit">
            Вернуть в очередь (уже на помосте)
          </button>
        <?php else: ?>
          <input type="hidden" name="action" value="defer">
          <button class="platform-btn-defer" type="submit">
            Ещё не пришёл на помост
          </button>
        <?php endif; ?>
      </form>

      <?php if ($upcoming): ?>
        <p class="platform-upcoming">
          Далее:
          <strong><?= htmlspecialchars($upcoming['full_name']) ?></strong>
          · <?= htmlspecialchars($weightFmt($upcoming['declared_weight'])) ?> <?= htmlspecialchars($unit) ?>
        </p>
      <?php endif; ?>

      <p class="platform-hotkeys hidden lg:block">
        <?php if ($scoring === 'reps'): ?>
          Введите повторы · <kbd>Enter</kbd> зафиксировать · <kbd>Space</kbd> таймер
        <?php else: ?>
          У каждого судьи: <strong>Белая</strong> или <strong>Красная</strong> ·
          <kbd>Enter</kbd> зафиксировать ·
          <kbd>Space</kbd> таймер
          <?php if ($canReOrderLast): ?>
            · <kbd>O</kbd> перезаказать
          <?php endif; ?>
        <?php endif; ?>
      </p>
    </section>

    <aside class="platform-side">
      <div class="platform-timer panel">
        <div class="platform-timer-head">
          <h2 class="platform-side-title mb-0">Таймер (1 мин)</h2>
          <label class="platform-autostart">
            <input type="checkbox" id="timerAutostart" checked>
            автостарт
          </label>
        </div>
        <div class="platform-timer-display" id="platformTimer">60</div>
        <div class="platform-timer-btns">
          <button type="button" class="platform-timer-start" id="timerStart">▶ Старт</button>
          <button type="button" class="platform-timer-pause" id="timerPause">❚❚ Пауза</button>
          <button type="button" class="platform-timer-reset" id="timerReset">↻ Сброс</button>
        </div>
      </div>

      <div class="platform-queue panel">
        <h2 class="platform-side-title">Очередь · <?= count($queue) ?></h2>
        <ul class="platform-queue-list">
          <?php foreach ($queue as $i => $item): ?>
            <?php
              $key = (int) $item['participant_id'] . '-' . (int) $item['attempt_no'];
              $href = $base . '/judge?discipline=' . $did . '&current=' . urlencode($key);
              $isCurrent = $key === $currentKey;
              $isNext = $i === $queueIndex + 1;
            ?>
            <li>
              <a class="platform-queue-item<?= $isCurrent ? ' is-current' : '' ?><?= $isNext ? ' is-next' : '' ?><?= !empty($item['deferred']) ? ' is-deferred' : '' ?>"
                 href="<?= htmlspecialchars($href) ?>"
                 <?= $isCurrent ? 'id="queueCurrent"' : '' ?>>
                <div>
                  <div class="platform-queue-name">
                    <?php if ($isCurrent): ?><span class="platform-pill">сейчас</span><?php endif; ?>
                    <?php if ($isNext): ?><span class="platform-pill platform-pill--next">далее</span><?php endif; ?>
                    <?php if (!empty($item['deferred'])): ?><span class="platform-pill platform-pill--defer">не пришёл</span><?php endif; ?>
                    <?= htmlspecialchars($item['full_name']) ?>
                  </div>
                  <div class="platform-queue-meta">
                    #<?= (int) $item['attempt_no'] ?>
                    <?php if (!empty($item['lot_number'])): ?>
                      · жребий #<?= (int) $item['lot_number'] ?>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="platform-queue-weight">
                  <?= htmlspecialchars($weightFmt($item['declared_weight'])) ?> <?= htmlspecialchars($unit) ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>

  <?php if ($canOrder): ?>
    <dialog id="orderModal" class="w-[min(28rem,calc(100%-2rem))] rounded-lg border border-line bg-paper p-0 text-ink shadow-xl" aria-labelledby="orderModalLabel">
        <form method="post" action="<?= htmlspecialchars($base) ?>/judge/order" class="flex flex-col">
          <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 class="text-lg font-semibold" id="orderModalLabel">
              <?= $orderIsReorder ? 'Перезаказ веса' : 'Заказ веса' ?> · попытка #<?= $orderTargetAttempt ?>
            </h2>
            <button type="button" class="text-2xl leading-none text-muted hover:text-ink" data-close-dialog aria-label="Закрыть">×</button>
          </div>
          <div class="px-4 py-3">
            <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
            <input type="hidden" name="discipline_id" value="<?= $did ?>">
            <input type="hidden" name="attempt_no" value="<?= $orderTargetAttempt ?>">
            <p class="mb-3 text-sm text-muted">
              <?= htmlspecialchars($current['full_name']) ?> · сейчас
              <?= htmlspecialchars($weightFmt($current['declared_weight'])) ?> <?= htmlspecialchars($unit) ?>
              <?php if ($orderIsReorder): ?>
                <br><span class="text-xs">Можно изменить вес последнего подхода до фиксации</span>
              <?php endif; ?>
            </p>
            <label class="field-label" for="order_weight">Вес</label>
            <div class="platform-order-stepper mb-2">
              <button type="button" class="platform-step" data-delta="-<?= $step ?>" data-target="order_weight">−</button>
              <input class="field-lg platform-order-input" id="order_weight" name="weight"
                     type="number" step="<?= $step ?>" min="0" required inputmode="decimal"
                     value="<?= htmlspecialchars($weightFmt($current['declared_weight'])) ?>">
              <button type="button" class="platform-step" data-delta="<?= $step ?>" data-target="order_weight">+</button>
              <span class="platform-order-unit"><?= htmlspecialchars($unit) ?></span>
            </div>
            <div class="platform-order-presets">
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="<?= $step ?>" data-target="order_weight">+<?= $step ?></button>
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="5" data-target="order_weight">+5</button>
              <button type="button" class="btn-secondary-sm platform-preset" data-delta="10" data-target="order_weight">+10</button>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-line px-4 py-3">
            <button type="button" class="btn-secondary" data-close-dialog>Отмена</button>
            <button type="submit" class="btn-primary"><?= $orderIsReorder ? 'Перезаказать' : 'Заказать' ?></button>
          </div>
        </form>
    </dialog>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</section>

<script>
  window.PLATFORM = {
    prevUrl: <?= json_encode($prevHref) ?>,
    nextUrl: <?= json_encode($nextHref) ?>,
    autoOrder: <?= $autoOrder ? 'true' : 'false' ?>
  };
</script>
<script src="<?= htmlspecialchars($base) ?>/js/platform.js"></script>
