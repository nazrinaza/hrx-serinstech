<?php

namespace App\Payroll\Statutory;

/**
 * Shared wage-band lookup used by SOCSO and EIS: hard-coded low bands up to RM300,
 * then RM100 bands where the contribution is the rate applied to the band midpoint.
 */
trait BandedContribution
{
    /** @return array{employer: float, employee: float} */
    protected function banded(float $wages, float $ceiling, array $lowBands, float $employerRate, float $employeeRate): array
    {
        if ($wages <= 0) {
            return ['employer' => 0.0, 'employee' => 0.0];
        }

        foreach ($lowBands as [$upper, $employer, $employee]) {
            if ($wages <= $upper) {
                return ['employer' => (float) $employer, 'employee' => (float) $employee];
            }
        }

        $capped = min($wages, $ceiling);
        $bandUpper = ceil(round($capped / 100, 6)) * 100;
        $midpoint = $bandUpper - 50;

        return [
            'employer' => Money::nearest5SenTieToFive($midpoint * $employerRate / 100),
            'employee' => Money::nearest5SenTieToFive($midpoint * $employeeRate / 100),
        ];
    }
}
