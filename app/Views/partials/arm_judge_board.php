<?php
/**
 * Панель судейства армрестлинга (пары, победы, табло).
 *
 * @var array $config
 * @var array $byCategory
 * @var array $wins
 * @var string $returnTo  protocol|arm
 */
$base = $config['base_url'] ?? '';
$byCategory = $byCategory ?? [];
$wins = $wins ?? [];
$returnTo = $returnTo ?? 'protocol';

$catKeys = array_keys($byCategory);
$totalPeople = 0;
$liveMatch = null;
foreach ($byCategory as $block) {
    $totalPeople += count($block['people'] ?? []);
    foreach (($block['rounds'] ?? []) as $list) {
        foreach ($list as $m) {
            if (!empty($m['is_current'])) {
                $liveMatch = $m;
            }
        }
    }
}
?>

<?php if (empty($byCategory)): ?>
  <div class="aj-empty">
    <p class="aj-empty-title">Нет взвешенных участников</p>
    <p class="aj-empty-text">Сначала примите заявки и проведите взвешивание.</p>
    <a class="btn-primary-sm" href="<?= htmlspecialchars($base) ?>/judge/weigh-in">К взвешиванию</a>
  </div>
<?php else: ?>
  <div class="aj">
    <div class="aj-top">
      <div class="aj-top-stats">
        <span><strong><?= count($catKeys) ?></strong> ВК</span>
        <span><strong><?= $totalPeople ?></strong> уч.</span>
        <?php if ($liveMatch): ?>
          <span class="aj-live-pill">
            <span class="aj-live-dot" aria-hidden="true"></span>
            на табло · <?= htmlspecialchars((string) $liveMatch['p1_name']) ?>
            vs
            <?= htmlspecialchars((string) ($liveMatch['p2_name'] ?: 'bye')) ?>
          </span>
        <?php endif; ?>
      </div>
      <?php if (count($catKeys) > 1): ?>
        <nav class="aj-cats" aria-label="Весовые категории">
          <?php foreach ($catKeys as $i => $cat): ?>
            <a class="aj-cat-jump" href="#aj-cat-<?= $i ?>"><?= htmlspecialchars($cat) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <?php $catIdx = 0; foreach ($byCategory as $cat => $block): ?>
      <?php
        $people = $block['people'] ?? [];
        $rounds = $block['rounds'] ?? [];
        $maxRound = (int) ($block['max_round'] ?? 0);
        $canGenerate = count($people) >= 2 || $maxRound > 0;
        ksort($rounds);
        $pendingCount = 0;
        $doneCount = 0;
        foreach ($rounds as $list) {
            foreach ($list as $m) {
                if (($m['status'] ?? '') === 'done') $doneCount++;
                else $pendingCount++;
            }
        }
      ?>
      <section class="aj-cat" id="aj-cat-<?= $catIdx ?>">
        <header class="aj-cat-head">
          <div class="aj-cat-id">
            <h2 class="aj-cat-title"><?= htmlspecialchars($cat) ?></h2>
            <p class="aj-cat-meta">
              <?= count($people) ?> чел.
              <?php if ($maxRound > 0): ?>
                · раунд <?= $maxRound ?>
                · <?= $doneCount ?>/<?= $doneCount + $pendingCount ?> боёв
              <?php endif; ?>
            </p>
          </div>
          <?php if ($canGenerate): ?>
            <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/generate">
              <input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>">
              <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
              <button class="aj-btn aj-btn--primary" type="submit">
                <?= $maxRound === 0 ? 'Составить пары' : 'Следующий раунд' ?>
              </button>
            </form>
          <?php endif; ?>
        </header>

        <?php if ($people): ?>
          <div class="aj-roster" aria-label="Участники категории">
            <?php foreach ($people as $p): ?>
              <?php $pid = (int) $p['id']; ?>
              <div class="aj-chip">
                <span class="aj-chip-name"><?= htmlspecialchars($p['full_name']) ?></span>
                <span class="aj-chip-meta">
                  <?= number_format((float) $p['body_weight'], 1, '.', '') ?>
                  <span class="aj-chip-w"><?= (int) ($wins[$pid] ?? 0) ?>W</span>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (empty($rounds)): ?>
          <div class="aj-hint">
            <p>Пары ещё не составлены.</p>
            <p>Нажмите «Составить пары» — жребий внутри категории.</p>
          </div>
        <?php else: ?>
          <?php foreach ($rounds as $roundNo => $list): ?>
            <div class="aj-round">
              <div class="aj-round-label">
                <span>Раунд <?= (int) $roundNo ?></span>
                <span class="aj-round-count"><?= count($list) ?></span>
              </div>
              <div class="aj-fights">
                <?php foreach ($list as $m): ?>
                  <?php
                    $mid = (int) $m['id'];
                    $p1 = (int) $m['participant1_id'];
                    $p2 = $m['participant2_id'] !== null ? (int) $m['participant2_id'] : null;
                    $done = ($m['status'] ?? '') === 'done';
                    $winner = $m['winner_id'] !== null ? (int) $m['winner_id'] : null;
                    $isCurrent = !empty($m['is_current']);
                    $isBye = $p2 === null;
                    $state = $isBye ? 'bye' : ($done ? 'done' : ($isCurrent ? 'live' : 'wait'));
                  ?>
                  <article class="aj-fight is-<?= $state ?>">
                    <div class="aj-fight-rail">
                      <span class="aj-fight-no">#<?= (int) $m['match_no'] ?></span>
                      <?php if ($isCurrent): ?>
                        <span class="aj-fight-tag aj-fight-tag--live">табло</span>
                      <?php elseif ($isBye): ?>
                        <span class="aj-fight-tag">bye</span>
                      <?php elseif ($done): ?>
                        <span class="aj-fight-tag">готово</span>
                      <?php else: ?>
                        <span class="aj-fight-tag aj-fight-tag--wait">ожидает</span>
                      <?php endif; ?>
                    </div>

                    <div class="aj-sides">
                      <div class="aj-side<?= $winner === $p1 ? ' is-win' : ($done && !$isBye ? ' is-loss' : '') ?>">
                        <div class="aj-side-body">
                          <strong class="aj-side-name"><?= htmlspecialchars($m['p1_name']) ?></strong>
                          <span class="aj-side-sub">
                            <?= number_format((float) $m['p1_bw'], 1, '.', '') ?> кг
                            · <?= (int) ($wins[$p1] ?? 0) ?>W
                          </span>
                        </div>
                        <?php if (!$done && !$isBye): ?>
                          <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner">
                            <input type="hidden" name="match_id" value="<?= $mid ?>">
                            <input type="hidden" name="winner_id" value="<?= $p1 ?>">
                            <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
                            <button class="aj-win" type="submit">Победа</button>
                          </form>
                        <?php elseif ($winner === $p1): ?>
                          <span class="aj-crown">W</span>
                        <?php endif; ?>
                      </div>

                      <div class="aj-mid" aria-hidden="true"><?= $isBye ? 'BYE' : 'VS' ?></div>

                      <div class="aj-side aj-side--right<?= $winner === $p2 ? ' is-win' : ($done && !$isBye ? ' is-loss' : '') ?>">
                        <?php if ($isBye): ?>
                          <div class="aj-side-body">
                            <strong class="aj-side-name is-muted">свободен</strong>
                            <span class="aj-side-sub">проход без боя</span>
                          </div>
                        <?php else: ?>
                          <div class="aj-side-body">
                            <strong class="aj-side-name"><?= htmlspecialchars((string) $m['p2_name']) ?></strong>
                            <span class="aj-side-sub">
                              <?= number_format((float) $m['p2_bw'], 1, '.', '') ?> кг
                              · <?= (int) ($wins[$p2] ?? 0) ?>W
                            </span>
                          </div>
                          <?php if (!$done): ?>
                            <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner">
                              <input type="hidden" name="match_id" value="<?= $mid ?>">
                              <input type="hidden" name="winner_id" value="<?= $p2 ?>">
                              <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
                              <button class="aj-win" type="submit">Победа</button>
                            </form>
                          <?php elseif ($winner === $p2): ?>
                            <span class="aj-crown">W</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>

                    <?php if (!$isBye && (!$isCurrent || $done)): ?>
                      <div class="aj-fight-tools">
                        <?php if (!$isCurrent): ?>
                          <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/current">
                            <input type="hidden" name="match_id" value="<?= $mid ?>">
                            <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
                            <button class="aj-btn aj-btn--ghost" type="submit">На табло</button>
                          </form>
                        <?php endif; ?>
                        <?php if ($done): ?>
                          <form method="post" action="<?= htmlspecialchars($base) ?>/judge/arm/winner">
                            <input type="hidden" name="match_id" value="<?= $mid ?>">
                            <input type="hidden" name="action" value="clear">
                            <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
                            <button class="aj-btn aj-btn--ghost" type="submit">Сбросить</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
      <?php $catIdx++; ?>
    <?php endforeach; ?>

    <p class="aj-foot">
      Пары по жребию внутри категории. Нечётное число — bye. Победы пишутся в протокол сами.
    </p>
  </div>
<?php endif; ?>
