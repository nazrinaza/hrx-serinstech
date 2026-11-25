<?php

namespace App\Payroll\Statutory;

/**
 * EIS (SIP) contribution — Malaysians and PRs aged 18 to below 60 only.
 */
class Eis
{
    use BandedContribution;

    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self(config('statutory.eis'));
    }

    public function applies(bool $isLocal, ?int $age): bool
    {
        return $isLocal && ($age === null || ($age >= 18 && $age < $this->config['max_age']));
    }

    /** @return array{employer: float, employee: float} */
    public function calculate(float $wages): array
    {
        return $this->banded(
            $wages,
            $this->config['wage_ceiling'],
            $this->config['low_bands'],
            $this->config['employer'],
            $this->config['employee'],
        );
    }
}
