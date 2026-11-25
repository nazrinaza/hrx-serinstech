<?php

namespace Tests\Unit\Statutory;

use App\Payroll\Statutory\Eis;
use App\Payroll\Statutory\Epf;
use App\Payroll\Statutory\Money;
use App\Payroll\Statutory\Pcb;
use App\Payroll\Statutory\PcbInput;
use App\Payroll\Statutory\Socso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Expected values follow the KWSP Third Schedule and PERKESO/EIS schedule rules.
 * SOCSO category 2 and PCB rows still need confirming against the official
 * calculators — add rows whenever an accountant confirms a figure.
 */
class StatutoryTest extends TestCase
{
    private function config(): array
    {
        return require __DIR__.'/../../../config/statutory.php';
    }

    public static function epfCases(): array
    {
        return [
            'nil up to RM10' => [10.00, 'local_below_60', 0, 0],
            'RM10.01–20 band' => [15.00, 'local_below_60', 3, 3],
            'RM3,000' => [3000.00, 'local_below_60', 330, 390],
            'RM3,000.50 → RM3,020 band' => [3000.50, 'local_below_60', 333, 393],
            'RM5,000 (13% employer)' => [5000.00, 'local_below_60', 550, 650],
            'RM5,000.01 → RM5,100 band (12%)' => [5000.01, 'local_below_60', 561, 612],
            'RM25,000 straight %' => [25000.00, 'local_below_60', 2750, 3000],
            'Age 60+: employer 4% only' => [3000.00, 'local_60_plus', 0, 120],
            'Foreign worker 2%/2%' => [2000.00, 'foreigner', 40, 40],
        ];
    }

    #[DataProvider('epfCases')]
    public function test_epf(float $wages, string $cat, float $ee, float $er): void
    {
        $r = (new Epf($this->config()['epf']))->calculate($wages, $cat);
        $this->assertSame([(float) $ee, (float) $er], [$r['employee'], $r['employer']]);
    }

    public static function socsoCases(): array
    {
        return [
            'RM30 band' => [25.00, 1, 0.40, 0.10],
            'RM300.01–400' => [350.00, 1, 6.15, 1.75],
            'RM4,900.01–5,000' => [5000.00, 1, 86.65, 24.75],
            'RM5,900.01–6,000' => [6000.00, 1, 104.15, 29.75],
            'Above ceiling capped' => [9000.00, 1, 104.15, 29.75],
            'Cat 2 employer only' => [6000.00, 2, 74.35, 0.0],
        ];
    }

    #[DataProvider('socsoCases')]
    public function test_socso(float $wages, int $cat, float $er, float $ee): void
    {
        $r = (new Socso($this->config()['socso']))->calculate($wages, $cat);
        $this->assertSame([$er, $ee], [$r['employer'], $r['employee']]);
    }

    public function test_eis(): void
    {
        $eis = new Eis($this->config()['eis']);
        $this->assertSame(['employer' => 11.90, 'employee' => 11.90], $eis->calculate(6000));
        $this->assertSame(['employer' => 11.90, 'employee' => 11.90], $eis->calculate(12000));
        $this->assertSame(['employer' => 6.90, 'employee' => 6.90], $eis->calculate(3500));
        $this->assertFalse($eis->applies(true, 60));
        $this->assertFalse($eis->applies(false, 30));
        $this->assertTrue($eis->applies(true, 30));
    }

    public function test_pcb_single_rm5000_january(): void
    {
        $pcb = new Pcb($this->config()['pcb']);
        $this->assertSame(110.00, $pcb->calculate(new PcbInput(month: 1, currentRemuneration: 5000, currentEpf: 550)));
    }

    public function test_pcb_below_threshold_is_zero(): void
    {
        $pcb = new Pcb($this->config()['pcb']);
        $this->assertSame(0.0, $pcb->calculate(new PcbInput(month: 1, currentRemuneration: 2500, currentEpf: 275)));
    }

    public function test_pcb_spouse_and_children_reduce_tax(): void
    {
        $pcb = new Pcb($this->config()['pcb']);
        $single = $pcb->calculate(new PcbInput(1, 8000, 880));
        $family = $pcb->calculate(new PcbInput(1, 8000, 880, category: 2, children: 2));
        $this->assertGreaterThan($family, $single);
    }

    public function test_pcb_accumulated_months_converge_to_annual_tax(): void
    {
        // Twelve equal months should total roughly the annual tax on the same income.
        $pcb = new Pcb($this->config()['pcb']);
        $y = $k = $x = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $d = $pcb->calculate(new PcbInput($m, 6000, 660, $y, $k, $x));
            $y += 6000; $k += 660; $x += $d;
        }
        $annual = $pcb->annualTax(72000 - 4000 - 9000, 1);
        $this->assertEqualsWithDelta($annual, $x, 1.0);
    }

    public function test_non_resident_flat_30_percent(): void
    {
        $pcb = new Pcb($this->config()['pcb']);
        $this->assertSame(1500.0, $pcb->calculate(new PcbInput(1, 5000, 0, resident: false)));
    }

    public function test_rounding_helpers(): void
    {
        $this->assertSame(104.15, Money::nearest5SenTieToFive(104.125));
        $this->assertSame(4.35, Money::nearest5SenTieToFive(4.375));
        $this->assertSame(110.0, Money::ceil5Sen(109.9998));
    }
}
