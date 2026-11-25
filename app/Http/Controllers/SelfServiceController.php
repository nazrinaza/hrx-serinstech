<?php

namespace App\Http\Controllers;

use App\Leave\LeaveService;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Payroll\PayslipPdf;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    public function profile(Request $request, CurrentCompany $current)
    {
        return view('me.profile', ['employee' => $this->me($request, $current)?->load('department', 'position', 'manager')]);
    }

    public function leave(Request $request, CurrentCompany $current, LeaveService $leave)
    {
        $employee = $this->me($request, $current);
        $year = (int) $request->input('year', now()->year);

        $types = $employee
            ? LeaveType::where('is_active', true)->orderBy('name')->get()->filter(fn ($t) => $t->appliesTo($employee))
            : collect();

        return view('me.leave', [
            'employee' => $employee,
            'year' => $year,
            'types' => $types,
            'balances' => $types->where('is_paid', true)->map(fn ($t) => ['type' => $t] + $leave->balance($employee, $t, $year)),
            'history' => $employee?->leaveRequests()->with('leaveType', 'approver')->latest('start_date')->limit(30)->get() ?? collect(),
            'approver' => $employee ? $leave->approverFor($employee) : null,
        ]);
    }

    public function storeLeave(Request $request, CurrentCompany $current, LeaveService $leave)
    {
        $employee = $this->me($request, $current);
        abort_unless($employee, 403, 'Your login is not linked to an employee record.');

        $data = $request->validate([
            'leave_type_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'half_day' => 'nullable|in:am,pm',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);
        $data['attachment_path'] = $request->file('attachment')?->store('leave-attachments');

        $leave->submit($employee, $data, fromSelfService: true);

        return back()->with('status', 'Leave request submitted for approval.');
    }

    public function cancelLeave(Request $request, LeaveRequest $leaveRequest, CurrentCompany $current, LeaveService $leave)
    {
        $employee = $this->me($request, $current);
        abort_unless($employee && $leaveRequest->employee_id === $employee->id, 403);
        // Employees may withdraw pending requests, or approved leave that has not started yet.
        abort_if($leaveRequest->status === LeaveRequest::APPROVED && $leaveRequest->start_date->lte(today()), 403,
            'Leave that has already started can only be cancelled by HR.');

        $leave->cancel($leaveRequest, $request->user());

        return back()->with('status', 'Leave request cancelled.');
    }

    public function payslips(Request $request, CurrentCompany $current)
    {
        $employee = $this->me($request, $current);

        $payslips = $employee
            ? Payslip::with('run')->where('employee_id', $employee->id)
                ->whereHas('run', fn ($q) => $q->where('is_published', true))
                ->get()->sortByDesc(fn ($p) => $p->run->year * 100 + $p->run->month)
            : collect();

        return view('me.payslips', compact('employee', 'payslips'));
    }

    public function payslipPdf(Request $request, Payslip $payslip, CurrentCompany $current)
    {
        $employee = $this->me($request, $current);
        abort_unless($employee && $payslip->employee_id === $employee->id && $payslip->run->is_published, 404);

        return PayslipPdf::download($payslip);
    }

    private function me(Request $request, CurrentCompany $current): ?Employee
    {
        return $request->user()->employeeIn($current->get());
    }
}
