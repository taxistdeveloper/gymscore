<?php

namespace App\Models;

/**
 * Наборка олимпийского грифа 20 кг стандартными дисками (кг).
 */
class BarLoader
{
    public const BAR_KG = 20.0;

    /** Цвета дисков (IPF-ish) */
    private const PLATES = [
        ['kg' => 25.0,  'color' => '#c8102e', 'label' => '25'],
        ['kg' => 20.0,  'color' => '#1d4ed8', 'label' => '20'],
        ['kg' => 15.0,  'color' => '#eab308', 'label' => '15'],
        ['kg' => 10.0,  'color' => '#16a34a', 'label' => '10'],
        ['kg' => 5.0,   'color' => '#f8fafc', 'label' => '5',  'dark' => true],
        ['kg' => 2.5,   'color' => '#111827', 'label' => '2.5'],
        ['kg' => 1.25,  'color' => '#9ca3af', 'label' => '1.25'],
        ['kg' => 0.5,   'color' => '#6b7280', 'label' => '0.5'],
        ['kg' => 0.25,  'color' => '#4b5563', 'label' => '0.25'],
    ];

    /**
     * @return array{
     *   total: float,
     *   bar: float,
     *   per_side: float,
     *   plates: list<array{kg: float, color: string, label: string, dark?: bool}>,
     *   ok: bool,
     *   note: ?string
     * }
     */
    public static function forWeight(float $totalKg): array
    {
        $bar = self::BAR_KG;
        if ($totalKg < $bar) {
            return [
                'total'    => $totalKg,
                'bar'      => $bar,
                'per_side' => 0.0,
                'plates'   => [],
                'ok'       => false,
                'note'     => 'Вес меньше грифа',
            ];
        }

        $remainder = round(($totalKg - $bar) / 2, 2);
        $perSide = $remainder;
        $plates = [];

        foreach (self::PLATES as $plate) {
            while ($remainder + 1e-9 >= $plate['kg']) {
                $plates[] = $plate;
                $remainder = round($remainder - $plate['kg'], 2);
            }
        }

        $ok = abs($remainder) < 0.01;
        return [
            'total'    => $totalKg,
            'bar'      => $bar,
            'per_side' => $perSide,
            'plates'   => $plates,
            'ok'       => $ok,
            'note'     => $ok ? null : 'Нельзя набрать ровно стандартными дисками',
        ];
    }
}
