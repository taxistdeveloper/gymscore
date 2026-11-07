<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ArmMatch
{
    public static function discipline(): ?array
    {
        $st = Database::pdo()->prepare(
            "SELECT * FROM disciplines WHERE scoring_type = 'arm' ORDER BY sort_order LIMIT 1"
        );
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Взвешенные участники армрестлинга, сгруппированные по весовой категории. */
    public static function eligibleByCategory(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT p.id, p.full_name, p.body_weight, p.gender, p.lot_number, p.age_group, p.birth_year,
                    COALESCE(r.weight_category, p.weight_category) AS weight_category, p.weighed_at
             FROM participants p
             JOIN registrations r ON r.participant_id = p.id AND r.discipline_id = ?
             WHERE p.status = ? AND p.weighed_at IS NOT NULL
             ORDER BY COALESCE(r.weight_category, p.weight_category), p.lot_number IS NULL, p.lot_number, p.full_name'
        );
        $st->execute([$disciplineId, Participant::STATUS_APPROVED]);

        $groups = [];
        foreach ($st->fetchAll() as $row) {
            $cat = trim((string) ($row['weight_category'] ?? ''));
            if ($cat === '') {
                $cat = WeightCategory::forDiscipline(
                    'armwrestling',
                    (string) ($row['age_group'] ?? 'open'),
                    (float) $row['body_weight']
                );
            }
            $row['weight_category'] = $cat;
            $groups[$cat][] = $row;
        }
        return $groups;
    }

    public static function allForDiscipline(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT m.*,
                    p1.full_name AS p1_name, p1.body_weight AS p1_bw, p1.lot_number AS p1_lot,
                    p2.full_name AS p2_name, p2.body_weight AS p2_bw, p2.lot_number AS p2_lot,
                    w.full_name AS winner_name
             FROM arm_matches m
             JOIN participants p1 ON p1.id = m.participant1_id
             LEFT JOIN participants p2 ON p2.id = m.participant2_id
             LEFT JOIN participants w ON w.id = m.winner_id
             WHERE m.discipline_id = ?
             ORDER BY m.weight_category, m.round_no, m.match_no, m.id'
        );
        $st->execute([$disciplineId]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT m.*,
                    p1.full_name AS p1_name, p1.body_weight AS p1_bw,
                    p2.full_name AS p2_name, p2.body_weight AS p2_bw
             FROM arm_matches m
             JOIN participants p1 ON p1.id = m.participant1_id
             LEFT JOIN participants p2 ON p2.id = m.participant2_id
             WHERE m.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function current(int $disciplineId): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT m.*,
                    p1.full_name AS p1_name, p1.body_weight AS p1_bw, p1.gender AS p1_gender,
                    p1.lot_number AS p1_lot,
                    p2.full_name AS p2_name, p2.body_weight AS p2_bw, p2.gender AS p2_gender,
                    p2.lot_number AS p2_lot
             FROM arm_matches m
             JOIN participants p1 ON p1.id = m.participant1_id
             LEFT JOIN participants p2 ON p2.id = m.participant2_id
             WHERE m.discipline_id = ? AND m.is_current = 1
             LIMIT 1'
        );
        $st->execute([$disciplineId]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }

        // Фоллбек: первый незавершённый бой
        $st = Database::pdo()->prepare(
            'SELECT m.*,
                    p1.full_name AS p1_name, p1.body_weight AS p1_bw, p1.gender AS p1_gender,
                    p1.lot_number AS p1_lot,
                    p2.full_name AS p2_name, p2.body_weight AS p2_bw, p2.gender AS p2_gender,
                    p2.lot_number AS p2_lot
             FROM arm_matches m
             JOIN participants p1 ON p1.id = m.participant1_id
             LEFT JOIN participants p2 ON p2.id = m.participant2_id
             WHERE m.discipline_id = ? AND m.status = \'pending\' AND m.participant2_id IS NOT NULL
             ORDER BY m.round_no, m.match_no, m.id
             LIMIT 1'
        );
        $st->execute([$disciplineId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function upcoming(int $disciplineId, ?int $excludeId = null, int $limit = 12): array
    {
        $sql = 'SELECT m.*,
                       p1.full_name AS p1_name,
                       p2.full_name AS p2_name
                FROM arm_matches m
                JOIN participants p1 ON p1.id = m.participant1_id
                LEFT JOIN participants p2 ON p2.id = m.participant2_id
                WHERE m.discipline_id = ? AND m.status = \'pending\' AND m.participant2_id IS NOT NULL';
        $params = [$disciplineId];
        if ($excludeId) {
            $sql .= ' AND m.id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY m.is_current DESC, m.round_no, m.match_no, m.id LIMIT ' . (int) $limit;
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /**
     * Доска судейства: участники и раунды по категориям + счёт побед.
     *
     * @return array{byCategory: array<string, array>, wins: array<int, int>}
     */
    public static function boardData(int $disciplineId): array
    {
        $eligible = self::eligibleByCategory($disciplineId);
        $matches = self::allForDiscipline($disciplineId);
        $wins = self::winCounts($disciplineId);

        $byCategory = [];
        foreach ($matches as $m) {
            $cat = $m['weight_category'];
            $round = (int) $m['round_no'];
            $byCategory[$cat]['rounds'][$round][] = $m;
            $byCategory[$cat]['max_round'] = max(
                (int) ($byCategory[$cat]['max_round'] ?? 0),
                $round
            );
        }

        foreach ($eligible as $cat => $people) {
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = ['rounds' => [], 'max_round' => 0];
            }
            $byCategory[$cat]['people'] = $people;
        }

        ksort($byCategory);

        return [
            'byCategory' => $byCategory,
            'wins'       => $wins,
        ];
    }

    /** Число побед по дисциплине (из завершённых матчей). */
    public static function winCounts(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT winner_id, COUNT(*) AS wins
             FROM arm_matches
             WHERE discipline_id = ? AND status = \'done\' AND winner_id IS NOT NULL
             GROUP BY winner_id'
        );
        $st->execute([$disciplineId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int) $row['winner_id']] = (int) $row['wins'];
        }
        return $out;
    }

    /**
     * Протокол армрестлинга: таблица мест по ВК + список боёв.
     *
     * @return array{
     *   categories: list<array{category:string,category_short:string,sort_key:float,athletes:list<array>}>,
     *   matches: list<array>,
     *   total_athletes: int,
     *   total_matches: int,
     *   done_matches: int
     * }
     */
    public static function buildProtocol(int $disciplineId): array
    {
        $eligible = self::eligibleByCategory($disciplineId);
        $matches = self::allForDiscipline($disciplineId);
        $wins = self::winCounts($disciplineId);

        $losses = [];
        $played = [];
        foreach ($matches as $m) {
            if (($m['status'] ?? '') !== 'done') {
                continue;
            }
            $p1 = (int) $m['participant1_id'];
            $p2 = $m['participant2_id'] !== null ? (int) $m['participant2_id'] : null;
            $winner = $m['winner_id'] !== null ? (int) $m['winner_id'] : null;
            if ($p2 === null) {
                // bye — не считаем поражением
                $played[$p1] = ($played[$p1] ?? 0) + 1;
                continue;
            }
            $played[$p1] = ($played[$p1] ?? 0) + 1;
            $played[$p2] = ($played[$p2] ?? 0) + 1;
            if ($winner === $p1) {
                $losses[$p2] = ($losses[$p2] ?? 0) + 1;
            } elseif ($winner === $p2) {
                $losses[$p1] = ($losses[$p1] ?? 0) + 1;
            }
        }

        $grouped = [];
        foreach ($eligible as $cat => $people) {
            $catShort = PowerProtocol::formatCategoryShort($cat);
            $athletes = [];
            foreach ($people as $p) {
                $pid = (int) $p['id'];
                $bw = isset($p['body_weight']) ? (float) $p['body_weight'] : null;
                $w = (int) ($wins[$pid] ?? 0);
                $l = (int) ($losses[$pid] ?? 0);
                $athletes[] = [
                    'id'              => $pid,
                    'lot_number'      => !empty($p['lot_number']) ? (int) $p['lot_number'] : $pid,
                    'full_name'       => (string) ($p['full_name'] ?? '—'),
                    'team'            => !empty($p['team']) ? (string) $p['team'] : (!empty($p['club']) ? (string) $p['club'] : '—'),
                    'birth_year'      => !empty($p['birth_year']) ? (int) $p['birth_year'] : '—',
                    'body_weight'     => $bw,
                    'body_weight_fmt' => $bw !== null ? number_format($bw, 2, ',', '') : '—',
                    'category_short'  => $catShort,
                    'category_full'   => $cat,
                    'wins'            => $w,
                    'losses'          => $l,
                    'played'          => (int) ($played[$pid] ?? 0),
                    'has_result'      => $w > 0 || $l > 0 || (($played[$pid] ?? 0) > 0),
                    'place'           => null,
                ];
            }

            usort($athletes, static function (array $a, array $b): int {
                if ($a['has_result'] !== $b['has_result']) {
                    return $a['has_result'] ? -1 : 1;
                }
                if ($a['wins'] !== $b['wins']) {
                    return $b['wins'] <=> $a['wins'];
                }
                if ($a['losses'] !== $b['losses']) {
                    return $a['losses'] <=> $b['losses'];
                }
                $bwa = $a['body_weight'] !== null ? (float) $a['body_weight'] : 9999.0;
                $bwb = $b['body_weight'] !== null ? (float) $b['body_weight'] : 9999.0;
                if ($bwa !== $bwb) {
                    return $bwa <=> $bwb;
                }
                return strcmp($a['full_name'], $b['full_name']);
            });

            $currentPlace = 0;
            $currentRank = 0;
            $prevW = null;
            $prevL = null;
            $prevBw = null;
            foreach ($athletes as &$ath) {
                if (!$ath['has_result']) {
                    $ath['place'] = '—';
                    continue;
                }
                $currentRank++;
                $same = ($prevW !== null)
                    && (int) $ath['wins'] === (int) $prevW
                    && (int) $ath['losses'] === (int) $prevL
                    && (float) $ath['body_weight'] === (float) $prevBw;
                if (!$same) {
                    $currentPlace = $currentRank;
                }
                $ath['place'] = $currentPlace;
                $prevW = $ath['wins'];
                $prevL = $ath['losses'];
                $prevBw = $ath['body_weight'];
            }
            unset($ath);

            $grouped[] = [
                'category'       => $cat,
                'category_short' => $catShort,
                'sort_key'       => PowerProtocol::categorySortKey($cat),
                'athletes'       => $athletes,
            ];
        }

        usort($grouped, static fn(array $a, array $b): int => $a['sort_key'] <=> $b['sort_key']);

        $done = 0;
        foreach ($matches as $m) {
            if (($m['status'] ?? '') === 'done') {
                $done++;
            }
        }

        return [
            'categories'      => $grouped,
            'matches'         => $matches,
            'total_athletes'  => array_sum(array_map(static fn(array $g): int => count($g['athletes']), $grouped)),
            'total_matches'   => count($matches),
            'done_matches'    => $done,
        ];
    }

    public static function syncWins(int $disciplineId): void
    {
        $counts = self::winCounts($disciplineId);

        // Все, кто уже судился в матчах (победа или поражение)
        $st = Database::pdo()->prepare(
            'SELECT DISTINCT pid FROM (
               SELECT participant1_id AS pid FROM arm_matches WHERE discipline_id = ? AND status = \'done\'
               UNION
               SELECT participant2_id AS pid FROM arm_matches
               WHERE discipline_id = ? AND status = \'done\' AND participant2_id IS NOT NULL
               UNION
               SELECT winner_id AS pid FROM arm_matches
               WHERE discipline_id = ? AND winner_id IS NOT NULL
             ) t'
        );
        $st->execute([$disciplineId, $disciplineId, $disciplineId]);
        foreach ($st->fetchAll() as $row) {
            $pid = (int) $row['pid'];
            Attempt::upsert($pid, $disciplineId, 1, (float) ($counts[$pid] ?? 0), true, null);
        }
    }

    /**
     * Составить пары в категории.
     * Если последний раунд завершён — следующий из победителей.
     * Если нет матчей — 1-й раунд из взвешенных.
     * Если есть pending — пересобрать только pending-раунд из того же пула.
     *
     * @return array{round:int, created:int, byes:int}
     */
    public static function generatePairs(int $disciplineId, string $category): array
    {
        $category = trim($category);
        if ($category === '') {
            throw new \InvalidArgumentException('Не указана категория');
        }

        $pdo = Database::pdo();

        $stMax = $pdo->prepare(
            'SELECT COALESCE(MAX(round_no), 0) FROM arm_matches
             WHERE discipline_id = ? AND weight_category = ?'
        );
        $stMax->execute([$disciplineId, $category]);
        $maxRound = (int) $stMax->fetchColumn();

        $pool = [];
        $roundNo = 1;

        if ($maxRound === 0) {
            $groups = self::eligibleByCategory($disciplineId);
            $pool = $groups[$category] ?? [];
            $roundNo = 1;
        } else {
            $stPend = $pdo->prepare(
                'SELECT COUNT(*) FROM arm_matches
                 WHERE discipline_id = ? AND weight_category = ? AND round_no = ? AND status = \'pending\''
            );
            $stPend->execute([$disciplineId, $category, $maxRound]);
            $pending = (int) $stPend->fetchColumn();

            if ($pending > 0) {
                // Пересобрать незавершённый раунд
                $stPool = $pdo->prepare(
                    'SELECT DISTINCT participant_id AS id FROM (
                       SELECT participant1_id AS participant_id FROM arm_matches
                       WHERE discipline_id = ? AND weight_category = ? AND round_no = ? AND status = \'pending\'
                       UNION ALL
                       SELECT participant2_id FROM arm_matches
                       WHERE discipline_id = ? AND weight_category = ? AND round_no = ?
                         AND status = \'pending\' AND participant2_id IS NOT NULL
                     ) t'
                );
                $stPool->execute([
                    $disciplineId, $category, $maxRound,
                    $disciplineId, $category, $maxRound,
                ]);
                $ids = array_map('intval', array_column($stPool->fetchAll(), 'id'));

                $pdo->prepare(
                    'DELETE FROM arm_matches
                     WHERE discipline_id = ? AND weight_category = ? AND round_no = ? AND status = \'pending\''
                )->execute([$disciplineId, $category, $maxRound]);

                $pool = self::participantsByIds($ids);
                $roundNo = $maxRound;
            } else {
                // Следующий раунд из победителей
                $stW = $pdo->prepare(
                    'SELECT winner_id AS id FROM arm_matches
                     WHERE discipline_id = ? AND weight_category = ? AND round_no = ?
                       AND status = \'done\' AND winner_id IS NOT NULL
                     ORDER BY match_no, id'
                );
                $stW->execute([$disciplineId, $category, $maxRound]);
                $ids = array_map('intval', array_column($stW->fetchAll(), 'id'));
                if (count($ids) < 2) {
                    throw new \RuntimeException('Недостаточно победителей для следующего раунда');
                }
                $pool = self::participantsByIds($ids);
                $order = array_flip($ids);
                usort($pool, static function (array $a, array $b) use ($order): int {
                    return ($order[(int) $a['id']] ?? 0) <=> ($order[(int) $b['id']] ?? 0);
                });
                $roundNo = $maxRound + 1;
            }
        }

        if (count($pool) < 2) {
            throw new \RuntimeException('В категории меньше двух участников для пар');
        }

        $created = 0;
        $byes = 0;
        $matchNo = 1;
        $n = count($pool);

        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < $n; $i += 2) {
                $p1 = (int) $pool[$i]['id'];
                if ($i + 1 >= $n) {
                    // bye
                    self::insertMatch($pdo, $disciplineId, $category, $roundNo, $matchNo, $p1, null, $p1, 'done');
                    $byes++;
                } else {
                    $p2 = (int) $pool[$i + 1]['id'];
                    self::insertMatch($pdo, $disciplineId, $category, $roundNo, $matchNo, $p1, $p2, null, 'pending');
                }
                $created++;
                $matchNo++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::syncWins($disciplineId);

        return ['round' => $roundNo, 'created' => $created, 'byes' => $byes];
    }

    public static function setCurrent(int $matchId): void
    {
        $match = self::find($matchId);
        if (!$match) {
            throw new \InvalidArgumentException('Матч не найден');
        }
        $did = (int) $match['discipline_id'];
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE arm_matches SET is_current = 0 WHERE discipline_id = ?')
            ->execute([$did]);
        $pdo->prepare('UPDATE arm_matches SET is_current = 1 WHERE id = ?')
            ->execute([$matchId]);
    }

    public static function setWinner(int $matchId, int $winnerId): void
    {
        $match = self::find($matchId);
        if (!$match) {
            throw new \InvalidArgumentException('Матч не найден');
        }
        $p1 = (int) $match['participant1_id'];
        $p2 = $match['participant2_id'] !== null ? (int) $match['participant2_id'] : null;
        if ($winnerId !== $p1 && $winnerId !== $p2) {
            throw new \InvalidArgumentException('Победитель не из этой пары');
        }
        if ($p2 === null) {
            throw new \InvalidArgumentException('Bye уже засчитан');
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE arm_matches
             SET winner_id = ?, status = \'done\', judged_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        )->execute([$winnerId, $matchId]);

        self::syncWins((int) $match['discipline_id']);
    }

    public static function clearResult(int $matchId): void
    {
        $match = self::find($matchId);
        if (!$match) {
            throw new \InvalidArgumentException('Матч не найден');
        }
        if ($match['participant2_id'] === null) {
            throw new \InvalidArgumentException('Bye нельзя сбросить');
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE arm_matches
             SET winner_id = NULL, status = \'pending\', judged_at = NULL
             WHERE id = ?'
        )->execute([$matchId]);

        self::syncWins((int) $match['discipline_id']);
    }

    /** @param list<int> $ids */
    private static function participantsByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = Database::pdo()->prepare(
            "SELECT id, full_name, body_weight, gender, lot_number, weight_category
             FROM participants WHERE id IN ($placeholders)"
        );
        $st->execute($ids);
        $map = [];
        foreach ($st->fetchAll() as $row) {
            $map[(int) $row['id']] = $row;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $out[] = $map[$id];
            }
        }
        return $out;
    }

    private static function insertMatch(
        PDO $pdo,
        int $disciplineId,
        string $category,
        int $roundNo,
        int $matchNo,
        int $p1,
        ?int $p2,
        ?int $winnerId,
        string $status
    ): void {
        $st = $pdo->prepare(
            'INSERT INTO arm_matches
               (discipline_id, weight_category, round_no, match_no,
                participant1_id, participant2_id, winner_id, status, judged_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $disciplineId,
            $category,
            $roundNo,
            $matchNo,
            $p1,
            $p2,
            $winnerId,
            $status,
            $status === 'done' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
