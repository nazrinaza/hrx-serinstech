<?php

namespace App\Payroll\Statutory;

/**
 * SOCSO (PERKESO) contribution, First Schedule.
 */
class Socso
{
    use BandedContribution;

    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self(config('statutory.socso'));
    }

    /** Category 2 (injury only, employer pays) applies from age 60 and to foreign workers. */
    public static function category(bool $isLocal, ?int $age): int
    {
        return (! $isLocal || ($age !== null && $age >= 60)) ? 2 : 1;
    }

    /** @return array{employer: float, employee: float} */
    public function calculate(float $wages, int $category): array
    {
        $key = 'cat'.$category;
        $rates = $this->config['rates'][$key];

        return $this->banded(
            $wages,
            $this->config['wage_ceiling'],
            $this->config['low_bands_'.$key],
            $rates['employer'],
            $rates['employee'],
        );
    }
}
