<?php
/**
 * Таблица протокола армрестлинга (места + бои).
 *
 * @var array $armProtocol
 * @var array $config
 * @var bool  $isJudge
 */
$base = $config['base_url'] ?? '';
$isJudge = !empty($isJudge);
$categories = $armProtocol['categories'] ?? [];
$matches = $armProtocol['matches'] ?? [];
$totalAthletes = (int) ($armProtocol['total_athletes'] ?? 0);
$totalMatches = (int) ($armProtocol['total_matches'] ?? 0);
$doneMatches = (int) ($armProtocol['done_matches'] ?? 0);
?>

<?php if (empty($categories)): ?>
  <div class="judge-empty panel">
    <p class="judge-empty-title">Нет взвешенных участников</p>
    <p class="text-muted mt-2">Сначала примите заявки и проведите взвешивание.</p>
    <?php if ($isJudge): ?>
      <a class="btn-primary-sm mt-3" href="<?= htmlspecialchars($base) ?>/judge/weigh-in">К взвешиванию</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="proto-scroll" id="tableScrollWrap">
    <table class="proto-table proto-table--arm" id="armStandings">
      <thead>
        <tr>
          <th class="proto-col-lot" title="Жребий">Жр</th>
          <th class="proto-col-name text-left">Спортсмен</th>
          <th class="proto-col-bw">Вес</th>
          <th class="proto-col-cat">ВК</th>
          <th class="proto-col-wl" title="Победы">W</th>
          <th class="proto-col-wl" title="Поражения">L</th>
          <th class="proto-col-place">М</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $catIdx => $catGroup): ?>
          <?php
            $catShort = $catGroup['category_short'];
            $groupKey = (string) ($catGroup['group_key'] ?? $catShort);
            $ageLabel = (string) ($catGroup['age_group'] ?? '');
            $athletesCount = count($catGroup['athletes']);
          ?>
          <tr class="proto-cat-row" data-cat-header="<?= htmlspecialchars($groupKey) ?>">
            <td colspan="7">
              <?php if ($ageLabel !== ''): ?>
                <span class="proto-age-label<?= $ageLabel === 'Юниоры' ? ' proto-age-label--jr' : ' proto-age-label--open' ?>"><?= htmlspecialchars($ageLabel) ?></span>
              <?php endif; ?>
              <span class="proto-cat-label"><?= htmlspecialchars($catShort) ?></span>
              <span class="proto-cat-count"><?= $athletesCount ?></span>
            </td>
          </tr>
          <?php foreach ($catGroup['athletes'] as $athIdx => $ath): ?>
            <?php
              $rowClasses = ['proto-row', 'pt-row'];
              if ($athIdx % 2 === 1) $rowClasses[] = 'is-alt';
            ?>
            <tr class="<?= implode(' ', $rowClasses) ?>"
                data-cat="<?= htmlspecialchars($groupKey) ?>"
                data-name="<?= htmlspecialchars(mb_strtolower($ath['full_name'])) ?>">
              <td class="proto-cell proto-col-lot"><?= htmlspecialchars((string) $ath['lot_number']) ?></td>
              <td class="proto-cell proto-col-name text-left">
                <strong class="proto-ath"><?= htmlspecialchars(mb_strtoupper($ath['full_name'])) ?></strong>
                <?php if (!empty($ath['age_group'])): ?>
                  <span class="proto-meta"><?= htmlspecialchars((string) $ath['age_group']) ?></span>
                <?php endif; ?>
              </td>
              <td class="proto-cell proto-col-bw"><?= htmlspecialchars($ath['body_weight_fmt']) ?></td>
              <td class="proto-cell proto-col-cat"><strong><?= htmlspecialchars($catShort) ?></strong></td>
              <td class="proto-cell proto-col-wl"><strong><?= (int) $ath['wins'] ?></strong></td>
              <td class="proto-cell proto-col-wl"><?= (int) $ath['losses'] ?></td>
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
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="arm-proto-matches panel">
    <header class="arm-proto-matches-head">
      <h2 class="arm-proto-matches-title">Бои</h2>
      <p class="arm-proto-matches-sub">
        <?= $doneMatches ?> / <?= $totalMatches ?> завершено
        · <?= $totalAthletes ?> уч.
      </p>
    </header>

    <?php if (empty($matches)): ?>
      <p class="arm-empty-hint">Пары ещё не составлены.<?= $isJudge ? ' Составьте их в разделе «Арм».' : '' ?></p>
    <?php else: ?>
      <div class="proto-scroll">
        <table class="proto-table proto-table--arm-matches">
          <thead>
            <tr>
              <th>ВК</th>
              <th>Раунд</th>
              <th>Бой</th>
              <th class="text-left">Участник 1</th>
              <th class="text-left">Участник 2</th>
              <th class="text-left">Победитель</th>
              <th>Статус</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matches as $idx => $m): ?>
              <?php
                $done = ($m['status'] ?? '') === 'done';
                $isCurrent = !empty($m['is_current']);
                $isBye = $m['participant2_id'] === null;
                $statusLabel = $isBye ? 'bye'
                  : ($done ? 'готово' : ($isCurrent ? 'на табло' : 'ожидает'));
                $rowClass = 'proto-row';
                if ($idx % 2 === 1) $rowClass .= ' is-alt';
                if ($isCurrent) $rowClass .= ' is-stage';
                if ($done) $rowClass .= ' is-done-arm';
              ?>
              <tr class="<?= $rowClass ?>">
                <td class="proto-cell"><?= htmlspecialchars(\App\Models\PowerProtocol::formatCategoryShort($m['weight_category'] ?? '')) ?></td>
                <td class="proto-cell"><?= (int) $m['round_no'] ?></td>
                <td class="proto-cell"><?= (int) $m['match_no'] ?></td>
                <td class="proto-cell text-left<?= ((int) ($m['winner_id'] ?? 0) === (int) $m['participant1_id']) ? ' is-arm-winner' : '' ?>">
                  <?= htmlspecialchars((string) $m['p1_name']) ?>
                </td>
                <td class="proto-cell text-left<?= ((int) ($m['winner_id'] ?? 0) === (int) ($m['participant2_id'] ?? 0)) ? ' is-arm-winner' : '' ?>">
                  <?= $isBye ? '—' : htmlspecialchars((string) $m['p2_name']) ?>
                </td>
                <td class="proto-cell text-left">
                  <?php if ($done && !empty($m['winner_name'])): ?>
                    <strong><?= htmlspecialchars((string) $m['winner_name']) ?></strong>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="proto-cell">
                  <span class="arm-status arm-status--<?= htmlspecialchars($isBye ? 'bye' : ($done ? 'done' : ($isCurrent ? 'live' : 'wait'))) ?>">
                    <?= htmlspecialchars($statusLabel) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
