<?php

namespace App\Payroll\Statutory;

/** HRD Corp levy: employer-only, on Malaysian employees' monthly wages. */
class Hrdf
{
    public static function calculate(float $wages, float $ratePercent, bool $isLocal): float
    {
        if (! $isLocal || $ratePercent <= 0) {
            return 0.0;
        }

        return Money::sen($wages * $ratePercent / 100);
    }
}
