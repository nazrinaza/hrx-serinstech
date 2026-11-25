<?php

namespace App\Payroll\Statutory;

/** Rounding helpers shared by the statutory calculators (amounts in RM). */
final class Money
{
    /** Round up to the next whole ringgit (EPF). */
    public static function ceilRinggit(float $amount): float
    {
        return (float) ceil(round($amount, 4));
    }

    /** Round up to the next 5 sen (PCB). */
    public static function ceil5Sen(float $amount): float
    {
        return ceil(round($amount * 100, 4) / 5) * 5 / 100;
    }

    /**
     * Round to the nearest 5 sen; an exact tie goes to the value ending in 5 sen.
     * Reproduces the SOCSO/EIS schedule values (e.g. 104.125 → 104.15, 4.375 → 4.35).
     */
    public static function nearest5SenTieToFive(float $amount): float
    {
        $units = round($amount * 100, 4) / 5;
        $floor = floor($units);
        if (abs($units - $floor - 0.5) < 1e-9) {
            $units = ((int) $floor) % 2 === 1 ? $floor : $floor + 1;
        } else {
            $units = round($units);
        }

        return round($units * 5 / 100, 2);
    }

    public static function sen(float $amount): float
    {
        return round($amount, 2);
    }
}
