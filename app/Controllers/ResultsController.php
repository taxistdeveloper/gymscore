<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Discipline;
use App\Models\Ranking;

class ResultsController extends Controller
{
    public function index(): void
    {
        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));
        $discipline = Discipline::find($disciplineId) ?? $disciplines[0] ?? null;

        $protocol = $discipline
            ? Ranking::protocolByCategory((int) $discipline['id'])
            : [];

        $this->view('results/index', [
            'disciplines' => $disciplines,
            'discipline'  => $discipline,
            'protocol'    => $protocol,
            'flash'       => $this->takeFlash(),
        ]);
    }

    /** Печатный протокол (Save as PDF в диалоге печати браузера). */
    public function pdf(): void
    {
        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? 0);
        $all = isset($_GET['all']) && (string) $_GET['all'] === '1';

        $list = $disciplines;
        if (!$all && $disciplineId > 0) {
            $one = Discipline::find($disciplineId);
            $list = $one ? [$one] : [];
        }

        $sections = [];
        foreach ($list as $d) {
            $sections[] = [
                'discipline' => $d,
                'protocol'   => Ranking::protocolByCategory((int) $d['id']),
            ];
        }

        $this->printView('results/print', [
            'disciplines'  => $disciplines,
            'sections'     => $sections,
            'disciplineId' => $all ? null : ($disciplineId > 0 ? $disciplineId : null),
        ]);
    }

    protected function printView(string $name, array $data = []): void
    {
        extract($data);
        $config = $this->config;
        require dirname(__DIR__) . '/Views/' . $name . '.php';
    }
}
