<?php

namespace App\Payroll\Statutory;

/**
 * EPF (KWSP) contribution using the Third Schedule wage-band method.
 */
class Epf
{
    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self(config('statutory.epf'));
    }

    /**
     * @param  string  $category  local_below_60 | local_60_plus | foreigner
     * @return array{employee: float, employer: float}
     */
    public function calculate(float $wages, string $category, ?float $employeeRateOverride = null): array
    {
        $c = $this->config;
        if ($wages <= $c['min_wage']) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        $base = $this->bandUpperLimit($wages);
        $rates = $c['rates'][$category];
        $employerRate = $wages <= $rates['employer_threshold'] ? $rates['employer_low'] : $rates['employer_high'];
        $employeeRate = $employeeRateOverride ?? $rates['employee'];

        return [
            'employee' => Money::ceilRinggit($base * $employeeRate / 100),
            'employer' => Money::ceilRinggit($base * $employerRate / 100),
        ];
    }

    public function bandUpperLimit(float $wages): float
    {
        $c = $this->config;
        if ($wages <= $c['band_small_limit']) {
            return ceil(round($wages / $c['band_small'], 6)) * $c['band_small'];
        }
        if ($wages <= $c['band_large_limit']) {
            return ceil(round($wages / $c['band_large'], 6)) * $c['band_large'];
        }

        return $wages;
    }

    public static function category(bool $isLocal, ?int $age): string
    {
        if (! $isLocal) {
            return 'foreigner';
        }

        return ($age !== null && $age >= 60) ? 'local_60_plus' : 'local_below_60';
    }
}
