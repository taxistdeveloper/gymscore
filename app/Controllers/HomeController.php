<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Discipline;
use App\Models\Participant;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home/index', [
            'disciplines'  => Discipline::all(),
            'participants' => Participant::allApproved(),
            'flash'        => $this->takeFlash(),
        ]);
    }
}
