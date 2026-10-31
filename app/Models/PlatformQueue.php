<?php

namespace App\Models;

use App\Core\Database;

/**
 * Очередь на помост: текущий раунд попыток, сортировка вес → жребий.
 * Отложенные («ещё не пришёл») уходят в конец очереди.
 */
class PlatformQueue
{
    /**
     * @return list<array{
     *   participant_id: int,
     *   full_name: string,
     *   body_weight: float,
     *   gender: string,
     *   lot_number: ?int,
     *   weight_category: ?string,
     *   attempt_no: int,
     *   declared_weight: float,
     *   judged: bool,
     *   is_valid: ?int,
     *   deferred: bool,
     *   queue_key: string
     * }>
     */
    public static function forDiscipline(int $disciplineId): array
    {
        $discipline = Discipline::find($disciplineId);
        if (!$discipline) {
            return [];
        }

        $attemptsAllowed = (int) ($discipline['attempts_allowed'] ?? 3);
        $round = self::currentRound($disciplineId, $attemptsAllowed);

        $st = Database::pdo()->prepare(
            'SELECT p.id AS participant_id, p.full_name, p.body_weight, p.gender,
                    p.lot_number, COALESCE(r.weight_category, p.weight_category) AS weight_category,
                    ? AS attempt_no,
                    COALESCE(a.declared_weight, r.opening_weight) AS declared_weight,
                    CASE WHEN a.value IS NOT NULL THEN 1 ELSE 0 END AS judged,
                    a.is_valid
             FROM registrations r
             JOIN participants p ON p.id = r.participant_id
             LEFT JOIN attempts a
               ON a.participant_id = p.id
              AND a.discipline_id = r.discipline_id
              AND a.attempt_no = ?
             WHERE r.discipline_id = ?
               AND p.status = ?
               AND COALESCE(a.declared_weight, r.opening_weight) IS NOT NULL
               AND a.value IS NULL
             ORDER BY COALESCE(a.declared_weight, r.opening_weight) ASC,
                      COALESCE(p.lot_number, 999999) ASC,
                      p.full_name ASC'
        );
        $st->execute([
            $round,
            $round,
            $disciplineId,
            Participant::STATUS_APPROVED,
        ]);

        $deferred = PlatformState::deferredKeys($disciplineId);
        $deferredIndex = array_flip($deferred);

        $rows = $st->fetchAll();
        foreach ($rows as &$row) {
            $row['participant_id'] = (int) $row['participant_id'];
            $row['attempt_no'] = (int) $row['attempt_no'];
            $row['declared_weight'] = (float) $row['declared_weight'];
            $row['body_weight'] = (float) $row['body_weight'];
            $row['lot_number'] = $row['lot_number'] !== null ? (int) $row['lot_number'] : null;
            $row['judged'] = false;
            $row['is_valid'] = $row['is_valid'] !== null ? (int) $row['is_valid'] : null;
            $row['queue_key'] = $row['participant_id'] . '-' . $row['attempt_no'];
            $row['deferred'] = isset($deferredIndex[$row['queue_key']]);
        }
        unset($row);

        // Активные сначала, отложенные — в конец (в порядке откладывания)
        usort($rows, static function (array $a, array $b) use ($deferredIndex): int {
            $da = $a['deferred'] ? 1 : 0;
            $db = $b['deferred'] ? 1 : 0;
            if ($da !== $db) {
                return $da <=> $db;
            }
            if ($a['deferred'] && $b['deferred']) {
                return ($deferredIndex[$a['queue_key']] ?? 0) <=> ($deferredIndex[$b['queue_key']] ?? 0);
            }
            if ($a['declared_weight'] !== $b['declared_weight']) {
                return $a['declared_weight'] <=> $b['declared_weight'];
            }
            $la = $a['lot_number'] ?? 999999;
            $lb = $b['lot_number'] ?? 999999;
            if ($la !== $lb) {
                return $la <=> $lb;
            }
            return strcmp((string) $a['full_name'], (string) $b['full_name']);
        });

        return $rows;
    }

    public static function defer(int $disciplineId, string $queueKey): void
    {
        PlatformState::defer($disciplineId, $queueKey);
    }

    public static function undefer(int $disciplineId, string $queueKey): void
    {
        PlatformState::undefer($disciplineId, $queueKey);
    }

    public static function clearDeferForJudged(int $disciplineId, int $participantId, int $attemptNo): void
    {
        PlatformState::undefer($disciplineId, $participantId . '-' . $attemptNo);
    }

    /** Текущий раунд: первый, где ещё есть незакрытые заявленные попытки. */
    public static function currentRound(int $disciplineId, int $attemptsAllowed): int
    {
        for ($round = 1; $round <= $attemptsAllowed; $round++) {
            $st = Database::pdo()->prepare(
                'SELECT COUNT(*) FROM registrations r
                 JOIN participants p ON p.id = r.participant_id AND p.status = ?
                 LEFT JOIN attempts a
                   ON a.participant_id = p.id
                  AND a.discipline_id = r.discipline_id
                  AND a.attempt_no = ?
                 WHERE r.discipline_id = ?
                   AND COALESCE(a.declared_weight, IF(? = 1, r.opening_weight, NULL)) IS NOT NULL
                   AND a.value IS NULL'
            );
            $st->execute([
                Participant::STATUS_APPROVED,
                $round,
                $disciplineId,
                $round,
            ]);
            if ((int) $st->fetchColumn() > 0) {
                return $round;
            }
        }
        return min($attemptsAllowed, 1);
    }
}
