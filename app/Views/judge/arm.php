<?php
$base = $config['base_url'] ?? '';
$did = (int) ($discipline['id'] ?? 0);
$wins = $wins ?? [];
$byCategory = $byCategory ?? [];

$judgeNav = 'arm';
$judgeDid = $did;
require __DIR__ . '/nav.php';
?>

<section class="judge-shell">
  <?php if (empty($byCategory)): ?>
    <div class="judge-empty panel">
      <p class="judge-empty-title">Нет взвешенных участников</p>
      <p class="text-muted mb-3">Сначала примите заявки и проведите взвешивание.</p>
      <a class="btn-primary-sm" href="<?= htmlspecialchars($base) ?>/judge/weigh-in">К взвешиванию</a>
    </div>
  <?php else: ?>
    <?php foreach ($byCategory as $cat => $block): ?>
      <?php
        $people = $block['people'] ?? [];
        $rounds = $block['rounds'] ?? [];
        $maxRound = (int) ($block['max_round'] ?? 0);
        $canGenerate = count($people) >= 2 || $maxRound > 0;
        ksort($rounds);
      ?>
      <article class="arm-cat panel">
        <header class="arm-cat-head">
          <div>
            <h2 class="arm-cat-title"><?= htmlspecialchars($cat) ?></h2>
            <p class="arm-cat-sub">
              <?= count($people) ?> чел.
              <?php if ($maxRound > 0): ?> · раунд <?= $maxRound ?><?php endif; ?>
            </p>
          </div>
          <?php if ($canGenerate): ?>
            <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/generate">
              <input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>">
              <button class="btn-primary-sm" type="submit">
                <?= $maxRound === 0 ? 'Составить пары' : 'Следующий раунд' ?>
              </button>
            </form>
          <?php endif; ?>
        </header>

        <?php if ($people): ?>
          <div class="arm-roster">
            <?php foreach ($people as $p): ?>
              <?php $pid = (int) $p['id']; ?>
              <span class="arm-roster-chip">
                <?= htmlspecialchars($p['full_name']) ?>
                <span class="text-muted">
                  <?= number_format((float) $p['body_weight'], 2, '.', '') ?> кг
                  · <?= (int) ($wins[$pid] ?? 0) ?>W
                </span>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (empty($rounds)): ?>
          <p class="arm-empty-hint">Пары ещё не составлены.</p>
        <?php else: ?>
          <?php foreach ($rounds as $roundNo => $list): ?>
            <h3 class="arm-round-title">Раунд <?= (int) $roundNo ?></h3>
            <div class="arm-matches">
              <?php foreach ($list as $m): ?>
                <?php
                  $mid = (int) $m['id'];
                  $p1 = (int) $m['participant1_id'];
                  $p2 = $m['participant2_id'] !== null ? (int) $m['participant2_id'] : null;
                  $done = ($m['status'] ?? '') === 'done';
                  $winner = $m['winner_id'] !== null ? (int) $m['winner_id'] : null;
                  $isCurrent = !empty($m['is_current']);
                  $isBye = $p2 === null;
                ?>
                <div class="arm-match<?= $isCurrent ? ' is-current' : '' ?><?= $done ? ' is-done' : '' ?>">
                  <div class="arm-match-meta">
                    Бой <?= (int) $m['match_no'] ?>
                    <?php if ($isCurrent): ?><span class="arm-badge">на табло</span><?php endif; ?>
                    <?php if ($isBye): ?><span class="arm-badge arm-badge--bye">bye</span><?php endif; ?>
                  </div>

                  <div class="arm-fighters">
                    <div class="arm-fighter<?= $winner === $p1 ? ' is-winner' : '' ?>">
                      <div class="arm-fighter-name"><?= htmlspecialchars($m['p1_name']) ?></div>
                      <div class="arm-fighter-sub">
                        <?= number_format((float) $m['p1_bw'], 2, '.', '') ?> кг
                        · <?= (int) ($wins[$p1] ?? 0) ?>W
                      </div>
                      <?php if (!$done && !$isBye): ?>
                        <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner" class="mt-2">
                          <input type="hidden" name="match_id" value="<?= $mid ?>">
                          <input type="hidden" name="winner_id" value="<?= $p1 ?>">
                          <button class="btn-primary-sm w-full" type="submit">Победа</button>
                        </form>
                      <?php elseif ($winner === $p1): ?>
                        <div class="arm-win-label">Победитель</div>
                      <?php endif; ?>
                    </div>

                    <div class="arm-vs"><?= $isBye ? 'BYE' : 'VS' ?></div>

                    <div class="arm-fighter<?= $winner === $p2 ? ' is-winner' : '' ?>">
                      <?php if ($isBye): ?>
                        <div class="arm-fighter-name text-muted">свободен</div>
                        <div class="arm-fighter-sub">проход без боя</div>
                      <?php else: ?>
                        <div class="arm-fighter-name"><?= htmlspecialchars((string) $m['p2_name']) ?></div>
                        <div class="arm-fighter-sub">
                          <?= number_format((float) $m['p2_bw'], 2, '.', '') ?> кг
                          · <?= (int) ($wins[$p2] ?? 0) ?>W
                        </div>
                        <?php if (!$done): ?>
                          <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner" class="mt-2">
                            <input type="hidden" name="match_id" value="<?= $mid ?>">
                            <input type="hidden" name="winner_id" value="<?= $p2 ?>">
                            <button class="btn-primary-sm w-full" type="submit">Победа</button>
                          </form>
                        <?php elseif ($winner === $p2): ?>
                          <div class="arm-win-label">Победитель</div>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="arm-match-actions">
                    <?php if (!$isBye && !$isCurrent): ?>
                      <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/current">
                        <input type="hidden" name="match_id" value="<?= $mid ?>">
                        <button class="btn-secondary-sm" type="submit">На табло</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($done && !$isBye): ?>
                      <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner">
                        <input type="hidden" name="match_id" value="<?= $mid ?>">
                        <input type="hidden" name="action" value="clear">
                        <button class="btn-secondary-sm" type="submit">Сбросить</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>

    <p class="judge-footnote">
      Пары по жребию внутри категории. Нечётное число — bye.
      После раунда нажмите «Следующий раунд». Победы пишутся в протокол сами.
    </p>
  <?php endif; ?>
</section>
