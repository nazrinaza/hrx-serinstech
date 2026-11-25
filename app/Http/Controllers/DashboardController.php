<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $current)
    {
        $user = $request->user();
        $company = $current->get();
        $isHr = $current->roleIs(\App\Models\User::HR_ROLES);

        $today = today();
        $stats = null;
        if ($isHr) {
            $stats = [
                'headcount' => Employee::active()->count(),
                'joiners' => Employee::whereYear('join_date', $today->year)->whereMonth('join_date', $today->month)->count(),
                'onLeaveToday' => LeaveRequest::where('status', 'approved')
                    ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
                'pendingLeave' => LeaveRequest::where('status', 'pending')->count(),
                'lastRun' => PayrollRun::orderByDesc('year')->orderByDesc('month')->withSum('payslips', 'net_pay')->first(),
            ];
        }

        $myEmployee = $user->employeeIn($company);
        $birthdays = Employee::active()->whereNotNull('date_of_birth')->get()
            ->filter(fn ($e) => $e->date_of_birth->month === $today->month)
            ->sortBy(fn ($e) => $e->date_of_birth->day);
        $onLeave = LeaveRequest::with('employee', 'leaveType')->where('status', 'approved')
            ->whereDate('start_date', '<=', $today->copy()->addDays(7))->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')->limit(10)->get();
        $myPending = LeaveRequest::where('approver_id', $user->id)->where('status', 'pending')->count();

        return view('dashboard', compact('stats', 'myEmployee', 'birthdays', 'onLeave', 'myPending', 'isHr'));
    }
}
