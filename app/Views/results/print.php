<?php
/** @var array $config */
/** @var list<array> $disciplines */
/** @var list<array{discipline: array, protocol: list<array>}> $sections */
/** @var int|null $disciplineId */

$base = $config['base_url'] ?? '';
$eventDate = (string) ($config['event_date'] ?? '');
$eventPlace = (string) ($config['event_place'] ?? '');
$appName = (string) ($config['app_name'] ?? 'Alash Gym');
$orgShort = 'Alash Gym';
$single = $disciplineId !== null && $disciplineId > 0;
$printedAt = date('d.m.Y H:i');
$docNo = 'ПР-' . date('Ymd');

$titleDisc = '';
if ($single && !empty($sections[0]['discipline']['name_ru'])) {
    $titleDisc = (string) $sections[0]['discipline']['name_ru'];
}

function print_fmt_result(float $v, string $scoring): string {
    return $scoring === 'weight'
        ? number_format($v, 1, '.', '')
        : number_format($v, 0, '.', '');
}

function print_unit(string $scoring, bool $isFolk): string {
    return match (true) {
        $isFolk || $scoring === 'reps' => 'раз',
        $scoring === 'arm' => 'побед',
        default => 'кг',
    };
}

function print_result_label(string $scoring, bool $isFolk): string {
    return $isFolk || $scoring === 'reps' ? 'Результат, раз' : ($scoring === 'arm' ? 'Побед' : 'Результат, кг');
}

function print_gender(string $g): string {
    return strtoupper($g) === 'F' ? 'женщины' : 'мужчины';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Официальный протокол<?= $titleDisc !== '' ? ' — ' . htmlspecialchars($titleDisc) : '' ?></title>
  <style>
    @page {
      size: A4;
      margin: 16mm 14mm 18mm 14mm;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: #000;
      background: #e8e8e8;
      font: 11pt/1.35 "Times New Roman", Times, "Noto Serif", serif;
    }
    .print-bar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
      justify-content: space-between;
      padding: 0.65rem 1rem;
      background: #222;
      color: #fff;
      font-family: "Segoe UI", Arial, sans-serif;
      font-size: 0.85rem;
    }
    .print-bar p { margin: 0; opacity: 0.85; }
    .print-bar-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .print-btn {
      appearance: none;
      border: 1px solid #fff;
      background: #fff;
      color: #111;
      border-radius: 0.25rem;
      padding: 0.4rem 0.85rem;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
    }
    .print-btn-ghost {
      background: transparent;
      color: #fff;
    }

    .sheet {
      width: 210mm;
      min-height: 297mm;
      margin: 12px auto;
      padding: 14mm 12mm 16mm;
      background: #fff;
      box-shadow: 0 2px 18px rgba(0,0,0,0.18);
    }

    .org-line {
      text-align: center;
      font-size: 10pt;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin: 0 0 2px;
    }
    .org-sub {
      text-align: center;
      font-size: 9pt;
      margin: 0 0 10px;
    }
    .doc-rule {
      border: 0;
      border-top: 2px solid #000;
      margin: 0 0 2px;
    }
    .doc-rule-thin {
      border: 0;
      border-top: 1px solid #000;
      margin: 0 0 12px;
    }

    .approve {
      display: flex;
      justify-content: flex-end;
      margin: 0 0 14px;
    }
    .approve-box {
      width: 48%;
      font-size: 9.5pt;
      line-height: 1.4;
    }
    .approve-box strong {
      display: block;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 4px;
    }
    .approve-line {
      margin-top: 18px;
      border-bottom: 1px solid #000;
      height: 16px;
    }
    .approve-hint {
      font-size: 8pt;
      color: #444;
      text-align: center;
      margin-top: 2px;
    }

    .doc-title {
      text-align: center;
      margin: 0 0 6px;
      font-size: 15pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .doc-subtitle {
      text-align: center;
      margin: 0 0 14px;
      font-size: 12pt;
      font-weight: 700;
    }

    .meta {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 14px;
      font-size: 10pt;
    }
    .meta th,
    .meta td {
      border: 1px solid #000;
      padding: 5px 8px;
      vertical-align: top;
    }
    .meta th {
      width: 28%;
      text-align: left;
      font-weight: 700;
      background: #f3f3f3;
    }

    .disc {
      margin: 0 0 16px;
    }
    .disc-head {
      margin: 0 0 8px;
      padding: 5px 8px;
      border: 1px solid #000;
      background: #f3f3f3;
      font-size: 12pt;
      font-weight: 700;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .cat {
      margin: 0 0 12px;
      page-break-inside: avoid;
    }
    .cat-head {
      margin: 0 0 4px;
      font-size: 11pt;
      font-weight: 700;
    }
    .cat-head span {
      font-weight: 400;
      font-style: italic;
    }

    table.protocol {
      width: 100%;
      border-collapse: collapse;
      font-size: 10pt;
    }
    table.protocol th,
    table.protocol td {
      border: 1px solid #000;
      padding: 4px 6px;
      vertical-align: middle;
    }
    table.protocol th {
      background: #f3f3f3;
      font-size: 9pt;
      font-weight: 700;
      text-align: center;
    }
    .c-n { width: 8%; text-align: center; }
    .c-place { width: 10%; text-align: center; font-weight: 700; }
    .c-name { text-align: left; }
    .c-num { width: 14%; text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .c-lot { width: 10%; text-align: center; }
    .empty {
      margin: 8px 0;
      font-style: italic;
      font-size: 10pt;
    }

    .notes {
      margin: 10px 0 0;
      font-size: 9pt;
      color: #222;
    }
    .notes ol {
      margin: 4px 0 0;
      padding-left: 1.2rem;
    }

    .signs {
      margin-top: 22px;
      page-break-inside: avoid;
    }
    .signs-title {
      margin: 0 0 10px;
      font-size: 10pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .signs-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px 28px;
    }
    .sign-card {
      font-size: 9.5pt;
    }
    .sign-role {
      font-weight: 700;
      margin-bottom: 16px;
    }
    .sign-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 10px;
    }
    .sign-field {
      border-bottom: 1px solid #000;
      min-height: 18px;
    }
    .sign-hint {
      font-size: 8pt;
      color: #555;
      text-align: center;
      margin-top: 2px;
    }
    .stamp {
      margin-top: 18px;
      border: 1px dashed #666;
      width: 120px;
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 8.5pt;
      color: #666;
      text-align: center;
      line-height: 1.3;
    }

    .footer-note {
      margin-top: 18px;
      padding-top: 6px;
      border-top: 1px solid #000;
      font-size: 8pt;
      color: #444;
      display: flex;
      justify-content: space-between;
      gap: 1rem;
    }

    @media print {
      body { background: #fff; }
      .print-bar { display: none !important; }
      .sheet {
        width: auto;
        min-height: 0;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }
      .disc { break-inside: avoid; }
      .disc + .disc { break-before: page; }
      .meta th,
      table.protocol th,
      .disc-head {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
</head>
<body>
  <div class="print-bar">
    <p>Официальный бланк · в печати выберите «Сохранить как PDF»</p>
    <div class="print-bar-actions">
      <button type="button" class="print-btn" onclick="window.print()">Печать / PDF</button>
      <a class="print-btn print-btn-ghost" href="<?= htmlspecialchars($base) ?>/results<?= $single ? '?discipline=' . (int) $disciplineId : '' ?>">Назад</a>
    </div>
  </div>

  <div class="sheet">
    <p class="org-line"><?= htmlspecialchars($orgShort) ?></p>
    <p class="org-sub">Силовые соревнования</p>
    <hr class="doc-rule">
    <hr class="doc-rule-thin">

    <div class="approve">
      <div class="approve-box">
        <strong>Утверждаю</strong>
        Главный судья соревнований
        <div class="approve-line"></div>
        <div class="approve-hint">подпись, ФИО</div>
        <div class="approve-line" style="margin-top:10px"></div>
        <div class="approve-hint">дата</div>
      </div>
    </div>

    <h1 class="doc-title">Официальный протокол</h1>
    <p class="doc-subtitle">
      результатов соревнований
      <?php if ($titleDisc !== ''): ?>
        <br>«<?= htmlspecialchars($titleDisc) ?>»
      <?php endif; ?>
    </p>

    <table class="meta">
      <tr>
        <th>Документ №</th>
        <td><?= htmlspecialchars($docNo) ?></td>
      </tr>
      <tr>
        <th>Дата проведения</th>
        <td><?= $eventDate !== '' ? htmlspecialchars($eventDate) : '____________________' ?></td>
      </tr>
      <tr>
        <th>Место проведения</th>
        <td><?= $eventPlace !== '' ? htmlspecialchars($eventPlace) : '____________________' ?></td>
      </tr>
      <tr>
        <th>Организатор</th>
        <td><?= htmlspecialchars($appName) ?></td>
      </tr>
    </table>

    <?php if (empty($sections)): ?>
      <p class="empty">Данные для протокола отсутствуют.</p>
    <?php else: ?>
      <?php foreach ($sections as $si => $section): ?>
        <?php
          $d = $section['discipline'];
          $protocol = $section['protocol'];
          $absolute = $section['absolute'] ?? [];
          $scoring = $d['scoring_type'] ?? 'weight';
          $isFolk = ($d['code'] ?? '') === \App\Models\FolkBench::CODE;
          $useWilks = $scoring === 'weight' && !$isFolk;
          $unit = print_unit($scoring, $isFolk);
          $resultLabel = print_result_label($scoring, $isFolk);
        ?>
        <section class="disc">
          <?php if (!$single || count($sections) > 1): ?>
            <div class="disc-head">Дисциплина: <?= htmlspecialchars($d['name_ru'] ?? '') ?></div>
          <?php endif; ?>

          <?php if (!empty($absolute)): ?>
            <?php foreach ($absolute as $absBlock): ?>
              <?php
                $absRows = $absBlock['rows'] ?? [];
                if ($absRows === []) {
                    continue;
                }
                $ageLabel = (string) ($absBlock['age_group'] ?? '');
              ?>
              <div class="cat">
                <div class="cat-head">
                  Абсолютка<?= $ageLabel !== '' ? ': ' . htmlspecialchars($ageLabel) : '' ?>
                  <span>(<?= $useWilks ? 'по коэффициенту Wilks' : 'по результату' ?>)</span>
                </div>
                <table class="protocol">
                  <thead>
                    <tr>
                      <th class="c-n">№</th>
                      <th class="c-place">Место</th>
                      <th class="c-name">Фамилия, имя, отчество</th>
                      <th class="c-num">ВК</th>
                      <th class="c-num"><?= htmlspecialchars($resultLabel) ?></th>
                      <?php if ($useWilks): ?>
                        <th class="c-num">Коэф</th>
                      <?php endif; ?>
                      <th class="c-num">Собств. вес, кг</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($absRows as $ri => $row): ?>
                      <tr>
                        <td class="c-n"><?= $ri + 1 ?></td>
                        <td class="c-place"><?= (int) ($row['place'] ?? $row['abs_place'] ?? 0) ?></td>
                        <td class="c-name"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td class="c-num"><?= htmlspecialchars((string) ($row['category_short'] ?? '—')) ?></td>
                        <td class="c-num">
                          <?= htmlspecialchars(print_fmt_result((float) $row['best_result'], $scoring)) ?>
                          <?= htmlspecialchars($unit) ?>
                        </td>
                        <?php if ($useWilks): ?>
                          <td class="c-num">
                            <?= $row['wilks'] !== null ? number_format((float) $row['wilks'], 3, ',', '') : '—' ?>
                          </td>
                        <?php endif; ?>
                        <td class="c-num"><?= number_format((float) $row['body_weight'], 2, '.', '') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (empty($protocol) && empty($absolute)): ?>
            <p class="empty">Зачётные результаты отсутствуют.</p>
          <?php else: ?>
            <?php foreach ($protocol as $group): ?>
              <?php $gender = print_gender((string) ($group['gender'] ?? 'M')); ?>
              <div class="cat">
                <div class="cat-head">
                  Весовая категория: <?= htmlspecialchars($group['category']) ?>
                  <span>(<?= htmlspecialchars($gender) ?>)</span>
                </div>
                <table class="protocol">
                  <thead>
                    <tr>
                      <th class="c-n">№</th>
                      <th class="c-place">Место</th>
                      <th class="c-name">Фамилия, имя, отчество</th>
                      <th class="c-num"><?= htmlspecialchars($resultLabel) ?></th>
                      <th class="c-num">Собств. вес, кг</th>
                      <th class="c-lot">Жребий</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($group['rows'] as $ri => $row): ?>
                      <?php $pl = (int) $row['place']; ?>
                      <tr>
                        <td class="c-n"><?= $ri + 1 ?></td>
                        <td class="c-place"><?= $pl ?></td>
                        <td class="c-name"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td class="c-num">
                          <?= htmlspecialchars(print_fmt_result((float) $row['best_result'], $scoring)) ?>
                          <?= htmlspecialchars($unit) ?>
                        </td>
                        <td class="c-num"><?= number_format((float) $row['body_weight'], 2, '.', '') ?></td>
                        <td class="c-lot">
                          <?= !empty($row['lot_number']) ? (int) $row['lot_number'] : '—' ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>

      <div class="notes">
        <strong>Примечания:</strong>
        <ol>
          <li>В зачёт принимается лучший действительный результат участника.</li>
          <li>При равенстве результатов преимущество получает участник с меньшим собственным весом.</li>
          <li>Абсолютка: силовые дисциплины — по коэффициенту Wilks; народный жим и повторы — по лучшему результату. Отдельно для Юниоров и Открытой.</li>
          <li>Протокол составлен по итогам официальных попыток на помосте.</li>
        </ol>
      </div>

      <div class="signs">
        <div class="signs-title">Подписи должностных лиц</div>
        <div class="signs-grid">
          <div class="sign-card">
            <div class="sign-role">Главный судья</div>
            <div class="sign-row">
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">подпись</div>
              </div>
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">ФИО</div>
              </div>
            </div>
          </div>
          <div class="sign-card">
            <div class="sign-role">Главный секретарь</div>
            <div class="sign-row">
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">подпись</div>
              </div>
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">ФИО</div>
              </div>
            </div>
          </div>
          <div class="sign-card">
            <div class="sign-role">Судья на помосте</div>
            <div class="sign-row">
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">подпись</div>
              </div>
              <div>
                <div class="sign-field"></div>
                <div class="sign-hint">ФИО</div>
              </div>
            </div>
          </div>
          <div class="sign-card">
            <div class="stamp">Место<br>для печати<br>М.П.</div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="footer-note">
      <span>Сформировано: <?= htmlspecialchars($printedAt) ?></span>
      <span><?= htmlspecialchars($orgShort) ?> · официальный протокол</span>
    </div>
  </div>

  <script>
    (function () {
      var params = new URLSearchParams(window.location.search);
      if (params.get('auto') === '1') {
        window.addEventListener('load', function () {
          setTimeout(function () { window.print(); }, 250);
        });
      }
    })();
  </script>
</body>
</html>
