<?php

namespace App\Http\Controllers;

use App\Leave\LeaveService;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $requests = LeaveRequest::with('employee', 'leaveType', 'approver')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('end_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->to))
            ->latest('start_date')
            ->paginate(25)->withQueryString();

        return view('leave.index', [
            'requests' => $requests,
            'status' => $status,
            'employees' => Employee::active()->orderBy('full_name')->get(),
            'types' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** HR applies leave on behalf of an employee. */
    public function store(Request $request, LeaveService $leave)
    {
        $data = $this->validated($request);
        $employee = Employee::findOrFail($data['employee_id']);
        $data['attachment_path'] = $request->file('attachment')?->store('leave-attachments');

        $leave->submit($employee, $data, fromSelfService: false);

        return back()->with('status', "Leave request created for {$employee->full_name}.");
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveService $leave)
    {
        $leave->cancel($leaveRequest, $request->user());

        return back()->with('status', 'Leave cancelled.');
    }

    public function balances(Request $request, LeaveService $leave)
    {
        $year = (int) $request->input('year', now()->year);
        $types = LeaveType::where('is_active', true)->where('is_paid', true)->orderBy('name')->get();
        $employees = Employee::active()->orderBy('full_name')->get();

        $rows = $employees->map(fn ($e) => [
            'employee' => $e,
            'balances' => $types->mapWithKeys(fn ($t) => [
                $t->id => $t->appliesTo($e) ? $leave->balance($e, $t, $year) : null,
            ]),
        ]);

        return view('leave.balances', compact('year', 'types', 'rows'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'required|integer',
            'leave_type_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'half_day' => 'nullable|in:am,pm',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);
    }
}
