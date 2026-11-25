<?php

namespace App\Leave;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequested;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /** Employment Act 1955 minimums by completed years of service: [<2, 2–<5, ≥5]. */
    private const EA_TIERS = [
        'annual_ea' => [8, 12, 16],
        'sick_ea' => [14, 18, 22],
    ];

    /** Working days between two dates, skipping rest days and company holidays. */
    public function workingDays(Company $company, CarbonInterface $start, CarbonInterface $end): float
    {
        $holidays = Holiday::where('company_id', $company->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')->map->toDateString()->all();

        $days = 0;
        for ($d = CarbonImmutable::parse($start); $d->lte($end); $d = $d->addDay()) {
            if (in_array($d->isoWeekday(), $company->workDays(), true) && ! in_array($d->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return (float) $days;
    }

    public function requestDays(Company $company, CarbonInterface $start, CarbonInterface $end, ?string $halfDay): float
    {
        $days = $this->workingDays($company, $start, $end);

        return ($halfDay && $start->isSameDay($end) && $days > 0) ? 0.5 : $days;
    }

    /** Statutory/default days for a leave type, given years of service. */
    public function defaultDays(LeaveType $type, Employee $employee, int $year): float
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $service = $employee->join_date->lt($yearStart) ? (int) $employee->join_date->diffInYears($yearStart) : 0;

        if (isset(self::EA_TIERS[$type->entitlement_rule])) {
            $tiers = self::EA_TIERS[$type->entitlement_rule];
            $days = max((float) $type->default_days, (float) $tiers[$service >= 5 ? 2 : ($service >= 2 ? 1 : 0)]);
        } else {
            $days = (float) $type->default_days;
        }

        // Annual leave is prorated in the year the employee joins (rounded down to half days).
        if ($type->entitlement_rule === 'annual_ea' && $employee->join_date->year === $year) {
            $monthsLeft = 12 - $employee->join_date->month + 1;
            $days = floor($days * $monthsLeft / 12 * 2) / 2;
        }

        return $days;
    }

    /** Get (or generate) the entitlement row for an employee, leave type and year. */
    public function entitlement(Employee $employee, LeaveType $type, int $year): LeaveEntitlement
    {
        $existing = LeaveEntitlement::where([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year,
        ])->first();
        if ($existing) {
            return $existing;
        }

        $carried = 0.0;
        if ($type->max_carry_forward > 0) {
            $prev = LeaveEntitlement::where([
                'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year - 1,
            ])->first();
            if ($prev) {
                $carried = min((float) $type->max_carry_forward, max(0, $this->balance($employee, $type, $year - 1)['balance']));
            }
        }

        return LeaveEntitlement::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'days' => $this->defaultDays($type, $employee, $year),
            'carried_forward' => $carried,
        ]);
    }

    /** @return array{entitled: float, taken: float, pending: float, balance: float} */
    public function balance(Employee $employee, LeaveType $type, int $year): array
    {
        $entitled = $this->entitlement($employee, $type, $year)->total();

        $sums = LeaveRequest::withoutGlobalScope('company')
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereYear('start_date', $year)
            ->whereIn('status', [LeaveRequest::APPROVED, LeaveRequest::PENDING])
            ->selectRaw('status, SUM(days) as total')->groupBy('status')
            ->pluck('total', 'status');

        $taken = (float) ($sums[LeaveRequest::APPROVED] ?? 0);
        $pending = (float) ($sums[LeaveRequest::PENDING] ?? 0);

        return [
            'entitled' => $entitled,
            'taken' => $taken,
            'pending' => $pending,
            'balance' => $entitled - $taken - $pending,
        ];
    }

    /** Line manager's login, falling back to the company's HR/admin users. */
    public function approverFor(Employee $employee): ?User
    {
        if ($employee->manager?->user) {
            return $employee->manager->user;
        }

        // Prefer HR, then admin, then owner — and never the requester themself if someone else exists.
        $candidates = $employee->company->users()
            ->wherePivotIn('role', User::HR_ROLES)
            ->orderByRaw("CASE company_user.role WHEN 'hr' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
            ->get();

        return $candidates->firstWhere(fn ($u) => $u->id !== $employee->user_id) ?? $candidates->first();
    }

    /**
     * @param  array{leave_type_id:int,start_date:string,end_date:string,half_day:?string,reason:?string,attachment_path:?string}  $data
     */
    public function submit(Employee $employee, array $data, bool $fromSelfService): LeaveRequest
    {
        $type = LeaveType::findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        $errors = [];
        if ($end->lt($start)) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        } elseif ($start->year !== $end->year) {
            $errors['end_date'] = 'Split leave that crosses the new year into two requests.';
        }
        if (! $type->appliesTo($employee)) {
            $errors['leave_type_id'] = 'This leave type is not available to this employee.';
        }
        if ($type->requires_attachment && empty($data['attachment_path'])) {
            $errors['attachment'] = 'This leave type needs a supporting document.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $days = $this->requestDays($employee->company, $start, $end, $data['half_day'] ?? null);
        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'The selected dates are all rest days or holidays.']);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::APPROVED])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap another leave request.']);
        }

        if ($type->is_paid) {
            $balance = $this->balance($employee, $type, $start->year)['balance'];
            if ($days > $balance) {
                throw ValidationException::withMessages([
                    'end_date' => "Not enough balance: requesting {$days} day(s), {$balance} available.",
                ]);
            }
        }

        $approver = $this->approverFor($employee);

        $request = LeaveRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'half_day' => ($start->isSameDay($end) ? ($data['half_day'] ?? null) : null) ?: null,
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'approver_id' => $approver?->id,
            'from_self_service' => $fromSelfService,
            'status' => LeaveRequest::PENDING,
        ]);

        $approver?->notify(new LeaveRequested($request));

        return $request;
    }

    public function decide(LeaveRequest $request, User $by, bool $approve, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request has already been processed.']);
        }

        DB::transaction(fn () => $request->update([
            'status' => $approve ? LeaveRequest::APPROVED : LeaveRequest::REJECTED,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]));

        $request->employee->user?->notify(new LeaveDecided($request));
    }

    public function cancel(LeaveRequest $request, User $by): void
    {
        if (! in_array($request->status, [LeaveRequest::PENDING, LeaveRequest::APPROVED], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending or approved leave can be cancelled.']);
        }

        $request->update([
            'status' => LeaveRequest::CANCELLED,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * Unpaid leave days that fall inside a date range (for payroll).
     */
    public function unpaidDaysBetween(Employee $employee, CarbonInterface $from, CarbonInterface $to): float
    {
        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', LeaveRequest::APPROVED)
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->get();

        $total = 0.0;
        foreach ($requests as $r) {
            if ($r->half_day) {
                $total += 0.5;
                continue;
            }
            $s = $r->start_date->max($from);
            $e = $r->end_date->min($to);
            $total += $this->workingDays($employee->company, $s, $e);
        }

        return $total;
    }
}
