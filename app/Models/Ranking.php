<?php

namespace App\Models;

use App\Core\Database;

/**
 * Рейтинг дисциплины.
 *
 * Правило тай-брейка (пауэрлифтинг / силовые виды):
 * 1) Больше результат (кг / повторы / победы) — выше место.
 * 2) При равенстве результата — выше тот, у кого МЕНЬШЕ собственный вес.
 *
 * Пример: оба подняли 100 кг.
 *   Петров 79.9 кг → 1 место
 *   Иванов 80.0 кг → 2 место
 */
class Ranking
{
    public static function forDiscipline(int $disciplineId): array
    {
        $discipline = Discipline::find($disciplineId);
        if (!$discipline) {
            return [];
        }

        $st = Database::pdo()->prepare(
            'SELECT p.id, p.full_name, p.body_weight, p.gender, best.best_result
             FROM participants p
             JOIN registrations r
               ON r.participant_id = p.id AND r.discipline_id = ?
             JOIN (
               SELECT participant_id, MAX(value) AS best_result
               FROM attempts
               WHERE discipline_id = ? AND is_valid = 1 AND value IS NOT NULL
               GROUP BY participant_id
             ) best ON best.participant_id = p.id
             WHERE p.status = ?
             ORDER BY best.best_result DESC, p.body_weight ASC, p.full_name ASC'
        );
        $st->execute([$disciplineId, $disciplineId, Participant::STATUS_APPROVED]);

        $rows = $st->fetchAll();

        $place = 0;
        $rank = 0;
        $prevBest = null;
        $prevBw = null;

        foreach ($rows as &$row) {
            $rank++;
            $sameAsPrev = $prevBest !== null
                && (float) $row['best_result'] === (float) $prevBest
                && (float) $row['body_weight'] === (float) $prevBw;

            if (!$sameAsPrev) {
                $place = $rank;
            }

            $row['place'] = $place;
            $row['scoring_type'] = $discipline['scoring_type'];
            $row['tie_note'] = self::tieNote($rows, $rank - 1);

            $prevBest = $row['best_result'];
            $prevBw = $row['body_weight'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Протокол по категориям: в каждой — свои 1/2/3 места.
     *
     * @return list<array{category: string, gender: string, rows: list<array>}>
     */
    public static function protocolByCategory(int $disciplineId): array
    {
        $discipline = Discipline::find($disciplineId);
        if (!$discipline) {
            return [];
        }

        $st = Database::pdo()->prepare(
            'SELECT p.id, p.full_name, p.body_weight, p.gender, p.age_group,
                    COALESCE(r.weight_category, p.weight_category) AS weight_category,
                    p.lot_number, best.best_result
             FROM participants p
             JOIN registrations r
               ON r.participant_id = p.id AND r.discipline_id = ?
             JOIN (
               SELECT participant_id, MAX(value) AS best_result
               FROM attempts
               WHERE discipline_id = ? AND is_valid = 1 AND value IS NOT NULL
               GROUP BY participant_id
             ) best ON best.participant_id = p.id
             WHERE p.status = ?
             ORDER BY p.gender ASC, COALESCE(r.weight_category, p.weight_category) ASC, p.body_weight ASC'
        );
        $st->execute([$disciplineId, $disciplineId, Participant::STATUS_APPROVED]);
        $all = $st->fetchAll();

        $groups = [];
        foreach ($all as $row) {
            $cat = trim((string) ($row['weight_category'] ?? ''));
            if ($cat === '') {
                $cat = WeightCategory::forDiscipline(
                    $discipline,
                    (string) ($row['age_group'] ?? 'open'),
                    (float) $row['body_weight']
                );
            }
            $key = ($row['gender'] ?? 'M') . '|' . $cat;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'category' => $cat,
                    'gender'   => $row['gender'] ?? 'M',
                    'rows'     => [],
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        $out = [];
        foreach ($groups as $group) {
            usort($group['rows'], static function (array $a, array $b): int {
                $rb = (float) $b['best_result'] <=> (float) $a['best_result'];
                if ($rb !== 0) {
                    return $rb;
                }
                $bw = (float) $a['body_weight'] <=> (float) $b['body_weight'];
                if ($bw !== 0) {
                    return $bw;
                }
                return strcmp((string) $a['full_name'], (string) $b['full_name']);
            });

            $place = 0;
            $rank = 0;
            $prevBest = null;
            $prevBw = null;
            foreach ($group['rows'] as &$row) {
                $rank++;
                $same = $prevBest !== null
                    && (float) $row['best_result'] === (float) $prevBest
                    && (float) $row['body_weight'] === (float) $prevBw;
                if (!$same) {
                    $place = $rank;
                }
                $row['place'] = $place;
                $row['scoring_type'] = $discipline['scoring_type'];
                $prevBest = $row['best_result'];
                $prevBw = $row['body_weight'];
            }
            unset($row);

            $group['podium'] = array_values(array_filter(
                $group['rows'],
                static fn (array $r) => (int) $r['place'] <= 3
            ));
            $out[] = $group;
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['gender'] !== $b['gender']) {
                return $a['gender'] === 'M' ? -1 : 1;
            }
            return strcmp($a['category'], $b['category']);
        });

        return $out;
    }

    /** Подсказка: место решено по собственному весу */
    private static function tieNote(array $rows, int $index): ?string
    {
        if ($index < 0 || !isset($rows[$index])) {
            return null;
        }
        $cur = $rows[$index];
        $neighbors = [];
        if ($index > 0) {
            $neighbors[] = $rows[$index - 1];
        }
        if (isset($rows[$index + 1])) {
            $neighbors[] = $rows[$index + 1];
        }
        foreach ($neighbors as $n) {
            if ((float) $n['best_result'] === (float) $cur['best_result']
                && (float) $n['body_weight'] !== (float) $cur['body_weight']) {
                return 'Тай-брейк по собственному весу';
            }
        }
        return null;
    }

    public static function overallSummary(): array
    {
        $disciplines = Discipline::all();
        $participants = Participant::allApproved();

        $summary = [];

        foreach ($participants as $p) {
            $summary[$p['id']] = [
                'participant' => $p,
                'places'      => [],
                'medals'      => 0,
            ];
        }

        foreach ($disciplines as $d) {
            foreach (self::forDiscipline((int) $d['id']) as $row) {
                $pid = (int) $row['id'];
                if (!isset($summary[$pid])) {
                    continue;
                }
                $summary[$pid]['places'][$d['code']] = [
                    'place'       => $row['place'],
                    'best_result' => $row['best_result'],
                    'name'        => $d['name_ru'],
                ];
                if ((int) $row['place'] <= 3) {
                    $summary[$pid]['medals']++;
                }
            }
        }

        usort($summary, function ($a, $b) {
            if ($a['medals'] !== $b['medals']) {
                return $b['medals'] <=> $a['medals'];
            }
            return $a['participant']['body_weight'] <=> $b['participant']['body_weight'];
        });

        return array_values($summary);
    }
}
