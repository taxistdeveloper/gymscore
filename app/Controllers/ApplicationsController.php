<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Participant;

class ApplicationsController extends Controller
{
    public function index(): void
    {
        $groups = Participant::pendingGroupedByDiscipline();

        $listed = 0;
        foreach ($groups as $g) {
            $listed += count($g['participants']);
        }

        $this->view('applications/index', [
            'groups'       => $groups,
            'totalPending' => count(Participant::allWithDisciplines(Participant::STATUS_PENDING)),
            'listedCount'  => $listed,
        ]);
    }
}
