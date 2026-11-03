<?php

namespace App\Models;

use App\Core\Database;

class Participant
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING  => 'Новая',
            self::STATUS_APPROVED => 'Принята',
            self::STATUS_REJECTED => 'Отклонена',
        ];
    }

    public static function create(array $data, array $disciplineIds, array $disciplineCategories = []): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $primaryCategory = !empty($disciplineCategories) ? reset($disciplineCategories) : ($data['weight_category'] ?? null);
            $st = $pdo->prepare(
                'INSERT INTO participants
                   (full_name, phone, body_weight, gender, birth_year, age_group, status, weight_category)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $data['full_name'],
                $data['phone'] ?: null,
                $data['body_weight'],
                $data['gender'],
                $data['birth_year'] ?: null,
                $data['age_group'] ?? null,
                $data['status'] ?? self::STATUS_PENDING,
                $data['weight_category'] ?? $primaryCategory,
            ]);
            $id = (int) $pdo->lastInsertId();

            $reg = $pdo->prepare(
                'INSERT INTO registrations (participant_id, discipline_id, weight_category) VALUES (?, ?, ?)'
            );
            foreach ($disciplineIds as $did) {
                $didInt = (int) $did;
                $cat = $disciplineCategories[$didInt] ?? null;
                $reg->execute([$id, $didInt, $cat]);
            }

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM participants ORDER BY full_name')
            ->fetchAll();
    }

    public static function normalizeFullName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim(str_replace('ё', 'е', $name))) ?? '';
        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Дисциплины, на которые у ФИО уже есть заявка (pending/approved).
     *
     * @return list<int>
     */
    public static function takenDisciplineIdsByFullName(string $fullName): array
    {
        $key = self::normalizeFullName($fullName);
        if ($key === '') {
            return [];
        }

        $rows = Database::pdo()->prepare(
            'SELECT p.full_name, r.discipline_id
             FROM participants p
             JOIN registrations r ON r.participant_id = p.id
             WHERE p.status IN (?, ?)'
        );
        $rows->execute([self::STATUS_PENDING, self::STATUS_APPROVED]);
        $ids = [];
        foreach ($rows->fetchAll() as $row) {
            if (self::normalizeFullName((string) $row['full_name']) === $key) {
                $ids[(int) $row['discipline_id']] = true;
            }
        }
        return array_map('intval', array_keys($ids));
    }

    /**
     * Подсказки для формы регистрации (по фамилии / ФИО).
     *
     * @return list<array{id:int,full_name:string,phone:?string,body_weight:string,gender:string,birth_year:?int,discipline_ids:list<int>}>
     */
    public static function forRegisterSuggest(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo
            ->query(
                'SELECT id, full_name, phone, body_weight, gender, birth_year, age_group, status
                 FROM participants
                 ORDER BY id DESC'
            )
            ->fetchAll();

        $regs = $pdo
            ->query(
                'SELECT p.id AS participant_id, p.full_name, p.status, r.discipline_id
                 FROM participants p
                 JOIN registrations r ON r.participant_id = p.id
                 WHERE p.status IN (\'pending\', \'approved\')'
            )
            ->fetchAll();

        $takenByName = [];
        foreach ($regs as $reg) {
            $key = self::normalizeFullName((string) $reg['full_name']);
            if ($key === '') {
                continue;
            }
            $takenByName[$key][(int) $reg['discipline_id']] = true;
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = self::normalizeFullName((string) $row['full_name']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'id'             => (int) $row['id'],
                'full_name'      => (string) $row['full_name'],
                'phone'          => $row['phone'] !== null ? (string) $row['phone'] : null,
                'body_weight'    => number_format((float) $row['body_weight'], 2, '.', ''),
                'gender'         => (string) $row['gender'],
                'birth_year'     => $row['birth_year'] !== null ? (int) $row['birth_year'] : null,
                'age_group'      => $row['age_group'] !== null ? (string) $row['age_group'] : null,
                'discipline_ids' => array_map('intval', array_keys($takenByName[$key] ?? [])),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['full_name'], $b['full_name']));
        return $out;
    }

    public static function allApproved(): array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM participants WHERE status = ? ORDER BY full_name'
        );
        $st->execute([self::STATUS_APPROVED]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM participants WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function registeredFor(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT p.*, COALESCE(r.weight_category, p.weight_category) AS weight_category
             FROM participants p
             JOIN registrations r ON r.participant_id = p.id
             WHERE r.discipline_id = ? AND p.status = ?
             ORDER BY p.full_name'
        );
        $st->execute([$disciplineId, self::STATUS_APPROVED]);
        return $st->fetchAll();
    }

    public static function disciplineIds(int $participantId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT discipline_id FROM registrations WHERE participant_id = ?'
        );
        $st->execute([$participantId]);
        return array_column($st->fetchAll(), 'discipline_id');
    }

    public static function allWithDisciplines(?string $status = null, string $q = '', ?int $disciplineId = null): array
    {
        $sql = 'SELECT p.*,
                       GROUP_CONCAT(CONCAT(d.name_ru, IF(r.weight_category IS NOT NULL AND r.weight_category != "", CONCAT(" (", r.weight_category, ")"), "")) ORDER BY d.sort_order SEPARATOR ", ") AS disciplines
                FROM participants p
                LEFT JOIN registrations r ON r.participant_id = p.id
                LEFT JOIN disciplines d ON d.id = r.discipline_id';
        $where = [];
        $params = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(p.full_name LIKE ? OR IFNULL(p.phone, "") LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($disciplineId !== null && $disciplineId > 0) {
            $where[] = 'EXISTS (
                SELECT 1 FROM registrations rx
                WHERE rx.participant_id = p.id AND rx.discipline_id = ?
            )';
            $params[] = $disciplineId;
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY p.id
                  ORDER BY
                    FIELD(p.status, "pending", "approved", "rejected"),
                    p.created_at DESC,
                    p.full_name';

        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Счётчики новых заявок по дисциплинам. */
    public static function pendingDisciplineCounts(): array
    {
        $st = Database::pdo()->prepare(
            'SELECT d.id, d.name_ru, d.code,
                    COUNT(DISTINCT p.id) AS total
             FROM disciplines d
             LEFT JOIN registrations r ON r.discipline_id = d.id
             LEFT JOIN participants p ON p.id = r.participant_id AND p.status = ?
             GROUP BY d.id, d.name_ru, d.code, d.sort_order
             ORDER BY d.sort_order'
        );
        $st->execute([self::STATUS_PENDING]);

        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id'      => (int) $row['id'],
                'name_ru' => (string) $row['name_ru'],
                'code'    => (string) $row['code'],
                'count'   => (int) $row['total'],
            ];
        }
        return $out;
    }

    /**
     * Заявки, сгруппированные по дисциплинам (порядок sort_order).
     * Участник с несколькими дисциплинами попадает в каждую группу.
     *
     * @return list<array{id:int,name_ru:string,code:string,participants:list<array>,by_age:array}>
     */
    public static function groupedByDiscipline(?string $status = null, string $q = '', ?int $disciplineId = null): array
    {
        $sql = 'SELECT d.id AS discipline_id, d.name_ru AS discipline_name, d.code AS discipline_code,
                       p.*,
                       r.weight_category AS reg_weight_category
                FROM disciplines d
                INNER JOIN registrations r ON r.discipline_id = d.id
                INNER JOIN participants p ON p.id = r.participant_id
                WHERE 1 = 1';
        $params = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= ' AND (p.full_name LIKE ? OR IFNULL(p.phone, "") LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($disciplineId !== null && $disciplineId > 0) {
            $sql .= ' AND d.id = ?';
            $params[] = $disciplineId;
        }

        $sql .= ' ORDER BY d.sort_order,
                         FIELD(IFNULL(p.age_group, ""), "juniors", "open"),
                         FIELD(p.status, "pending", "approved", "rejected"),
                         p.created_at DESC,
                         p.full_name';

        $st = Database::pdo()->prepare($sql);
        $st->execute($params);

        $groups = [];
        foreach ($st->fetchAll() as $row) {
            $did = (int) $row['discipline_id'];
            if (!isset($groups[$did])) {
                $groups[$did] = [
                    'id'           => $did,
                    'name_ru'      => (string) $row['discipline_name'],
                    'code'         => (string) $row['discipline_code'],
                    'participants' => [],
                    'by_age'       => [
                        'juniors' => [],
                        'open'    => [],
                        'other'   => [],
                    ],
                ];
            }
            $cat = trim((string) ($row['reg_weight_category'] ?? ''));
            $row['disciplines'] = $cat !== '' ? $cat : '—';
            $groups[$did]['participants'][] = $row;

            $age = (string) ($row['age_group'] ?? '');
            if ($age === 'juniors' || $age === 'open') {
                $groups[$did]['by_age'][$age][] = $row;
            } else {
                $groups[$did]['by_age']['other'][] = $row;
            }
        }

        return array_values($groups);
    }

    /** Новые заявки, сгруппированные по дисциплинам. */
    public static function pendingGroupedByDiscipline(?int $disciplineId = null): array
    {
        return self::groupedByDiscipline(self::STATUS_PENDING, '', $disciplineId);
    }

    public static function update(int $id, array $data, array $disciplineIds, array $disciplineCategories = []): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $primaryCat = !empty($disciplineCategories) ? reset($disciplineCategories) : ($data['weight_category'] ?? null);
            $st = $pdo->prepare(
                'UPDATE participants
                 SET full_name = ?, phone = ?, body_weight = ?, gender = ?, birth_year = ?,
                     age_group = COALESCE(?, age_group),
                     weight_category = COALESCE(?, weight_category)
                 WHERE id = ?'
            );
            $st->execute([
                $data['full_name'],
                $data['phone'] ?: null,
                $data['body_weight'],
                $data['gender'],
                $data['birth_year'] ?: null,
                $data['age_group'] ?? null,
                $primaryCat,
                $id,
            ]);

            $existing = [];
            $stOld = $pdo->prepare('SELECT discipline_id, opening_weight, weight_category FROM registrations WHERE participant_id = ?');
            $stOld->execute([$id]);
            foreach ($stOld->fetchAll() as $row) {
                $existing[(int) $row['discipline_id']] = $row;
            }

            $pdo->prepare('DELETE FROM registrations WHERE participant_id = ?')->execute([$id]);
            $reg = $pdo->prepare(
                'INSERT INTO registrations (participant_id, discipline_id, opening_weight, weight_category) VALUES (?, ?, ?, ?)'
            );
            foreach ($disciplineIds as $did) {
                $didInt = (int) $did;
                $opening = $existing[$didInt]['opening_weight'] ?? null;
                $cat = $disciplineCategories[$didInt] ?? ($existing[$didInt]['weight_category'] ?? null);
                $reg->execute([$id, $didInt, $opening, $cat]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setStatus(int $id, string $status, ?string $note = null): void
    {
        if (!isset(self::statuses()[$status])) {
            throw new \InvalidArgumentException('Некорректный статус заявки');
        }

        $st = Database::pdo()->prepare(
            'UPDATE participants
             SET status = ?, status_note = ?, reviewed_at = NOW()
             WHERE id = ?'
        );
        $st->execute([$status, $note !== null && $note !== '' ? $note : null, $id]);
    }

    public static function delete(int $id): void
    {
        $st = Database::pdo()->prepare('DELETE FROM participants WHERE id = ?');
        $st->execute([$id]);
    }

    public static function count(?string $status = null): int
    {
        if ($status === null || $status === '' || $status === 'all') {
            return (int) Database::pdo()
                ->query('SELECT COUNT(*) FROM participants')
                ->fetchColumn();
        }
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM participants WHERE status = ?');
        $st->execute([$status]);
        return (int) $st->fetchColumn();
    }

    public static function countsByStatus(): array
    {
        $rows = Database::pdo()
            ->query(
                'SELECT status, COUNT(*) AS cnt
                 FROM participants
                 GROUP BY status'
            )
            ->fetchAll();
        $out = [
            'all'      => 0,
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
        ];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['cnt'];
            $out['all'] += (int) $row['cnt'];
        }
        return $out;
    }

    /** Категория участника: выбранная при регистрации (по дисциплине или общая). */
    public static function weighCategoryLabel(array $participant): string
    {
        foreach (['discipline_weight_category', 'weight_category'] as $key) {
            $stored = trim((string) ($participant[$key] ?? ''));
            if ($stored !== '') {
                return $stored;
            }
        }

        if ((float) ($participant['body_weight'] ?? 0) > 0) {
            return WeightCategory::labelFor(
                (string) ($participant['gender'] ?? 'M'),
                (float) $participant['body_weight']
            );
        }

        return '—';
    }

    /**
     * @param 'all'|'pending'|'done' $weighFilter
     */
    public static function forWeighIn(
        string $weighFilter = 'pending',
        string $q = '',
        ?int $disciplineId = null
    ): array {
        $sql = 'SELECT p.*,
                       GROUP_CONCAT(d.name_ru ORDER BY d.sort_order SEPARATOR ", ") AS disciplines';
        if ($disciplineId !== null && $disciplineId > 0) {
            $sql .= ',
                       (SELECT rf.weight_category FROM registrations rf
                        WHERE rf.participant_id = p.id AND rf.discipline_id = ?
                        LIMIT 1) AS discipline_weight_category';
        }
        $sql .= '
                FROM participants p
                LEFT JOIN registrations r ON r.participant_id = p.id
                LEFT JOIN disciplines d ON d.id = r.discipline_id
                WHERE p.status = ?';
        $params = [];

        if ($disciplineId !== null && $disciplineId > 0) {
            $params[] = $disciplineId;
        }
        $params[] = self::STATUS_APPROVED;

        if ($disciplineId !== null && $disciplineId > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1 FROM registrations rf
                        WHERE rf.participant_id = p.id AND rf.discipline_id = ?
                      )';
            $params[] = $disciplineId;
        }

        if ($weighFilter === 'pending') {
            $sql .= ' AND p.weighed_at IS NULL';
        } elseif ($weighFilter === 'done') {
            $sql .= ' AND p.weighed_at IS NOT NULL';
        }

        if ($q !== '') {
            $sql .= ' AND (p.full_name LIKE ? OR IFNULL(p.phone, "") LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' GROUP BY p.id
                  ORDER BY
                    (p.weighed_at IS NULL) DESC,
                    p.full_name';

        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function weighInCounts(): array
    {
        $st = Database::pdo()->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(weighed_at IS NOT NULL) AS done,
               SUM(weighed_at IS NULL) AS pending
             FROM participants
             WHERE status = ?'
        );
        $st->execute([self::STATUS_APPROVED]);
        $row = $st->fetch() ?: [];
        return [
            'all'     => (int) ($row['total'] ?? 0),
            'done'    => (int) ($row['done'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    /**
     * Счётчики по дисциплинам для экрана выбора на взвешивании.
     *
     * @return list<array{id: int, name_ru: string, code: string, count: int, pending: int, done: int}>
     */
    public static function weighInDisciplineCounts(): array
    {
        $st = Database::pdo()->prepare(
            'SELECT d.id, d.name_ru, d.code,
                    COUNT(DISTINCT p.id) AS total,
                    COUNT(DISTINCT CASE WHEN p.weighed_at IS NULL THEN p.id END) AS pending,
                    COUNT(DISTINCT CASE WHEN p.weighed_at IS NOT NULL THEN p.id END) AS done
             FROM disciplines d
             LEFT JOIN registrations r ON r.discipline_id = d.id
             LEFT JOIN participants p ON p.id = r.participant_id AND p.status = ?
             GROUP BY d.id, d.name_ru, d.code, d.sort_order
             ORDER BY d.sort_order'
        );
        $st->execute([self::STATUS_APPROVED]);

        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id'      => (int) $row['id'],
                'name_ru' => (string) $row['name_ru'],
                'code'    => (string) $row['code'],
                'count'   => (int) $row['total'],
                'pending' => (int) $row['pending'],
                'done'    => (int) $row['done'],
            ];
        }
        return $out;
    }

    public static function registrationsWithDisciplines(int $participantId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT r.discipline_id, r.opening_weight, r.weight_category, d.code, d.name_ru, d.scoring_type, d.sort_order
             FROM registrations r
             JOIN disciplines d ON d.id = r.discipline_id
             WHERE r.participant_id = ?
             ORDER BY d.sort_order'
        );
        $st->execute([$participantId]);
        return $st->fetchAll();
    }

    /**
     * @param array<int, float|null> $openings discipline_id => opening weight
     */
    public static function saveWeighIn(
        int $id,
        float $bodyWeight,
        ?string $weighNote = null,
        array $openings = []
    ): void {
        $participant = self::find($id);
        if (!$participant || ($participant['status'] ?? '') !== self::STATUS_APPROVED) {
            throw new \RuntimeException('Участник не найден или заявка не принята');
        }

        // Категорию не пересчитываем по весу на весах — берём выбранную при регистрации.
        $regs = self::registrationsWithDisciplines($id);
        $primaryCat = trim((string) ($participant['weight_category'] ?? ''));
        if ($primaryCat === '') {
            foreach ($regs as $reg) {
                $stored = trim((string) ($reg['weight_category'] ?? ''));
                if ($stored !== '') {
                    $primaryCat = $stored;
                    break;
                }
            }
        }
        if ($primaryCat === '') {
            $primaryCat = WeightCategory::labelFor(
                (string) ($participant['gender'] ?? 'M'),
                $bodyWeight
            );
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'UPDATE participants
                 SET body_weight = ?, weighed_at = NOW(), weigh_note = ?, weight_category = ?
                 WHERE id = ? AND status = ?'
            );
            $st->execute([
                round($bodyWeight, 2),
                $weighNote !== null && $weighNote !== '' ? $weighNote : null,
                $primaryCat,
                $id,
                self::STATUS_APPROVED,
            ]);

            if (empty($participant['lot_number'])) {
                $lot = (int) $pdo->query(
                    'SELECT COALESCE(MAX(lot_number), 0) + 1 FROM participants'
                )->fetchColumn();
                $pdo->prepare('UPDATE participants SET lot_number = ? WHERE id = ?')
                    ->execute([$lot, $id]);
            }

            $upd = $pdo->prepare(
                'UPDATE registrations SET opening_weight = ?
                 WHERE participant_id = ? AND discipline_id = ?'
            );
            $declare = $pdo->prepare(
                'INSERT INTO attempts
                   (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
                 VALUES (?, ?, 1, ?, NULL, 1)
                 ON DUPLICATE KEY UPDATE
                   declared_weight = VALUES(declared_weight)'
            );

            // Народный жим: штанга всегда = ½ собственного веса
            foreach (self::registrationsWithDisciplines($id) as $reg) {
                if (($reg['code'] ?? '') === FolkBench::CODE) {
                    $openings[(int) $reg['discipline_id']] = FolkBench::barWeight($bodyWeight);
                }
            }

            foreach ($openings as $disciplineId => $value) {
                $w = $value !== null ? round((float) $value, 2) : null;
                $upd->execute([$w, $id, (int) $disciplineId]);
                if ($w !== null) {
                    $declare->execute([$id, (int) $disciplineId, $w]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function assignLotIfMissing(int $id): void
    {
        $p = self::find($id);
        if (!$p || !empty($p['lot_number'])) {
            return;
        }
        $pdo = Database::pdo();
        $lot = (int) $pdo->query(
            'SELECT COALESCE(MAX(lot_number), 0) + 1 FROM participants'
        )->fetchColumn();
        $pdo->prepare('UPDATE participants SET lot_number = ? WHERE id = ?')
            ->execute([$lot, $id]);
    }
}
