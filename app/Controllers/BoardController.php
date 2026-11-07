<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ArmMatch;
use App\Models\Attempt;
use App\Models\BarLoader;
use App\Models\Discipline;
use App\Models\FolkBench;
use App\Models\PlatformQueue;
use App\Models\PlatformState;
use App\Models\Ranking;
use App\Models\WeightCategory;

class BoardController extends Controller
{
    public function index(): void
    {
        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));

        $this->view('board/live', [
            'disciplines'  => $disciplines,
            'disciplineId' => $disciplineId,
            'boot'         => $this->payload($disciplineId),
            'wide'         => true,
            'live'         => true,
            'flash'        => null,
        ]);
    }

    public function data(): void
    {
        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));
        $this->json($this->payload($disciplineId));
    }

    /** @return array<string, mixed> */
    private function payload(int $disciplineId): array
    {
        $disciplines = Discipline::all();
        $discipline = Discipline::find($disciplineId) ?? $disciplines[0] ?? null;

        if (!$discipline) {
            return ['ok' => false, 'error' => 'no_discipline'];
        }

        $did = (int) $discipline['id'];
        $scoring = $discipline['scoring_type'] ?? 'weight';
        $unit = match ($scoring) {
            'reps' => 'раз',
            'arm'  => 'побед',
            default => 'кг',
        };
        $attemptsAllowed = (int) ($discipline['attempts_allowed'] ?? 3);
        $isFolk = FolkBench::isFolk($discipline);
        $isArm = $scoring === 'arm';

        $ranking = Ranking::forDiscipline($did);
        $board = array_map(static function (array $row) {
            return [
                'place'       => (int) $row['place'],
                'full_name'   => $row['full_name'],
                'best_result' => (float) $row['best_result'],
                'body_weight' => (float) $row['body_weight'],
                'tie_note'    => $row['tie_note'] ?? null,
            ];
        }, $ranking);

        if ($isArm) {
            $match = ArmMatch::current($did);
            $wins = ArmMatch::winCounts($did);
            $upcomingMatches = ArmMatch::upcoming(
                $did,
                $match ? (int) $match['id'] : null
            );
            $upcoming = array_map(static function (array $m) {
                return [
                    'full_name'  => $m['p1_name'] . ' vs ' . ($m['p2_name'] ?? 'bye'),
                    'attempt_no' => (int) $m['round_no'],
                    'lot_number' => null,
                    'declared_weight' => 0,
                    'deferred'   => false,
                    'is_arm'     => true,
                    'round_no'   => (int) $m['round_no'],
                    'match_no'   => (int) $m['match_no'],
                    'category'   => $m['weight_category'],
                ];
            }, $upcomingMatches);

            return [
                'ok' => true,
                'ts' => time(),
                'discipline' => [
                    'id'               => $did,
                    'name'             => $discipline['name_ru'],
                    'code'             => $discipline['code'] ?? '',
                    'scoring_type'     => $scoring,
                    'unit'             => $unit,
                    'display_unit'     => $unit,
                    'is_folk'          => false,
                    'attempts_allowed' => $attemptsAllowed,
                ],
                'current' => null,
                'match' => $match ? [
                    'id'         => (int) $match['id'],
                    'round_no'   => (int) $match['round_no'],
                    'match_no'   => (int) $match['match_no'],
                    'category'   => $match['weight_category'],
                    'status'     => $match['status'],
                    'left' => [
                        'full_name'   => $match['p1_name'],
                        'body_weight' => (float) $match['p1_bw'],
                        'wins'        => $wins[(int) $match['participant1_id']] ?? 0,
                        'lot_number'  => $match['p1_lot'],
                    ],
                    'right' => $match['participant2_id'] !== null ? [
                        'full_name'   => $match['p2_name'],
                        'body_weight' => (float) $match['p2_bw'],
                        'wins'        => $wins[(int) $match['participant2_id']] ?? 0,
                        'lot_number'  => $match['p2_lot'],
                    ] : null,
                ] : null,
                'bar'      => null,
                'upcoming' => $upcoming,
                'ranking'  => $board,
            ];
        }

        $queue = PlatformQueue::forDiscipline($did);
        $currentKey = PlatformState::getCurrent($did);
        $current = null;

        if ($queue) {
            if ($currentKey) {
                foreach ($queue as $item) {
                    if (($item['queue_key'] ?? '') === $currentKey) {
                        $current = $item;
                        break;
                    }
                }
            }
            $current = $current ?? $queue[0];
        }

        $bar = null;
        $category = null;
        if ($current) {
            $category = $current['weight_category']
                ?: WeightCategory::forDiscipline(
                    $discipline,
                    (string) ($current['age_group'] ?? 'open'),
                    (float) $current['body_weight']
                );
            if ($scoring === 'weight' || $isFolk) {
                $loaded = BarLoader::forWeight((float) $current['declared_weight']);
                $bar = [
                    'per_side' => $loaded['per_side'],
                    'plates'   => array_map(
                        static function (array $p) {
                            return [
                                'label' => $p['label'],
                                'color' => $p['color'],
                                'dark'  => !empty($p['dark']),
                            ];
                        },
                        $loaded['plates']
                    ),
                ];
            }
        }

        $upcoming = [];
        $queueBoard = [];
        foreach ($queue as $qi => $item) {
            $entry = [
                'participant_id'  => (int) $item['participant_id'],
                'full_name'       => $item['full_name'],
                'attempt_no'      => (int) $item['attempt_no'],
                'lot_number'      => $item['lot_number'],
                'declared_weight' => (float) $item['declared_weight'],
                'deferred'        => !empty($item['deferred']),
                'queue_key'       => (string) ($item['queue_key'] ?? ((int) $item['participant_id'] . '-' . (int) $item['attempt_no'])),
                'pos'             => $qi + 1,
            ];
            $queueBoard[] = $entry;
            if ($current && ($item['queue_key'] ?? '') === ($current['queue_key'] ?? '')) {
                continue;
            }
            if (count($upcoming) < 12) {
                $upcoming[] = $entry;
            }
        }

        $lastJudged = Attempt::lastJudged($did);
        $lastResult = null;
        if ($lastJudged && ($isFolk || $scoring === 'reps')) {
            $lastResult = [
                'full_name'   => $lastJudged['full_name'],
                'reps'        => (int) round((float) $lastJudged['value']),
                'bar_weight'  => $lastJudged['declared_weight'] !== null
                    ? (float) $lastJudged['declared_weight']
                    : null,
                'body_weight' => (float) $lastJudged['body_weight'],
                'is_valid'    => (int) ($lastJudged['is_valid'] ?? 1) === 1,
            ];
        }

        return [
            'ok' => true,
            'ts' => time(),
            'discipline' => [
                'id'               => $did,
                'name'             => $discipline['name_ru'],
                'code'             => $discipline['code'] ?? '',
                'scoring_type'     => $scoring,
                'unit'             => $unit,
                'display_unit'     => $isFolk ? 'кг' : $unit,
                'is_folk'          => $isFolk,
                'attempts_allowed' => $attemptsAllowed,
            ],
            'current' => $current ? [
                'participant_id'  => (int) $current['participant_id'],
                'full_name'       => $current['full_name'],
                'attempt_no'      => (int) $current['attempt_no'],
                'declared_weight' => (float) $current['declared_weight'],
                'body_weight'     => (float) $current['body_weight'],
                'lot_number'      => $current['lot_number'],
                'category'        => $category,
                'gender'          => $current['gender'],
                'deferred'        => !empty($current['deferred']),
                'queue_key'       => (string) ($current['queue_key'] ?? ((int) $current['participant_id'] . '-' . (int) $current['attempt_no'])),
            ] : null,
            'last_result' => $lastResult,
            'match'       => null,
            'bar'         => $bar,
            'upcoming'    => $upcoming,
            'queue'       => $queueBoard,
            'ranking'     => $board,
        ];
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
