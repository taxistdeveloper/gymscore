<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Participant;

class ApplicationsController extends Controller
{
    public function index(): void
    {
        $this->view('applications/index', [
            'participants' => Participant::allWithDisciplines(Participant::STATUS_PENDING),
        ]);
    }
}
