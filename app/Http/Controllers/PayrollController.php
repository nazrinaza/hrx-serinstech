<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Payroll\PayrollService;
use App\Payroll\PayslipPdf;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function index()
    {
        $runs = PayrollRun::withCount('payslips')
            ->withSum('payslips', 'gross_pay')->withSum('payslips', 'net_pay')
            ->orderByDesc('year')->orderByDesc('month')->paginate(24);

        $next = PayrollRun::orderByDesc('year')->orderByDesc('month')->first()?->periodStart()->addMonth() ?? now()->startOfMonth();

        return view('payroll.index', compact('runs', 'next'));
    }

    public function store(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'period' => 'required|date_format:Y-m',
            'pay_date' => 'required|date',
        ]);
        [$year, $month] = array_map('intval', explode('-', $data['period']));

        $run = $this->payroll->createRun($current->get(), $year, $month, $data['pay_date']);

        return redirect()->route('payroll.show', $run)->with('status', 'Payroll calculated for '.$run->label().'. Review it, then finalize.');
    }

    public function show(PayrollRun $run)
    {
        $payslips = $run->payslips()->with('employee')->get()->sortBy('employee_snapshot.full_name');

        $totals = collect(['gross_pay', 'total_deductions', 'net_pay', 'epf_employee', 'epf_employer',
            'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer', 'pcb', 'hrdf'])
            ->mapWithKeys(fn ($f) => [$f => $payslips->sum($f)]);

        return view('payroll.show', compact('run', 'payslips', 'totals'));
    }

    public function recalculate(PayrollRun $run)
    {
        $this->payroll->calculate($run);

        return back()->with('status', 'Payroll recalculated with the latest employee data.');
    }

    public function finalize(Request $request, PayrollRun $run)
    {
        $this->payroll->finalize($run, $request->user());

        return back()->with('status', 'Payroll finalized. Download the bank file and publish payslips when ready.');
    }

    public function reopen(PayrollRun $run)
    {
        $this->payroll->reopen($run);

        return back()->with('status', 'Payroll reopened for changes.');
    }

    public function publish(Request $request, PayrollRun $run)
    {
        $publish = $request->boolean('publish');
        $this->payroll->setPublished($run, $publish);

        return back()->with('status', $publish ? 'Payslips are now visible to employees.' : 'Payslips hidden from employees.');
    }

    public function destroy(PayrollRun $run)
    {
        abort_unless($run->isDraft(), 422, 'Only draft runs can be deleted.');
        $run->delete();

        return redirect()->route('payroll.index')->with('status', 'Draft payroll deleted.');
    }

    public function payslip(Payslip $payslip)
    {
        $payslip->load('run', 'lines', 'employee');

        return view('payroll.payslip', compact('payslip'));
    }

    public function payslipPdf(Payslip $payslip)
    {
        return PayslipPdf::download($payslip);
    }

    public function addLine(Request $request, Payslip $payslip)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'epf' => 'boolean', 'socso' => 'boolean', 'eis' => 'boolean', 'pcb' => 'boolean', 'hrdf' => 'boolean',
        ]);
        $this->payroll->addManualLine($payslip, $data);

        return back()->with('status', 'Line added and payslip recalculated.');
    }

    public function removeLine(PayslipLine $line)
    {
        // PayslipLine is not tenant-scoped itself: resolve its payslip through the scoped model.
        Payslip::findOrFail($line->payslip_id);
        $this->payroll->removeManualLine($line);

        return back()->with('status', 'Line removed and payslip recalculated.');
    }

    /** Generic bank payment file (CSV). Bank-specific formats can be added per bank. */
    public function bankFile(PayrollRun $run)
    {
        abort_if($run->isDraft(), 422, 'Finalize the payroll before downloading the bank file.');

        $rows = $run->payslips()->get()->sortBy('employee_snapshot.full_name')->map(fn ($p) => [
            $p->employee_snapshot['employee_no'], $p->employee_snapshot['full_name'],
            $p->employee_snapshot['ic_no'] ?? $p->employee_snapshot['passport_no'],
            $p->employee_snapshot['bank_name'], $p->employee_snapshot['bank_account_no'],
            number_format((float) $p->net_pay, 2, '.', ''),
        ]);

        return $this->csv(
            sprintf('bank-payment-%04d-%02d.csv', $run->year, $run->month),
            ['Employee No', 'Name', 'IC / Passport', 'Bank', 'Account No', 'Net Pay'],
            $rows,
        );
    }

    /** EPF / SOCSO / EIS / PCB / HRDF contribution summary for the month. */
    public function statutoryReport(PayrollRun $run)
    {
        $rows = $run->payslips()->get()->sortBy('employee_snapshot.full_name')->map(fn ($p) => [
            $p->employee_snapshot['employee_no'], $p->employee_snapshot['full_name'], $p->employee_snapshot['ic_no'],
            $p->employee_snapshot['epf_no'], $p->employee_snapshot['socso_no'], $p->employee_snapshot['tax_no'],
            $p->epf_wages, $p->epf_employee, $p->epf_employer, $p->socso_employee, $p->socso_employer,
            $p->eis_employee, $p->eis_employer, $p->pcb, $p->hrdf,
        ]);

        return $this->csv(
            sprintf('statutory-%04d-%02d.csv', $run->year, $run->month),
            ['Employee No', 'Name', 'IC No', 'EPF No', 'SOCSO No', 'Tax No', 'EPF Wages', 'EPF Employee',
                'EPF Employer', 'SOCSO Employee', 'SOCSO Employer', 'EIS Employee', 'EIS Employer', 'PCB', 'HRDF'],
            $rows,
        );
    }

    private function csv(string $filename, array $header, $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
