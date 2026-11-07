<?php

namespace App\Models;

/**
 * Жим лёжа народный 1/2 с/в:
 * вес на штанге = половина собственного веса (округление до шага 2.5 кг),
 * результат = число повторений.
 */
class FolkBench
{
    public const CODE = 'bench_folk';

    /** Шаг набора штанги (стандартные блины). */
    public const STEP_KG = 2.5;

    public static function isFolk(?array $discipline): bool
    {
        return ($discipline['code'] ?? '') === self::CODE;
    }

    /** Вес штанги: ½ собственного, до ближайших 2.5 кг. */
    public static function barWeight(float $bodyWeight): float
    {
        if ($bodyWeight <= 0) {
            return 0.0;
        }

        $half = $bodyWeight / 2;
        $step = self::STEP_KG;

        return round($half / $step) * $step;
    }
}
