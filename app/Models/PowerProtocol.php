<?php

namespace App\Models;

class PowerProtocol
{
    /**
     * Wilks formula (men & women).
     * Exact formula used by IPF / WRPF / powertable.ru.
     */
    public static function wilks(float $bodyWeight, float $liftedWeight, string $gender = 'M'): float
    {
        if ($bodyWeight <= 0 || $liftedWeight <= 0) {
            return 0.0;
        }

        if (strtoupper($gender) === 'F') {
            $a = 594.31747775582;
            $b = -27.23842536447;
            $c = 0.82112226871;
            $d = -0.00930733913;
            $e = 0.00004731582;
            $f = -0.00000009054;
        } else {
            $a = -216.0475144;
            $b = 16.2606339;
            $c = -0.002388645;
            $d = -0.00113732;
            $e = 0.00000701863;
            $f = -0.00000001291;
        }

        $bw = $bodyWeight;
        $denom = $a + ($b * $bw) + ($c * ($bw ** 2)) + ($d * ($bw ** 3)) + ($e * ($bw ** 4)) + ($f * ($bw ** 5));
        if ($denom <= 0) {
            return 0.0;
        }

        $coeff = 500.0 / $denom;
        return round($liftedWeight * $coeff, 3);
    }

    /**
     * Clean short label for weight category (e.g. "до 67.5 кг" -> "67,5", "100+ кг" -> "100+").
     */
    public static function formatCategoryShort(?string $cat): string
    {
        $raw = trim((string) $cat);
        if ($raw === '') {
            return '—';
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*\+/u', $raw, $m)) {
            return str_replace('.', ',', $m[1]) . '+';
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m)) {
            return str_replace('.', ',', $m[1]);
        }

        return $raw;
    }

    /**
     * Numeric weight for sorting weight categories.
     */
    public static function categorySortKey(?string $cat): float
    {
        $raw = trim((string) $cat);
        if ($raw === '') {
            return 9999.0;
        }

        if (str_contains($raw, '+')) {
            if (preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m)) {
                return (float) str_replace(',', '.', $m[1]) + 1000.0;
            }
            return 9000.0;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return 500.0;
    }

    /**
     * Classification standards for Bench Press RAW (as shown on powertable.ru).
     *
     * @return list<array{category: string, cat_key: string, msmk: float, ms: float, kms: float, r1: float, r2: float, r3: float}>
     */
    public static function normatives(string $disciplineCode = 'bench'): array
    {
        return [
            ['category' => 'Мужчины: 60',   'cat_key' => '60',   'msmk' => 130.0, 'ms' => 115.0, 'kms' => 100.0, 'r1' => 87.5,  'r2' => 77.5,  'r3' => 67.5],
            ['category' => 'Мужчины: 67,5', 'cat_key' => '67,5', 'msmk' => 145.0, 'ms' => 127.5, 'kms' => 110.0, 'r1' => 97.5,  'r2' => 87.5,  'r3' => 77.5],
            ['category' => 'Мужчины: 75',   'cat_key' => '75',   'msmk' => 157.5, 'ms' => 137.5, 'kms' => 120.0, 'r1' => 107.5, 'r2' => 97.5,  'r3' => 85.0],
            ['category' => 'Мужчины: 82,5', 'cat_key' => '82,5', 'msmk' => 170.0, 'ms' => 147.5, 'kms' => 130.0, 'r1' => 115.0, 'r2' => 102.5, 'r3' => 90.0],
            ['category' => 'Мужчины: 90',   'cat_key' => '90',   'msmk' => 177.5, 'ms' => 155.0, 'kms' => 135.0, 'r1' => 122.5, 'r2' => 107.5, 'r3' => 92.5],
            ['category' => 'Мужчины: 100',  'cat_key' => '100',  'msmk' => 187.5, 'ms' => 162.5, 'kms' => 142.5, 'r1' => 130.0, 'r2' => 110.0, 'r3' => 97.5],
            ['category' => 'Мужчины: 110',  'cat_key' => '110',  'msmk' => 195.0, 'ms' => 167.5, 'kms' => 147.5, 'r1' => 135.0, 'r2' => 115.0, 'r3' => 100.0],
            ['category' => 'Мужчины: 125',  'cat_key' => '125',  'msmk' => 202.5, 'ms' => 175.0, 'kms' => 155.0, 'r1' => 140.0, 'r2' => 120.0, 'r3' => 105.0],
            ['category' => 'Мужчины: 140+', 'cat_key' => '140+', 'msmk' => 212.5, 'ms' => 182.5, 'kms' => 162.5, 'r1' => 147.5, 'r2' => 125.0, 'r3' => 110.0],
        ];
    }

    /**
     * Determine sport rank achieved.
     */
    public static function determineRank(float $catWeight, float $bestLift, string $disciplineCode = 'bench'): string
    {
        if ($bestLift <= 0) {
            return '';
        }

        $norms = self::normatives($disciplineCode);
        $closest = null;
        $minDiff = 9999.0;

        foreach ($norms as $norm) {
            $val = (float) str_replace(['+', ','], ['', '.'], $norm['cat_key']);
            $diff = abs($val - $catWeight);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $norm;
            }
        }

        if (!$closest) {
            return '';
        }

        if ($bestLift >= $closest['msmk']) {
            return 'МСМК';
        }
        if ($bestLift >= $closest['ms']) {
            return 'МС';
        }
        if ($bestLift >= $closest['kms']) {
            return 'КМС';
        }
        if ($bestLift >= $closest['r1']) {
            return '1';
        }
        if ($bestLift >= $closest['r2']) {
            return '2';
        }
        if ($bestLift >= $closest['r3']) {
            return '3';
        }

        return '';
    }

    /**
     * Records table for the discipline (matching powertable.ru layout).
     *
     * @return list<array{age_group: string, discipline: string, category: string, level: string, country: string, record: string}>
     */
    public static function records(string $disciplineName = 'Любители Жим штанги лежа без экипировки'): array
    {
        return [
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '60',   'level' => 'Мир', 'country' => 'Казахстан', 'record' => '155,0'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '67,5', 'level' => 'Мир', 'country' => 'Казахстан', 'record' => '172,5'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '75',   'level' => 'Мир', 'country' => 'Казахстан', 'record' => '195,0'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '82,5', 'level' => 'Мир', 'country' => 'Казахстан', 'record' => '217,5'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '90',   'level' => 'Мир', 'country' => 'Казахстан', 'record' => '232,5'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '100',  'level' => 'Мир', 'country' => 'Казахстан', 'record' => '250,0'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '110',  'level' => 'Мир', 'country' => 'Казахстан', 'record' => '262,5'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '125',  'level' => 'Мир', 'country' => 'Казахстан', 'record' => '277,5'],
            ['age_group' => 'Открытая (24-99)', 'discipline' => $disciplineName, 'category' => '140+', 'level' => 'Мир', 'country' => 'Казахстан', 'record' => '310,0'],
        ];
    }

    /**
     * Builds structured protocol data grouped by weight category.
     *
     * @param array $discipline
     * @param list<array> $participants
     * @param array<int, array<int, array>> $attemptsMap [participant_id => [attempt_no => attempt]]
     * @param list<array> $queue Platform queue
     * @param array|null $current Current athlete on platform
     * @return array{
     *   categories: list<array{
     *     category: string,
     *     category_short: string,
     *     sort_key: float,
     *     athletes: list<array>
     *   }>,
     *   total_athletes: int,
     *   judged_attempts: int
     * }
     */
    public static function buildProtocol(
        array $discipline,
        array $participants,
        array $attemptsMap,
        array $queue = [],
        ?array $current = null
    ): array {
        $did = (int) ($discipline['id'] ?? 0);
        $scoring = (string) ($discipline['scoring_type'] ?? 'weight');
        $code = (string) ($discipline['code'] ?? 'bench');
        $isFolk = FolkBench::isFolk($discipline);
        $isReps = $scoring === 'reps' || $isFolk;
        $maxAttempts = (int) ($discipline['attempts_allowed'] ?? 3);
        // Классический жим/бицепс — минимум 3 попытки в таблице; народный — 1 (повторы)
        if (!$isReps && $maxAttempts < 3) {
            $maxAttempts = 3;
        }
        if ($isReps && $maxAttempts < 1) {
            $maxAttempts = 1;
        }

        // Build queue map by participant_id
        $queueMap = [];
        if ($current) {
            $currentPid = (int) ($current['participant_id'] ?? 0);
            $queueMap[$currentPid] = [
                'status'          => 'current',
                'pos'             => 0,
                'label'           => 'СЕЙЧАС',
                'attempt_no'      => (int) ($current['attempt_no'] ?? 1),
                'declared_weight' => (float) ($current['declared_weight'] ?? 0),
                'deferred'        => false,
            ];
        }

        $posCounter = 1;
        foreach ($queue as $qItem) {
            $qPid = (int) $qItem['participant_id'];
            $isCurrent = $current && (($qItem['queue_key'] ?? '') === ($current['queue_key'] ?? ''));
            if ($isCurrent) {
                continue;
            }
            if (!isset($queueMap[$qPid])) {
                $isNext = ($posCounter === 1);
                $isDef = !empty($qItem['deferred']);
                $queueMap[$qPid] = [
                    'status'          => $isNext ? 'next' : ($isDef ? 'deferred' : 'queue'),
                    'pos'             => $posCounter,
                    'label'           => $isNext ? 'ДАЛЕЕ' : ($isDef ? 'Не пришёл' : "#{$posCounter}"),
                    'attempt_no'      => (int) $qItem['attempt_no'],
                    'declared_weight' => (float) $qItem['declared_weight'],
                    'deferred'        => $isDef,
                ];
                $posCounter++;
            }
        }

        $grouped = [];
        $totalJudged = 0;

        foreach ($participants as $p) {
            $pid = (int) $p['id'];
            $bw = ($p['body_weight'] !== null && (float) $p['body_weight'] > 0)
                ? (float) $p['body_weight']
                : null;

            $cat = trim((string) ($p['weight_category'] ?? ''));
            if ($cat === '' && $bw !== null) {
                $cat = WeightCategory::forDiscipline(
                    $discipline,
                    (string) ($p['age_group'] ?? 'open'),
                    $bw
                );
            }
            if ($cat === '') {
                $cat = 'Без категории';
            }

            $catShort = self::formatCategoryShort($cat);
            $catKey = self::categorySortKey($cat);
            $qInfo = $queueMap[$pid] ?? null;

            // Process attempts 1..$maxAttempts (+ 4th record only for weight disciplines)
            $attemptsData = [];
            $bestVal = null;
            $hasAnyLift = false;

            $displayAttemptCount = $isReps ? $maxAttempts : max(3, $maxAttempts);
            if (!$isReps && isset($attemptsMap[$pid][4])) {
                $displayAttemptCount = max($displayAttemptCount, 4);
            }

            $fmtLift = static function (float $val) use ($isReps): string {
                if ($isReps) {
                    return (string) (int) round($val);
                }
                return str_replace('.', ',', (string) round($val, 2));
            };

            for ($i = 1; $i <= $displayAttemptCount; $i++) {
                $a = $attemptsMap[$pid][$i] ?? null;
                $val = null;
                $isValid = true;
                $status = 'empty';
                $text = '';

                if ($a) {
                    $isValid = (int) ($a['is_valid'] ?? 1) === 1;
                    if ($a['value'] !== null && $a['value'] !== '') {
                        $val = (float) $a['value'];
                        $totalJudged++;
                        if ($isValid) {
                            $status = 'valid';
                            $text = $fmtLift($val);
                            if ($bestVal === null || $val > $bestVal) {
                                $bestVal = $val;
                            }
                            $hasAnyLift = true;
                        } else {
                            $status = 'invalid';
                            $text = $fmtLift($val);
                        }
                    } elseif (!$isReps && $a['declared_weight'] !== null && $a['declared_weight'] !== '') {
                        // Для народного в ячейке — только повторы; вес штанги отдельной колонкой
                        $status = 'declared';
                        $text = str_replace('.', ',', (string) round((float) $a['declared_weight'], 2));
                    }
                }

                // Если попытка сейчас на помосте или следующая в очереди, и ещё не отсужена:
                if ($val === null && $qInfo && (int) $qInfo['attempt_no'] === $i) {
                    $dw = (float) $qInfo['declared_weight'];
                    $dwFormatted = $dw > 0 ? str_replace('.', ',', (string) round($dw, 2)) : '';
                    if ($qInfo['status'] === 'current') {
                        $status = 'stage_now';
                        // Народный: на помосте ждём число повторов, не вес штанги
                        $text = $isReps ? '…' : ($dwFormatted !== '' ? $dwFormatted : 'ПОМОСТ');
                    } elseif ($qInfo['status'] === 'next') {
                        $status = 'stage_next';
                        $text = $isReps ? '…' : ($dwFormatted !== '' ? $dwFormatted : 'ДАЛЕЕ');
                    } elseif (!$isReps && $status === 'empty' && $dw > 0) {
                        $status = 'declared';
                        $text = $dwFormatted;
                    }
                }

                $attemptsData[$i] = [
                    'no'       => $i,
                    'value'    => $val,
                    'is_valid' => $isValid,
                    'status'   => $status,
                    'text'     => $text,
                ];
            }

            // Sum / Wilks / разряды — только для силовых (кг), не для повторов народного
            $sum = $bestVal;
            $wilks = (!$isReps && $bw !== null && $bestVal !== null && $bestVal > 0)
                ? self::wilks($bw, $bestVal, $p['gender'] ?? 'M')
                : null;

            $numericCat = (float) str_replace(['+', ','], ['', '.'], $catShort);
            if ($numericCat <= 0 && $bw !== null) {
                $numericCat = $bw;
            }
            $rank = (!$isReps && $bestVal !== null && $bestVal > 0)
                ? self::determineRank($numericCat, $bestVal, $code)
                : '';

            $barWeight = null;
            if ($isFolk && $bw !== null) {
                $barWeight = FolkBench::barWeight($bw);
            } elseif ($isFolk && $qInfo && (float) ($qInfo['declared_weight'] ?? 0) > 0) {
                $barWeight = (float) $qInfo['declared_weight'];
            }

            $athleteRow = [
                'id'              => $pid,
                'lot_number'      => !empty($p['lot_number']) ? (int) $p['lot_number'] : $pid,
                'full_name'       => (string) ($p['full_name'] ?? '—'),
                'discipline_abbr' => !empty($discipline['name_ru']) ? (string) $discipline['name_ru'] : '—',
                'age_group'       => ($p['age_group'] === 'juniors' || str_contains(mb_strtolower((string)$p['age_group']), 'юниор'))
                    ? 'Юниоры'
                    : 'Открытая',
                'team'            => !empty($p['team']) ? (string) $p['team'] : (!empty($p['club']) ? (string) $p['club'] : 'Личник'),
                'birth_year'      => !empty($p['birth_year']) ? (int) $p['birth_year'] : '—',
                'sport_rank'      => $rank ?: (!empty($p['rank']) ? (string) $p['rank'] : '—'),
                'body_weight'     => $bw,
                'body_weight_fmt' => $bw !== null ? number_format($bw, 2, ',', '') : '—',
                'bar_weight'      => $barWeight,
                'bar_weight_fmt'  => $barWeight !== null ? number_format($barWeight, 1, ',', '') : '—',
                'category_short'  => $catShort,
                'category_full'   => $cat,
                'weighed'         => !empty($p['weighed_at']),
                'attempts'        => $attemptsData,
                'best'            => $bestVal,
                'best_fmt'        => $bestVal !== null
                    ? ($isReps ? (string) (int) round($bestVal) : number_format($bestVal, 1, ',', ''))
                    : '—',
                'sum'             => $sum,
                'sum_fmt'         => $sum !== null
                    ? ($isReps ? (string) (int) round($sum) : number_format($sum, 1, ',', ''))
                    : '—',
                'wilks'           => $wilks,
                'wilks_fmt'       => $wilks !== null ? number_format($wilks, 3, ',', '') : '—',
                'place'           => null,
                'has_lift'        => $hasAnyLift,
                // Позиция и статус в очереди помоста (как на live)
                'queue_status'    => $qInfo['status'] ?? null,
                'queue_pos'       => $qInfo !== null ? (int) $qInfo['pos'] : 9999,
                'queue_label'     => $qInfo['label'] ?? '—',
                'queue_att_no'    => $qInfo['attempt_no'] ?? null,
                'queue_weight'    => $qInfo['declared_weight'] ?? null,
                'is_on_stage'     => ($qInfo['status'] ?? '') === 'current',
                'is_next_up'      => ($qInfo['status'] ?? '') === 'next',
            ];

            $grouped[$catShort]['category'] = $cat;
            $grouped[$catShort]['category_short'] = $catShort;
            $grouped[$catShort]['sort_key'] = $catKey;
            $grouped[$catShort]['athletes'][] = $athleteRow;
        }

        // Calculate places within each category
        foreach ($grouped as &$group) {
            usort($group['athletes'], static function (array $a, array $b): int {
                // Athletes with valid lifts first
                if ($a['has_lift'] !== $b['has_lift']) {
                    return $a['has_lift'] ? -1 : 1;
                }

                // Higher best result is higher place
                $ra = (float) ($a['best'] ?? -1);
                $rb = (float) ($b['best'] ?? -1);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }

                // If result is equal, lighter athlete wins tie
                $bwa = $a['body_weight'] !== null ? (float) $a['body_weight'] : 9999.0;
                $bwb = $b['body_weight'] !== null ? (float) $b['body_weight'] : 9999.0;
                if ($bwa !== $bwb) {
                    return $bwa <=> $bwb;
                }

                return strcmp($a['full_name'], $b['full_name']);
            });

            // Assign places: 1, 2, 3...
            $currentPlace = 0;
            $currentRank = 0;
            $prevBest = null;
            $prevBw = null;

            foreach ($group['athletes'] as &$ath) {
                if (!$ath['has_lift'] || $ath['best'] === null || (float) $ath['best'] <= 0) {
                    $ath['place'] = '—';
                    continue;
                }

                $currentRank++;
                $sameAsPrev = ($prevBest !== null)
                    && (float) $ath['best'] === (float) $prevBest
                    && (float) $ath['body_weight'] === (float) $prevBw;

                if (!$sameAsPrev) {
                    $currentPlace = $currentRank;
                }

                $ath['place'] = $currentPlace;
                $prevBest = $ath['best'];
                $prevBw = $ath['body_weight'];
            }
            unset($ath);
        }
        unset($group);

        // Sort categories by athletic weight progression (60, 67.5, 75, 82.5, 90, 100, 110, 125, 140+)
        uasort($grouped, static fn (array $a, array $b): int => $a['sort_key'] <=> $b['sort_key']);

        return [
            'categories'      => array_values($grouped),
            'total_athletes'  => count($participants),
            'judged_attempts' => $totalJudged,
        ];
    }
}

