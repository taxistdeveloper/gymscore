<?php
$base = $config['base_url'] ?? '';
$disciplineId = (int) ($disciplineId ?? 0);
?>

<div class="live-board" id="liveBoard"
     data-base="<?= htmlspecialchars($base) ?>"
     data-discipline="<?= $disciplineId ?>">
  <header class="live-top">
    <div class="live-brand">
      <span class="live-brand-name">ALASH <em>GYM</em></span>
      <span class="live-brand-live">
        <span class="live-brand-live-dot" aria-hidden="true"></span>
        LIVE
      </span>
    </div>

    <div class="live-top-right">
      <div class="live-disc-pick" id="liveDiscipline" role="listbox" aria-label="Дисциплина">
        <?php foreach ($disciplines as $d): ?>
          <?php $did = (int) $d['id']; $active = $did === $disciplineId; ?>
          <button type="button"
                  class="live-disc-chip<?= $active ? ' is-active' : '' ?>"
                  role="option"
                  aria-selected="<?= $active ? 'true' : 'false' ?>"
                  data-id="<?= $did ?>">
            <?= htmlspecialchars($d['name_ru']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="live-top-tools">
        <span class="live-pulse" id="livePulse" title="Связь с сервером" aria-label="Статус обновления">
          <span class="live-pulse-dot"></span>
        </span>
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Переключить фон">
          <span class="theme-toggle-track" aria-hidden="true"><span class="theme-toggle-knob"></span></span>
          <span class="theme-toggle-text"><span class="theme-label-light">Светлый</span><span class="theme-label-dark">Тёмный</span></span>
        </button>
        <a class="live-exit" href="<?= htmlspecialchars($base) ?>/">Выход</a>
      </div>
    </div>
  </header>

  <div class="live-grid">
    <section class="live-now" aria-live="polite">
      <div class="live-now-head">
        <div class="live-now-badge" id="liveAttempt">Попытка —</div>
        <p class="live-meta" id="liveMeta">—</p>
      </div>

      <h1 class="live-name" id="liveName">Ожидание помоста…</h1>
      <p class="live-sub" id="liveSub"></p>

      <div class="live-arm" id="liveArm" hidden>
        <div class="live-arm-fighter live-arm-fighter--left" id="liveArmLeft">
          <span class="live-arm-side">Левая</span>
          <div class="live-arm-name" id="liveArmLeftName">—</div>
          <div class="live-arm-meta" id="liveArmLeftMeta"></div>
        </div>
        <div class="live-arm-vs" aria-hidden="true">VS</div>
        <div class="live-arm-fighter live-arm-fighter--right" id="liveArmRight">
          <span class="live-arm-side">Правая</span>
          <div class="live-arm-name" id="liveArmRightName">—</div>
          <div class="live-arm-meta" id="liveArmRightMeta"></div>
        </div>
      </div>

      <div class="live-stage">
        <div class="live-metrics" id="liveMetrics">
          <div class="live-reps-block" id="liveRepsBlock" hidden>
            <div class="live-weight" id="liveReps">—</div>
            <div class="live-weight-label" id="liveRepsLabel">раз</div>
            <p class="live-reps-who" id="liveRepsWho"></p>
          </div>
          <div class="live-weight-block" id="liveWeightBlock">
            <div class="live-weight" id="liveWeight">—</div>
            <div class="live-weight-label" id="liveWeightLabel">Вес на штанге</div>
          </div>
        </div>

        <div class="live-bar" id="liveBar" hidden>
          <div class="live-bar-head">
            <span>Наборка · гриф 20 кг</span>
            <span id="livePerSide"></span>
          </div>
          <div class="live-bar-visual" id="liveBarVisual"></div>
        </div>
      </div>
    </section>

    <aside class="live-side">
      <div class="live-panel">
        <h2 class="live-side-title">Очередь</h2>
        <ul class="live-queue" id="liveQueue">
          <li class="live-empty">Нет участников</li>
        </ul>
      </div>

      <div class="live-panel live-panel--rank">
        <h2 class="live-side-title">Рейтинг</h2>
        <div class="live-rank-wrap">
          <table class="live-rank" id="liveRank">
            <thead>
              <tr>
                <th>#</th>
                <th>Участник</th>
                <th id="liveRankUnit">Рез.</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="3" class="live-empty">Пока нет результатов</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </aside>
  </div>
</div>

<script src="<?= htmlspecialchars($base) ?>/js/live-board.js"></script>
