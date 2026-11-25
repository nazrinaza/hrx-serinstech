<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'code', 'is_paid', 'default_days', 'entitlement_rule', 'gender',
        'max_carry_forward', 'requires_attachment', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'default_days' => 'decimal:1',
            'max_carry_forward' => 'decimal:1',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function appliesTo(Employee $employee): bool
    {
        return $this->gender === null || $this->gender === $employee->gender;
    }
}
