<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use BelongsToCompany;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'days', 'reason',
        'attachment_path', 'status', 'approver_id', 'decided_by', 'decided_at', 'decision_note',
        'from_self_service',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
            'decided_at' => 'datetime',
            'from_self_service' => 'boolean',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }

    public function isPending(): bool { return $this->status === self::PENDING; }
}
