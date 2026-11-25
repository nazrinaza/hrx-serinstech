<?php

namespace App\Payroll;

use App\Leave\LeaveService;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayItem;
use App\Models\EmployeeYtdOpening;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\User;
use App\Payroll\Statutory\Eis;
use App\Payroll\Statutory\Epf;
use App\Payroll\Statutory\Hrdf;
use App\Payroll\Statutory\Money;
use App\Payroll\Statutory\Pcb;
use App\Payroll\Statutory\PcbInput;
use App\Payroll\Statutory\Socso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    private const ALL_FLAGS = ['epf' => true, 'socso' => true, 'eis' => true, 'pcb' => true, 'hrdf' => true];

    public function __construct(private LeaveService $leave) {}

    public function createRun(Company $company, int $year, int $month, string $payDate): PayrollRun
    {
        if (PayrollRun::where(['company_id' => $company->id, 'year' => $year, 'month' => $month])->exists()) {
            throw ValidationException::withMessages(['month' => 'A payroll run for this month already exists.']);
        }

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'year' => $year,
            'month' => $month,
            'pay_date' => $payDate,
            'status' => 'draft',
        ]);

        $this->calculate($run);

        return $run;
    }

    /** (Re)calculate every payslip in a draft run. Manual one-off lines are kept. */
    public function calculate(PayrollRun $run): void
    {
        $this->assertDraft($run);

        DB::transaction(function () use ($run) {
            $employees = Employee::where('company_id', $run->company_id)
                ->employedBetween($run->periodStart(), $run->periodEnd())
                ->with('salaries', 'company', 'department', 'position')
                ->get();

            $keep = [];
            foreach ($employees as $employee) {
                $payslip = $this->calculatePayslip($run, $employee);
                if ($payslip) {
                    $keep[] = $payslip->id;
                }
            }

            $run->payslips()->whereNotIn('id', $keep)->delete();
        });
    }

    public function calculatePayslip(PayrollRun $run, Employee $employee): ?Payslip
    {
        $company = $run->company;
        $start = $run->periodStart();
        $end = $run->periodEnd();
        $from = $employee->join_date->max($start);
        $to = $employee->resign_date ? $employee->resign_date->min($end) : $end;

        $salary = $employee->salaryOn($to);
        if ($salary === null) {
            return null; // no salary on record yet: skip rather than pay RM0
        }

        $payslip = Payslip::firstOrNew(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);
        $manual = $payslip->exists
            ? $payslip->lines()->where('is_manual', true)->get(['name', 'type', 'amount', 'flags', 'pay_item_id'])->toArray()
            : [];

        $ratio = round(((int) round($from->diffInDays($to)) + 1) / $start->daysInMonth, 4);
        $basic = (float) $salary->basic_salary;

        $lines = [[
            'name' => 'Basic salary'.($ratio < 1 ? ' (prorated)' : ''),
            'type' => 'earning',
            'amount' => Money::sen($basic * $ratio),
            'flags' => self::ALL_FLAGS,
            'pay_item_id' => null,
            'is_manual' => false,
        ]];

        $recurring = EmployeePayItem::with('payItem')
            ->where('employee_id', $employee->id)
            ->activeOn($to)
            ->get();
        foreach ($recurring as $item) {
            $lines[] = [
                'name' => $item->payItem->name,
                'type' => $item->payItem->type,
                'amount' => Money::sen((float) $item->amount * $ratio),
                'flags' => $item->payItem->flags(),
                'pay_item_id' => $item->pay_item_id,
                'is_manual' => false,
            ];
        }

        $unpaidDays = $this->leave->unpaidDaysBetween($employee, $from, $to);
        if ($unpaidDays > 0) {
            $amount = Money::sen($this->unpaidLeaveDeduction($basic, $unpaidDays, $run, $company));
            if ($amount > 0) {
                $lines[] = [
                    'name' => "Unpaid leave ({$unpaidDays} day".($unpaidDays == 1 ? '' : 's').')',
                    'type' => 'deduction',
                    'amount' => $amount,
                    'flags' => self::ALL_FLAGS, // unpaid leave reduces every statutory wage base
                    'pay_item_id' => null,
                    'is_manual' => false,
                ];
            }
        }

        foreach ($manual as $m) {
            $lines[] = ['amount' => (float) $m['amount'], 'is_manual' => true] + $m;
        }

        $wages = fn (string $flag) => max(0, array_sum(array_map(
            fn ($l) => ($l['flags'][$flag] ?? false) ? ($l['type'] === 'earning' ? $l['amount'] : -$l['amount']) : 0,
            $lines,
        )));

        $age = $employee->ageOn($end);
        $local = $employee->isLocal();

        $epf = Epf::make()->calculate(
            $wages('epf'),
            Epf::category($local, $age),
            $employee->epf_employee_rate !== null ? (float) $employee->epf_employee_rate : null,
        );
        $socso = Socso::make()->calculate($wages('socso'), Socso::category($local, $age));
        $eisCalc = Eis::make();
        $eis = $eisCalc->applies($local, $age) ? $eisCalc->calculate($wages('eis')) : ['employer' => 0.0, 'employee' => 0.0];
        $hrdf = Hrdf::calculate($wages('hrdf'), (float) $company->hrdf_rate, $local);

        $pcbTaxable = $wages('pcb');
        $ytd = $this->yearToDate($employee, $run);
        $pcb = Pcb::make()->calculate(new PcbInput(
            month: $run->month,
            currentRemuneration: $pcbTaxable,
            currentEpf: $epf['employee'],
            accumulatedRemuneration: $ytd['remuneration'],
            accumulatedEpf: $ytd['epf'],
            accumulatedPcb: $ytd['pcb'],
            category: Pcb::category($employee->marital_status, $employee->spouse_working, $employee->children_count),
            children: $employee->children_count,
            disabled: $employee->is_disabled,
            spouseDisabled: $employee->spouse_disabled,
            resident: $employee->is_tax_resident,
        ));

        $earnings = array_sum(array_map(fn ($l) => $l['type'] === 'earning' ? $l['amount'] : 0, $lines));
        $otherDeductions = array_sum(array_map(fn ($l) => $l['type'] === 'deduction' ? $l['amount'] : 0, $lines));
        $statutory = $epf['employee'] + $socso['employee'] + $eis['employee'] + $pcb;

        $payslip->fill([
            'company_id' => $run->company_id,
            'basic_salary' => $basic,
            'days_worked_ratio' => $ratio,
            'unpaid_leave_days' => $unpaidDays,
            'gross_pay' => Money::sen($earnings),
            'total_deductions' => Money::sen($otherDeductions + $statutory),
            'net_pay' => Money::sen($earnings - $otherDeductions - $statutory),
            'epf_wages' => $wages('epf'),
            'epf_employee' => $epf['employee'],
            'epf_employer' => $epf['employer'],
            'socso_employee' => $socso['employee'],
            'socso_employer' => $socso['employer'],
            'eis_employee' => $eis['employee'],
            'eis_employer' => $eis['employer'],
            'pcb' => $pcb,
            'pcb_taxable' => $pcbTaxable,
            'hrdf' => $hrdf,
            'employee_snapshot' => [
                'employee_no' => $employee->employee_no,
                'full_name' => $employee->full_name,
                'ic_no' => $employee->ic_no,
                'passport_no' => $employee->passport_no,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'join_date' => $employee->join_date->toDateString(),
                'bank_name' => $employee->bank_name,
                'bank_account_no' => $employee->bank_account_no,
                'epf_no' => $employee->epf_no,
                'socso_no' => $employee->socso_no,
                'tax_no' => $employee->tax_no,
            ],
        ])->save();

        $payslip->lines()->delete();
        foreach ($lines as $line) {
            $payslip->lines()->create($line);
        }

        return $payslip;
    }

    /**
     * How much to deduct from pay for unpaid leave taken in this payroll month.
     *
     * @param  float  $monthlyBasic  the employee's full (unprorated) monthly basic salary
     * @param  float  $unpaidDays  approved unpaid leave in this month, in working days (may be x.5)
     */
    protected function unpaidLeaveDeduction(float $monthlyBasic, float $unpaidDays, PayrollRun $run, Company $company): float
    {
        // TODO(human): return the amount to deduct (RM). See the request in the conversation.
        return 0.0;
    }

    /** Earlier finalized payslips in the same year plus any opening balance (for PCB accumulation). */
    private function yearToDate(Employee $employee, PayrollRun $run): array
    {
        $row = Payslip::withoutGlobalScope('company')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payslips.employee_id', $employee->id)
            ->where('payroll_runs.company_id', $run->company_id)
            ->where('payroll_runs.year', $run->year)
            ->where('payroll_runs.month', '<', $run->month)
            ->where('payroll_runs.status', 'finalized')
            ->selectRaw('COALESCE(SUM(pcb_taxable),0) as rem, COALESCE(SUM(epf_employee),0) as epf, COALESCE(SUM(pcb),0) as pcb')
            ->first();

        $opening = EmployeeYtdOpening::where(['employee_id' => $employee->id, 'year' => $run->year])->first();

        return [
            'remuneration' => (float) $row->rem + (float) ($opening->remuneration ?? 0),
            'epf' => (float) $row->epf + (float) ($opening->epf ?? 0),
            'pcb' => (float) $row->pcb + (float) ($opening->pcb ?? 0),
        ];
    }

    /** Add a one-off earning/deduction (bonus-free items, claims, loan repayments…) to a draft payslip. */
    public function addManualLine(Payslip $payslip, array $data): void
    {
        $this->assertDraft($payslip->run);

        $payslip->lines()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'is_manual' => true,
            'flags' => [
                'epf' => (bool) ($data['epf'] ?? false),
                'socso' => (bool) ($data['socso'] ?? false),
                'eis' => (bool) ($data['eis'] ?? false),
                'pcb' => (bool) ($data['pcb'] ?? false),
                'hrdf' => (bool) ($data['hrdf'] ?? false),
            ],
        ]);

        $this->calculatePayslip($payslip->run, $payslip->employee);
    }

    public function removeManualLine(PayslipLine $line): void
    {
        $payslip = $line->payslip;
        $this->assertDraft($payslip->run);
        abort_unless($line->is_manual, 422, 'Only manually added lines can be removed.');

        $line->delete();
        $this->calculatePayslip($payslip->run, $payslip->employee);
    }

    public function finalize(PayrollRun $run, User $by): void
    {
        $this->assertDraft($run);

        $earlierDraft = PayrollRun::where('company_id', $run->company_id)
            ->where('year', $run->year)->where('month', '<', $run->month)
            ->where('status', 'draft')->exists();
        if ($earlierDraft) {
            throw ValidationException::withMessages(['status' => 'Finalize earlier months of this year first (PCB uses their totals).']);
        }

        $this->calculate($run);
        $run->update(['status' => 'finalized', 'finalized_by' => $by->id, 'finalized_at' => now()]);
    }

    public function reopen(PayrollRun $run): void
    {
        if ($run->is_published) {
            throw ValidationException::withMessages(['status' => 'Unpublish the payslips before reopening this run.']);
        }
        $laterFinal = PayrollRun::where('company_id', $run->company_id)
            ->where('year', $run->year)->where('month', '>', $run->month)
            ->where('status', 'finalized')->exists();
        if ($laterFinal) {
            throw ValidationException::withMessages(['status' => 'A later month is already finalized; reopen that one first.']);
        }

        $run->update(['status' => 'draft', 'finalized_by' => null, 'finalized_at' => null]);
    }

    public function setPublished(PayrollRun $run, bool $published): void
    {
        if ($published && $run->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Finalize the run before publishing payslips.']);
        }
        $run->update(['is_published' => $published]);
    }

    private function assertDraft(PayrollRun $run): void
    {
        if (! $run->isDraft()) {
            throw ValidationException::withMessages(['status' => 'This payroll run is finalized. Reopen it to make changes.']);
        }
    }
}
