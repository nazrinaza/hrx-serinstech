<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlement extends Model
{
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'days', 'carried_forward', 'adjustment'];

    protected function casts(): array
    {
        return ['days' => 'decimal:1', 'carried_forward' => 'decimal:1', 'adjustment' => 'decimal:1'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }

    public function total(): float
    {
        return (float) $this->days + (float) $this->carried_forward + (float) $this->adjustment;
    }
}
