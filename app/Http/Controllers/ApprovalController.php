<?php

namespace App\Http\Controllers;

use App\Leave\LeaveService;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApprovalController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $isHr = $current->roleIs(User::HR_ROLES);
        $pending = LeaveRequest::with('employee', 'leaveType', 'approver')
            ->where('status', LeaveRequest::PENDING)
            ->when(! $isHr, fn ($q) => $q->where('approver_id', $request->user()->id))
            ->orderBy('start_date')
            ->get();

        return view('approvals.index', compact('pending', 'isHr'));
    }

    public function decide(Request $request, LeaveRequest $leaveRequest, LeaveService $leave, CurrentCompany $current)
    {
        $user = $request->user();
        $this->authorizeApprover($request, $leaveRequest, $current);
        abort_if($leaveRequest->employee->user_id === $user->id, 403, 'You cannot approve your own leave.');

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000|required_if:decision,reject',
        ], ['note.required_if' => 'Please give a reason when rejecting.']);

        $leave->decide($leaveRequest, $user, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('status', 'Leave '.($data['decision'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    public function attachment(Request $request, LeaveRequest $leaveRequest, CurrentCompany $current)
    {
        $isOwner = $leaveRequest->employee->user_id === $request->user()->id;
        if (! $isOwner) {
            $this->authorizeApprover($request, $leaveRequest, $current);
        }
        abort_unless($leaveRequest->attachment_path && Storage::exists($leaveRequest->attachment_path), 404);

        return Storage::download($leaveRequest->attachment_path);
    }

    private function authorizeApprover(Request $request, LeaveRequest $leaveRequest, CurrentCompany $current): void
    {
        abort_unless(
            $leaveRequest->approver_id === $request->user()->id || $current->roleIs(User::HR_ROLES),
            403,
        );
    }
}
