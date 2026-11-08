<?php
/**
 * Кнопка «Помост» + модалка судейства в протоколе.
 *
 * @var array $config
 * @var array $discipline
 * @var list<array> $queue
 * @var array $current
 * @var array|null $bar
 * @var string|null $category
 * @var int $attemptsAllowed
 * @var bool $isFolk
 * @var int $orderPid
 * @var int $orderAttempt
 * @var float|null $orderBase
 * @var string $orderName
 */

$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$scoring = $discipline['scoring_type'] ?? 'weight';
$isFolk = !empty($isFolk) || (($discipline['code'] ?? '') === \App\Models\FolkBench::CODE);
$displayUnit = $isFolk ? 'кг' : match ($scoring) {
    'reps' => 'раз',
    'arm'  => 'побед',
    default => 'кг',
};
$unit = $displayUnit;
$step = $scoring === 'weight' ? '2.5' : '1';
$attemptsAllowed = (int) ($attemptsAllowed ?? 3);
$queue = $queue ?? [];
$current = $current ?? null;
$bar = $bar ?? null;
$category = $category ?? null;

$weightFmt = static function ($w) use ($scoring, $isFolk): string {
    $n = (float) $w;
    return ($scoring === 'weight' || $isFolk)
        ? number_format($n, 1, '.', '')
        : number_format($n, 0, '.', '');
};

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
$orderDefault = $orderBase !== null
    ? $weightFmt($orderBase)
    : ($current ? $weightFmt($current['declared_weight']) : '');

$protoBase = $base . '/judge/protocol?discipline=' . $did;
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
                $prevHref = $protoBase . '&current='
                    . urlencode((int) $p['participant_id'] . '-' . (int) $p['attempt_no'])
                    . '&open_stage=1';
            }
            if (isset($queue[$i + 1])) {
                $n = $queue[$i + 1];
                $nextHref = $protoBase . '&current='
                    . urlencode((int) $n['participant_id'] . '-' . (int) $n['attempt_no'])
                    . '&open_stage=1';
            }
            break;
        }
    }
}
$upcoming = ($current && isset($queue[$queueIndex + 1])) ? $queue[$queueIndex + 1] : null;
$openStage = $autoOrder || !empty($_GET['open_stage']);
$hasStage = $current || $autoOrder;
?>

<?php if ($hasStage): ?>
  <button type="button"
          class="btn-primary-sm proto-stage-btn"
          id="btnOpenStage"
          <?= $current ? '' : 'disabled' ?>>
    <span class="proto-live-dot" aria-hidden="true"></span>
    Помост
    <?php if ($current): ?>
      <span class="proto-stage-btn-meta">
        <?= htmlspecialchars(mb_strtoupper(explode(' ', trim($current['full_name']))[0] ?? '')) ?>
        · <?= htmlspecialchars($weightFmt($current['declared_weight'])) ?>
      </span>
    <?php endif; ?>
  </button>

  <div class="proto-stage-backdrop<?= $openStage ? ' is-open' : '' ?>"
       id="stageModal"
       aria-hidden="<?= $openStage ? 'false' : 'true' ?>">
    <div class="proto-stage-dialog" role="dialog" aria-modal="true" aria-labelledby="stageModalTitle">
      <header class="proto-stage-dialog-head">
        <div>
          <h3 class="proto-stage-dialog-title" id="stageModalTitle">Помост</h3>
          <?php if ($current): ?>
            <p class="proto-stage-dialog-sub">
              <?= $queueIndex + 1 ?> / <?= count($queue) ?> в очереди
            </p>
          <?php endif; ?>
        </div>
        <button type="button" class="proto-stage-dialog-close" id="btnCloseStage" aria-label="Закрыть">×</button>
      </header>

      <div class="proto-stage-dialog-body">
        <?php if ($autoOrder): ?>
          <div class="platform-order-banner panel proto-stage-order" id="orderBanner">
            <div class="platform-order-banner-text">
              <strong>Заказ веса</strong>
              · <?= htmlspecialchars($orderName ?: 'участник') ?>
              · попытка #<?= $orderAttempt ?>
            </div>
            <form method="post" action="<?= htmlspecialchars($base) ?>/judge/order"
                  class="platform-order-quick" id="orderQuickForm">
              <input type="hidden" name="from" value="protocol">
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
                <a class="btn-secondary" href="<?= htmlspecialchars($protoBase) ?>">Пропустить</a>
                <button class="btn-primary" type="submit">Заказать и дальше</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($current): ?>
          <div class="proto-stage" data-platform data-athlete-key="<?= htmlspecialchars($currentKey) ?>">
            <div class="proto-stage-top">
              <div class="proto-stage-nav">
                <?php if ($prevHref): ?>
                  <a class="btn-secondary-sm" href="<?= htmlspecialchars($prevHref) ?>">←</a>
                <?php else: ?>
                  <span class="btn-secondary-sm disabled" aria-disabled="true">←</span>
                <?php endif; ?>
                <span class="proto-stage-pos"><?= $queueIndex + 1 ?> / <?= count($queue) ?></span>
                <?php if ($nextHref): ?>
                  <a class="btn-secondary-sm" href="<?= htmlspecialchars($nextHref) ?>">→</a>
                <?php else: ?>
                  <span class="btn-secondary-sm disabled" aria-disabled="true">→</span>
                <?php endif; ?>
              </div>

              <div class="proto-stage-athlete">
                <span class="proto-stage-badge">Попытка <?= (int) $current['attempt_no'] ?> / <?= $attemptsAllowed ?></span>
                <strong class="proto-stage-name"><?= htmlspecialchars(mb_strtoupper($current['full_name'])) ?></strong>
                <span class="proto-stage-meta">
                  <?php if ($category): ?><?= htmlspecialchars($category) ?> · <?php endif; ?>
                  с/в <?= number_format((float) $current['body_weight'], 2, '.', '') ?>
                  <?php if (!empty($current['lot_number'])): ?>
                    · жр #<?= (int) $current['lot_number'] ?>
                  <?php endif; ?>
                </span>
              </div>

              <div class="proto-stage-weight">
                <span class="proto-stage-kg"><?= htmlspecialchars($weightFmt($current['declared_weight'])) ?></span>
                <span class="proto-stage-unit"><?= htmlspecialchars($unit) ?></span>
              </div>
            </div>

            <?php if (($scoring === 'weight' || $isFolk) && $bar): ?>
              <div class="proto-stage-bar">
                <span class="proto-stage-bar-label">
                  Наборка · сторона <?= number_format((float) $bar['per_side'], 2, '.', '') ?> кг
                </span>
                <div class="platform-bar proto-stage-plates" aria-label="Наборка штанги">
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
              </div>
            <?php endif; ?>

            <?php if ($scoring === 'reps'): ?>
              <form method="post" action="<?= htmlspecialchars($base) ?>/judge/lift"
                    class="proto-stage-form platform-reps-form" id="liftForm">
                <input type="hidden" name="from" value="protocol">
                <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
                <input type="hidden" name="discipline_id" value="<?= $did ?>">
                <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
                <input type="hidden" name="mode" value="reps">
                <input type="hidden" name="weight" value="<?= htmlspecialchars((string) $current['declared_weight']) ?>">

                <div class="proto-stage-reps">
                  <label class="field-label" for="reps_count">Сколько раз поднял</label>
                  <div class="platform-order-stepper">
                    <button type="button" class="platform-step" data-delta="-1" data-target="reps_count">−</button>
                    <input class="field-lg platform-order-input" id="reps_count"
                           name="reps" type="number" step="1" min="0" required
                           inputmode="numeric" value="0">
                    <button type="button" class="platform-step" data-delta="1" data-target="reps_count">+</button>
                    <span class="platform-order-unit">раз</span>
                  </div>
                </div>

                <div class="platform-actions platform-actions--reps proto-stage-actions" id="platformActions">
                  <button class="platform-btn-confirm is-good" type="submit" id="btnConfirm">Зафиксировать повторы</button>
                  <?php if ($nextHref): ?>
                    <a class="platform-btn-order" href="<?= htmlspecialchars($nextHref) ?>">Следующий →</a>
                  <?php endif; ?>
                </div>
              </form>
            <?php else: ?>
              <div class="platform-lights proto-stage-lights" id="judgeLights">
                <?php
                  $lights = [
                      'left'   => 'Левый',
                      'center' => 'Старший',
                      'right'  => 'Правый',
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

              <div class="platform-verdict proto-stage-verdict" id="lightsVerdict">
                <span id="verdictText">Выберите три сигнала</span>
              </div>

              <form method="post" action="<?= htmlspecialchars($base) ?>/judge/lift"
                    class="proto-stage-form" id="liftForm" data-lift-form>
                <input type="hidden" name="from" value="protocol">
                <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
                <input type="hidden" name="discipline_id" value="<?= $did ?>">
                <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
                <input type="hidden" name="weight" value="<?= htmlspecialchars((string) $current['declared_weight']) ?>">
                <input type="hidden" name="result" id="liftResult" value="">

                <div class="platform-actions proto-stage-actions" id="platformActions">
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

            <div class="proto-stage-foot">
              <form method="post" action="<?= htmlspecialchars($base) ?>/judge/defer" class="proto-stage-defer">
                <input type="hidden" name="from" value="protocol">
                <input type="hidden" name="participant_id" value="<?= (int) $current['participant_id'] ?>">
                <input type="hidden" name="discipline_id" value="<?= $did ?>">
                <input type="hidden" name="attempt_no" value="<?= (int) $current['attempt_no'] ?>">
                <?php if (!empty($current['deferred'])): ?>
                  <input type="hidden" name="action" value="undefer">
                  <button class="platform-btn-defer is-back" type="submit">Вернуть в очередь</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="defer">
                  <button class="platform-btn-defer" type="submit">Ещё не пришёл</button>
                <?php endif; ?>
              </form>

              <?php if ($upcoming): ?>
                <p class="proto-stage-next">
                  Далее:
                  <strong><?= htmlspecialchars($upcoming['full_name']) ?></strong>
                  · <?= htmlspecialchars($weightFmt($upcoming['declared_weight'])) ?> <?= htmlspecialchars($unit) ?>
                </p>
              <?php endif; ?>
            </div>
          </div>

          </div>

          <?php if ($canOrder): ?>
            <dialog id="orderModal" class="w-[min(28rem,calc(100%-2rem))] rounded-lg border border-line bg-paper p-0 text-ink shadow-xl" aria-labelledby="orderModalLabel">
              <form method="post" action="<?= htmlspecialchars($base) ?>/judge/order" class="flex flex-col">
                <input type="hidden" name="from" value="protocol">
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
      </div>
    </div>
  </div>

  <script>
    window.PLATFORM = {
      prevUrl: <?= json_encode($prevHref) ?>,
      nextUrl: <?= json_encode($nextHref) ?>,
      autoOrder: <?= $autoOrder ? 'true' : 'false' ?>
    };
    (function () {
      var modal = document.getElementById('stageModal');
      var openBtn = document.getElementById('btnOpenStage');
      var closeBtn = document.getElementById('btnCloseStage');
      if (!modal) return;

      function openStage() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('proto-stage-lock');
      }
      function closeStage() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('proto-stage-lock');
      }

      openBtn?.addEventListener('click', openStage);
      closeBtn?.addEventListener('click', closeStage);
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeStage();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
          var orderModal = document.getElementById('orderModal');
          if (orderModal && orderModal.open) return;
          closeStage();
        }
      });

      if (modal.classList.contains('is-open')) {
        document.documentElement.classList.add('proto-stage-lock');
      }

      // Keep modal open after prev/next navigation
      window.__openProtoStage = openStage;
    })();
  </script>
  <script src="<?= htmlspecialchars($base) ?>/js/platform.js"></script>
<?php endif; ?>
