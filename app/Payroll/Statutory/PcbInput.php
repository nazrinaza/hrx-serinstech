<?php

namespace App\Payroll\Statutory;

/**
 * Everything the LHDN computerised MTD formula needs for one employee-month.
 */
final class PcbInput
{
    public function __construct(
        public readonly int $month,                 // 1–12
        public readonly float $currentRemuneration, // Y1: taxable remuneration this month
        public readonly float $currentEpf,          // K1: employee EPF this month
        public readonly float $accumulatedRemuneration = 0, // ΣY: earlier months this year
        public readonly float $accumulatedEpf = 0,          // ΣK
        public readonly float $accumulatedPcb = 0,          // X
        public readonly int $category = 1,          // 1 single · 2 spouse not working · 3 spouse working / single parent
        public readonly int $children = 0,
        public readonly bool $disabled = false,
        public readonly bool $spouseDisabled = false,
        public readonly bool $resident = true,
    ) {}
}
