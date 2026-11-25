<?php

namespace App\Payroll\Statutory;

/**
 * PCB / MTD (monthly tax deduction) — LHDN computerised calculation method for normal remuneration:
 *
 *   P   = [Σ(Y−K) + (Y1−K1) + (Y2−K2)·n] − (D + S + DU + SU + QC)
 *   MTD = [ (P − M)·R + B − X ] / (n + 1)
 *
 * Additional remuneration (bonus, arrears) and zakat are not handled yet.
 */
class Pcb
{
    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self(config('statutory.pcb'));
    }

    public static function category(string $maritalStatus, bool $spouseWorking, int $children): int
    {
        if ($maritalStatus === 'married') {
            return $spouseWorking ? 3 : 2;
        }

        return ($children > 0 && in_array($maritalStatus, ['divorced', 'widowed'], true)) ? 3 : 1;
    }

    public function calculate(PcbInput $in): float
    {
        $c = $this->config;

        if (! $in->resident) {
            return Money::sen($in->currentRemuneration * $c['non_resident_rate'] / 100);
        }

        $n = 12 - $in->month;
        $cap = $c['epf_relief_cap'];

        // EPF counted towards relief is capped for the whole year.
        $k = min($in->accumulatedEpf, $cap);
        $k1 = min($in->currentEpf, $cap - $k);
        $k2 = $n > 0 ? Money::sen(min($k1, ($cap - $k - $k1) / $n)) : 0.0;

        $reliefs = $c['individual_relief']
            + ($in->category === 2 ? $c['spouse_relief'] : 0)
            + ($in->disabled ? $c['disabled_relief'] : 0)
            + ($in->spouseDisabled && $in->category === 2 ? $c['disabled_spouse_relief'] : 0)
            + ($in->category !== 1 ? $in->children * $c['child_relief'] : 0);

        $p = ($in->accumulatedRemuneration - $k)
            + ($in->currentRemuneration - $k1)
            + ($in->currentRemuneration - $k2) * $n
            - $reliefs;
        $p = Money::sen($p);

        $yearTax = $this->annualTax($p, $in->category);
        $mtd = ($yearTax - $in->accumulatedPcb) / ($n + 1);

        if ($mtd <= 0) {
            return 0.0;
        }

        $mtd = Money::ceil5Sen($mtd);

        return $mtd < $c['minimum_deduction'] ? 0.0 : $mtd;
    }

    /** Tax on chargeable income P after rebates, per the MTD bracket table. */
    public function annualTax(float $p, int $category): float
    {
        $bracket = null;
        foreach ($this->config['brackets'] as $row) {
            if ($p > $row[1]) {
                $bracket = $row;
            }
        }
        if ($bracket === null) {
            return 0.0;
        }

        [, $m, $r, $bSingle, $bSpouse] = $bracket;
        $b = $category === 2 ? $bSpouse : $bSingle;

        return max(0.0, ($p - $m) * $r / 100 + $b);
    }
}
