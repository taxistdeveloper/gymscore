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
     * Normalize age group to protocol label: Юниоры | Открытая.
     */
    public static function ageGroupLabel(?string $ageGroup): string
    {
        $raw = mb_strtolower(trim((string) $ageGroup));
        if ($raw === 'juniors' || str_contains($raw, 'юниор') || str_contains($raw, 'юнош')) {
            return 'Юниоры';
        }

        return 'Открытая';
    }

    /**
     * Sort order for age groups (Juniors first, then Open).
     */
    public static function ageGroupSortKey(?string $ageGroup): int
    {
        return self::ageGroupLabel($ageGroup) === 'Юниоры' ? 0 : 1;
    }

    /**
     * Builds structured protocol data grouped by age group + weight category.
     * Juniors and Open are separate even when weight class labels match.
     *
     * @param array $discipline
     * @param list<array> $participants
     * @param array<int, array<int, array>> $attemptsMap [participant_id => [attempt_no => attempt]]
     * @param list<array> $queue Platform queue
     * @param array|null $current Current athlete on platform
     * @return array{
     *   categories: list<array{
     *     age_group: string,
     *     age_sort: int,
     *     category: string,
     *     category_short: string,
     *     group_key: string,
     *     group_label: string,
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
            if ($isFolk) {
                // Сначала заявленный/сохранённый вес штанги (админ / взвешивание), иначе ½ с/в
                $declaredBar = null;
                if ($qInfo && (float) ($qInfo['declared_weight'] ?? 0) > 0) {
                    $declaredBar = (float) $qInfo['declared_weight'];
                } elseif (!empty($attemptsMap[$pid][1]['declared_weight'])) {
                    $declaredBar = (float) $attemptsMap[$pid][1]['declared_weight'];
                }
                if ($declaredBar !== null && $declaredBar > 0) {
                    $barWeight = $declaredBar;
                } elseif ($bw !== null) {
                    $barWeight = FolkBench::barWeight($bw);
                }
            }

            $ageLabel = self::ageGroupLabel($p['age_group'] ?? null);
            $ageSort = self::ageGroupSortKey($p['age_group'] ?? null);
            $groupKey = $ageLabel . '|' . $catShort;
            $groupLabel = $ageLabel . ' · ' . $catShort;

            $athleteRow = [
                'id'              => $pid,
                'lot_number'      => !empty($p['lot_number']) ? (int) $p['lot_number'] : $pid,
                'full_name'       => (string) ($p['full_name'] ?? '—'),
                'discipline_abbr' => !empty($discipline['name_ru']) ? (string) $discipline['name_ru'] : '—',
                'age_group'       => $ageLabel,
                'team'            => !empty($p['team']) ? (string) $p['team'] : (!empty($p['club']) ? (string) $p['club'] : 'Личник'),
                'birth_year'      => !empty($p['birth_year']) ? (int) $p['birth_year'] : '—',
                'sport_rank'      => $rank ?: (!empty($p['rank']) ? (string) $p['rank'] : '—'),
                'body_weight'     => $bw,
                'body_weight_fmt' => $bw !== null ? number_format($bw, 2, ',', '') : '—',
                'bar_weight'      => $barWeight,
                'bar_weight_fmt'  => $barWeight !== null ? number_format($barWeight, 1, ',', '') : '—',
                'category_short'  => $catShort,
                'category_full'   => $cat,
                'group_key'       => $groupKey,
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

            $grouped[$groupKey]['age_group'] = $ageLabel;
            $grouped[$groupKey]['age_sort'] = $ageSort;
            $grouped[$groupKey]['category'] = $cat;
            $grouped[$groupKey]['category_short'] = $catShort;
            $grouped[$groupKey]['group_key'] = $groupKey;
            $grouped[$groupKey]['group_label'] = $groupLabel;
            $grouped[$groupKey]['sort_key'] = $catKey;
            $grouped[$groupKey]['athletes'][] = $athleteRow;
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

        // Juniors first, then Open; within age — by weight class
        uasort($grouped, static function (array $a, array $b): int {
            $age = ((int) ($a['age_sort'] ?? 1)) <=> ((int) ($b['age_sort'] ?? 1));
            if ($age !== 0) {
                return $age;
            }
            return $a['sort_key'] <=> $b['sort_key'];
        });

        $categories = array_values($grouped);
        $absolute = self::buildAbsoluteFromCategories($categories, !$isReps);

        // Проставить место в абсолютке в строки протокола
        $absPlaceById = [];
        foreach ($absolute as $block) {
            foreach ($block['rows'] as $row) {
                $absPlaceById[(int) $row['id']] = $row['abs_place'];
            }
        }
        foreach ($categories as &$catGroup) {
            foreach ($catGroup['athletes'] as &$ath) {
                $ath['abs_place'] = $absPlaceById[(int) $ath['id']] ?? '—';
            }
            unset($ath);
        }
        unset($catGroup);

        return [
            'categories'      => $categories,
            'absolute'        => $absolute,
            'total_athletes'  => count($participants),
            'judged_attempts' => $totalJudged,
        ];
    }

    /**
     * Абсолютка по возрастным группам.
     * Силовые (кг): по Wilks. Повторы/народный: по лучшему результату.
     * Тай-брейк: меньше собственный вес.
     *
     * @param list<array> $categories
     * @return list<array{age_group:string,age_sort:int,metric:string,rows:list<array>}>
     */
    public static function buildAbsoluteFromCategories(array $categories, bool $useWilks): array
    {
        $byAge = [];
        foreach ($categories as $catGroup) {
            $ageLabel = (string) ($catGroup['age_group'] ?? 'Открытая');
            $ageSort = (int) ($catGroup['age_sort'] ?? self::ageGroupSortKey($ageLabel));
            foreach ($catGroup['athletes'] as $ath) {
                if (empty($ath['has_lift']) || $ath['best'] === null || (float) $ath['best'] <= 0) {
                    continue;
                }
                $score = $useWilks
                    ? (float) ($ath['wilks'] ?? 0)
                    : (float) $ath['best'];
                if ($useWilks && $score <= 0) {
                    continue;
                }
                $byAge[$ageLabel]['age_group'] = $ageLabel;
                $byAge[$ageLabel]['age_sort'] = $ageSort;
                $byAge[$ageLabel]['rows'][] = [
                    'id'              => (int) $ath['id'],
                    'full_name'       => (string) $ath['full_name'],
                    'team'            => (string) ($ath['team'] ?? '—'),
                    'age_group'       => $ageLabel,
                    'category_short'  => (string) ($ath['category_short'] ?? '—'),
                    'category_full'   => (string) ($ath['category_full'] ?? ''),
                    'body_weight'     => $ath['body_weight'],
                    'body_weight_fmt' => (string) ($ath['body_weight_fmt'] ?? '—'),
                    'best'            => $ath['best'],
                    'best_fmt'        => (string) ($ath['best_fmt'] ?? '—'),
                    'wilks'           => $ath['wilks'] ?? null,
                    'wilks_fmt'       => (string) ($ath['wilks_fmt'] ?? '—'),
                    'score'           => $score,
                    'score_fmt'       => $useWilks
                        ? number_format($score, 3, ',', '')
                        : (string) ($ath['best_fmt'] ?? '—'),
                    'cat_place'       => $ath['place'] ?? '—',
                    'abs_place'       => null,
                ];
            }
        }

        $out = [];
        foreach ($byAge as $block) {
            usort($block['rows'], static function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                $bwa = $a['body_weight'] !== null ? (float) $a['body_weight'] : 9999.0;
                $bwb = $b['body_weight'] !== null ? (float) $b['body_weight'] : 9999.0;
                if ($bwa !== $bwb) {
                    return $bwa <=> $bwb;
                }
                return strcmp($a['full_name'], $b['full_name']);
            });

            $place = 0;
            $rank = 0;
            $prevScore = null;
            $prevBw = null;
            foreach ($block['rows'] as &$row) {
                $rank++;
                $same = $prevScore !== null
                    && (float) $row['score'] === (float) $prevScore
                    && (float) ($row['body_weight'] ?? -1) === (float) $prevBw;
                if (!$same) {
                    $place = $rank;
                }
                $row['abs_place'] = $place;
                $prevScore = $row['score'];
                $prevBw = $row['body_weight'];
            }
            unset($row);

            $block['metric'] = $useWilks ? 'wilks' : 'result';
            $out[] = $block;
        }

        usort($out, static fn(array $a, array $b): int =>
            ((int) ($a['age_sort'] ?? 1)) <=> ((int) ($b['age_sort'] ?? 1))
        );

        return $out;
    }
}

