<?php

namespace App\Models;

use App\Core\Database;

class Attempt
{
    public static function upsert(
        int $participantId,
        int $disciplineId,
        int $attemptNo,
        ?float $value,
        bool $isValid,
        ?float $declaredWeight = null
    ): void {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO attempts
               (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               declared_weight = COALESCE(VALUES(declared_weight), attempts.declared_weight),
               value = VALUES(value),
               is_valid = VALUES(is_valid),
               judged_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            $participantId,
            $disciplineId,
            $attemptNo,
            $declaredWeight,
            $value,
            $isValid ? 1 : 0,
        ]);
    }

    public static function declareWeight(
        int $participantId,
        int $disciplineId,
        int $attemptNo,
        float $declaredWeight
    ): void {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO attempts
               (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
             VALUES (?, ?, ?, ?, NULL, 1)
             ON DUPLICATE KEY UPDATE
               declared_weight = VALUES(declared_weight),
               judged_at = CURRENT_TIMESTAMP'
        );
        $st->execute([$participantId, $disciplineId, $attemptNo, round($declaredWeight, 2)]);
    }

    public static function judge(
        int $participantId,
        int $disciplineId,
        int $attemptNo,
        float $weight,
        bool $isValid
    ): void {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO attempts
               (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               declared_weight = COALESCE(attempts.declared_weight, VALUES(declared_weight)),
               value = VALUES(value),
               is_valid = VALUES(is_valid),
               judged_at = CURRENT_TIMESTAMP'
        );
        $w = round($weight, 2);
        $st->execute([
            $participantId,
            $disciplineId,
            $attemptNo,
            $w,
            $w,
            $isValid ? 1 : 0,
        ]);
    }

    /** Результат в повторах; declared_weight — вес на штанге (для народного жима). */
    public static function judgeReps(
        int $participantId,
        int $disciplineId,
        int $attemptNo,
        float $reps,
        ?float $barWeight = null,
        bool $isValid = true
    ): void {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO attempts
               (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               declared_weight = COALESCE(VALUES(declared_weight), attempts.declared_weight),
               value = VALUES(value),
               is_valid = VALUES(is_valid),
               judged_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            $participantId,
            $disciplineId,
            $attemptNo,
            $barWeight !== null ? round($barWeight, 2) : null,
            round($reps, 0),
            $isValid ? 1 : 0,
        ]);
    }

    public static function find(int $participantId, int $disciplineId, int $attemptNo): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM attempts
             WHERE participant_id = ? AND discipline_id = ? AND attempt_no = ?'
        );
        $st->execute([$participantId, $disciplineId, $attemptNo]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function forParticipantDiscipline(int $participantId, int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM attempts
             WHERE participant_id = ? AND discipline_id = ?
             ORDER BY attempt_no'
        );
        $st->execute([$participantId, $disciplineId]);
        return $st->fetchAll();
    }

    /** Последняя зафиксированная попытка дисциплины (для live-табло). */
    public static function lastJudged(int $disciplineId): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT a.participant_id, a.attempt_no, a.declared_weight, a.value, a.is_valid,
                    a.judged_at, p.full_name, p.body_weight
             FROM attempts a
             JOIN participants p ON p.id = a.participant_id
             WHERE a.discipline_id = ?
               AND a.value IS NOT NULL
               AND a.judged_at IS NOT NULL
             ORDER BY a.judged_at DESC, a.id DESC
             LIMIT 1'
        );
        $st->execute([$disciplineId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Лучший результат участника в дисциплине.
     * weight/reps: MAX(value) среди зачётных
     * arm: MAX(value) = число побед (больше лучше) ИЛИ если храним место — тогда MIN
     * Мы храним для arm число побед (больше = лучше).
     */
    public static function bestResult(int $participantId, int $disciplineId, string $scoringType): ?float
    {
        if ($scoringType === 'arm') {
            // Для армрестлинга value = число побед
            $sql = 'SELECT MAX(value) FROM attempts
                    WHERE participant_id = ? AND discipline_id = ? AND is_valid = 1';
        } else {
            $sql = 'SELECT MAX(value) FROM attempts
                    WHERE participant_id = ? AND discipline_id = ? AND is_valid = 1';
        }
        $st = Database::pdo()->prepare($sql);
        $st->execute([$participantId, $disciplineId]);
        $v = $st->fetchColumn();
        return $v !== null && $v !== false ? (float) $v : null;
    }

    public static function allForDiscipline(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM attempts WHERE discipline_id = ? ORDER BY participant_id, attempt_no'
        );
        $st->execute([$disciplineId]);
        return $st->fetchAll();
    }
}
