<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Discipline;
use App\Models\Participant;

class ApplicationsController extends Controller
{
    public function index(): void
    {
        $disciplines = Participant::pendingDisciplineCounts();
        $disciplineId = (int) ($_GET['discipline'] ?? 0);
        $discipline = $disciplineId > 0 ? Discipline::find($disciplineId) : null;
        if (!$discipline) {
            $disciplineId = 0;
        }

        $totalPending = count(Participant::allWithDisciplines(Participant::STATUS_PENDING));

        $this->view('applications/index', [
            'disciplines'  => $disciplines,
            'discipline'   => $discipline,
            'disciplineId' => $disciplineId,
            'participants' => $disciplineId > 0
                ? Participant::allWithDisciplines(Participant::STATUS_PENDING, '', $disciplineId)
                : [],
            'totalPending' => $totalPending,
        ]);
    }
}
