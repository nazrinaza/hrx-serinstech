<?php

namespace Tests\Feature;

use App\Models\EmployeeYtdOpening;
use App\Models\PayItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_calculates_statutory_deductions(): void
    {
        $company = $this->makeCompany('Acme', ['hrdf_rate' => 1]);
        $hr = $this->userIn($company, 'hr');
        $e = $this->employeeIn($company, [], basic: 3000);
        $e->payItems()->create(['pay_item_id' => PayItem::where('name', 'Transport allowance')->value('id'), 'amount' => 200, 'effective_from' => '2020-01-01']);

        $this->actingAs($hr)->post('/payroll', ['period' => '2026-11', 'pay_date' => '2026-11-30'])->assertRedirect();

        $p = Payslip::firstOrFail();
        $this->assertEquals(3200.00, $p->gross_pay);
        $this->assertEquals(3000.00, $p->epf_wages);       // transport allowance not EPF-able
        $this->assertEquals(330.00, $p->epf_employee);     // 11% of 3,000
        $this->assertEquals(390.00, $p->epf_employer);     // 13% (≤ RM5,000)
        $this->assertEquals(15.75, $p->socso_employee);    // band 3,100.01–3,200 → 3,150 × 0.5%
        $this->assertEquals(6.30, $p->eis_employee);       // 3,150 × 0.2%
        $this->assertEquals(30.00, $p->hrdf);              // 1% of 3,000 (transport excluded)
        $this->assertEquals(round(3200 - 330 - 15.75 - 6.30 - (float) $p->pcb, 2), (float) $p->net_pay);
    }

    public function test_joiner_is_prorated_by_calendar_days(): void
    {
        $company = $this->makeCompany();
        $this->employeeIn($company, ['join_date' => '2026-11-16'], basic: 3000); // 15 of 30 days

        $run = app(PayrollService::class)->createRun($company, 2026, 11, '2026-11-30');

        $p = $run->payslips()->first();
        $this->assertEquals(0.5, (float) $p->days_worked_ratio);
        $this->assertEquals(1500.00, (float) $p->gross_pay);
    }

    public function test_pcb_uses_earlier_months_and_opening_balance(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $e = $this->employeeIn($company, [], basic: 8000);
        $svc = app(PayrollService::class);

        // November alone, with no history, under-deducts…
        $nov = $svc->createRun($company, 2026, 11, '2026-11-30');
        $pcbNoHistory = (float) $nov->payslips()->first()->pcb;

        // …an opening balance for Jan–Oct makes it reflect the full year.
        EmployeeYtdOpening::create(['employee_id' => $e->id, 'year' => 2026, 'remuneration' => 80000, 'epf' => 3300, 'pcb' => 5000]);
        $svc->calculate($nov);
        $pcbWithHistory = (float) $nov->fresh()->payslips()->first()->pcb;
        $this->assertNotEquals($pcbNoHistory, $pcbWithHistory);

        // Finalized November feeds December.
        $svc->finalize($nov, $hr);
        $dec = $svc->createRun($company, 2026, 12, '2026-12-31');
        $this->assertGreaterThanOrEqual(0, (float) $dec->payslips()->first()->pcb);
    }

    public function test_manual_line_recalculates_and_finalized_run_is_locked(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $this->employeeIn($company, [], basic: 3000);
        $run = app(PayrollService::class)->createRun($company, 2026, 11, '2026-11-30');
        $p = $run->payslips()->first();

        $this->actingAs($hr)->post("/payroll/payslips/{$p->id}/lines", [
            'name' => 'Commission', 'type' => 'earning', 'amount' => 1000, 'epf' => 1, 'socso' => 1, 'eis' => 1, 'pcb' => 1,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertEquals(4000.00, (float) $p->gross_pay);
        $this->assertEquals(440.00, (float) $p->epf_employee);

        // Recalculating keeps the one-off line
        app(PayrollService::class)->calculate($run);
        $this->assertEquals(4000.00, (float) $p->fresh()->gross_pay);

        $this->actingAs($hr)->post("/payroll/{$run->id}/finalize")->assertSessionHasNoErrors();
        $this->actingAs($hr)->post("/payroll/payslips/{$p->id}/lines", [
            'name' => 'Late change', 'type' => 'earning', 'amount' => 50,
        ])->assertSessionHasErrors('status');
    }

    public function test_employee_sees_only_own_published_payslips(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $meUser = $this->userIn($company, 'employee');
        $me = $this->employeeIn($company, ['user_id' => $meUser->id]);
        $other = $this->employeeIn($company);
        $run = app(PayrollService::class)->createRun($company, 2026, 11, '2026-11-30');
        $mine = Payslip::where('employee_id', $me->id)->first();
        $theirs = Payslip::where('employee_id', $other->id)->first();

        // Not visible while draft / unpublished
        $this->actingAs($meUser)->get("/me/payslips/{$mine->id}/pdf")->assertNotFound();
        $this->actingAs($hr)->post("/payroll/{$run->id}/publish", ['publish' => 1])->assertSessionHasErrors('status');

        app(PayrollService::class)->finalize($run, $hr);
        $this->actingAs($hr)->post("/payroll/{$run->id}/publish", ['publish' => 1])->assertSessionHasNoErrors();

        $this->actingAs($meUser)->get('/me/payslips')->assertSee('November 2026');
        $this->actingAs($meUser)->get("/me/payslips/{$mine->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($meUser)->get("/me/payslips/{$theirs->id}/pdf")->assertNotFound();
    }

    public function test_bank_file_needs_finalized_run(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $this->employeeIn($company, ['bank_name' => 'Maybank', 'bank_account_no' => '123456789012']);
        $run = app(PayrollService::class)->createRun($company, 2026, 11, '2026-11-30');

        $this->actingAs($hr)->get("/payroll/{$run->id}/bank-file")->assertStatus(422);

        app(PayrollService::class)->finalize($run, $hr);
        $csv = $this->actingAs($hr)->get("/payroll/{$run->id}/bank-file")->assertOk()->streamedContent();
        $this->assertStringContainsString('123456789012', $csv);
    }

    public function test_cannot_finalize_out_of_order(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $this->employeeIn($company);
        $svc = app(PayrollService::class);
        $svc->createRun($company, 2026, 10, '2026-10-31');
        $nov = $svc->createRun($company, 2026, 11, '2026-11-30');

        $this->actingAs($hr)->post("/payroll/{$nov->id}/finalize")->assertSessionHasErrors('status');
        $this->assertTrue(PayrollRun::find($nov->id)->isDraft());
    }
}
