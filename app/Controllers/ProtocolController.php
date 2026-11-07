<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Attempt;
use App\Models\Discipline;
use App\Models\FolkBench;
use App\Models\Participant;
use App\Models\PlatformQueue;
use App\Models\PlatformState;
use App\Models\PowerProtocol;

class ProtocolController extends Controller
{
    /** Публичный протокол — только просмотр, без авторизации. */
    public function index(): void
    {
        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));
        $discipline = Discipline::find($disciplineId) ?? $disciplines[0] ?? null;

        // Армрестлинг — таблица мест и боёв, не попытки как у жима
        if ($discipline && ($discipline['scoring_type'] ?? '') === 'arm') {
            $this->view('protocol/arm', [
                'disciplines' => $disciplines,
                'discipline'  => $discipline,
                'armProtocol' => \App\Models\ArmMatch::buildProtocol((int) $discipline['id']),
                'flash'       => null,
                'wide'        => true,
            ]);
            return;
        }

        $participants = $discipline
            ? Participant::registeredFor((int) $discipline['id'])
            : [];

        $attemptsMap = [];
        if ($discipline) {
            foreach (Attempt::allForDiscipline((int) $discipline['id']) as $a) {
                $attemptsMap[$a['participant_id']][$a['attempt_no']] = $a;
            }
        }

        $queue = $discipline
            ? PlatformQueue::forDiscipline((int) $discipline['id'])
            : [];

        // Только чтение — не меняем текущего на помосте
        $storedKey = $discipline
            ? PlatformState::getCurrent((int) $discipline['id'])
            : null;

        $current = null;
        if ($queue) {
            if ($storedKey) {
                foreach ($queue as $item) {
                    $key = (string) ($item['queue_key'] ?? ((int) $item['participant_id'] . '-' . (int) $item['attempt_no']));
                    if ($key === $storedKey) {
                        $current = $item;
                        break;
                    }
                }
            }
            $current = $current ?? $queue[0];
        }

        $protocolData = $discipline
            ? PowerProtocol::buildProtocol($discipline, $participants, $attemptsMap, $queue, $current)
            : ['categories' => [], 'total_athletes' => 0, 'judged_attempts' => 0];

        $records = PowerProtocol::records($discipline['name_ru'] ?? 'Любители Жим штанги лежа без экипировки');
        $normatives = PowerProtocol::normatives($discipline['code'] ?? 'bench');

        $this->view('protocol/index', [
            'disciplines'  => $disciplines,
            'discipline'   => $discipline,
            'participants' => $participants,
            'protocolData' => $protocolData,
            'records'      => $records,
            'normatives'   => $normatives,
            'queue'        => $queue,
            'current'      => $current,
            'isFolk'       => FolkBench::isFolk($discipline),
            'flash'        => null,
            'wide'         => true,
        ]);
    }
}
