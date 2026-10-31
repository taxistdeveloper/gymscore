<?php

namespace App\Models;

/**
 * Жим лёжа народный 1/2 с/в:
 * вес на штанге = половина собственного веса (округление до 0.5 кг),
 * результат = число повторений.
 */
class FolkBench
{
    public const CODE = 'bench_folk';

    public static function isFolk(?array $discipline): bool
    {
        return ($discipline['code'] ?? '') === self::CODE;
    }

    /** Вес штанги: ½ собственного, до ближайших 0.5 кг. */
    public static function barWeight(float $bodyWeight): float
    {
        return round($bodyWeight) / 2;
    }
}
