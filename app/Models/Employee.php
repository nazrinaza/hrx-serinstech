<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'employee_no', 'full_name', 'ic_no', 'passport_no', 'citizenship', 'gender',
        'date_of_birth', 'marital_status', 'email', 'phone', 'address', 'department_id',
        'position_id', 'manager_id', 'employment_type', 'join_date', 'resign_date', 'bank_name',
        'bank_account_no', 'epf_no', 'socso_no', 'tax_no', 'epf_employee_rate', 'is_tax_resident',
        'spouse_working', 'children_count', 'is_disabled', 'spouse_disabled',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
            'epf_employee_rate' => 'decimal:2',
            'is_tax_resident' => 'boolean',
            'spouse_working' => 'boolean',
            'is_disabled' => 'boolean',
            'spouse_disabled' => 'boolean',
            'children_count' => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function reports(): HasMany { return $this->hasMany(Employee::class, 'manager_id'); }
    public function salaries(): HasMany { return $this->hasMany(EmployeeSalary::class)->orderByDesc('effective_from'); }
    public function payItems(): HasMany { return $this->hasMany(EmployeePayItem::class); }
    public function leaveEntitlements(): HasMany { return $this->hasMany(LeaveEntitlement::class); }
    public function leaveRequests(): HasMany { return $this->hasMany(LeaveRequest::class); }
    public function payslips(): HasMany { return $this->hasMany(Payslip::class); }
    public function ytdOpenings(): HasMany { return $this->hasMany(EmployeeYtdOpening::class); }

    /** Employed at any point between the two dates. */
    public function scopeEmployedBetween(Builder $q, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $q->whereDate('join_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('resign_date')->orWhereDate('resign_date', '>=', $from));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('resign_date')->orWhereDate('resign_date', '>=', today()));
    }

    public function isActive(): bool
    {
        return $this->resign_date === null || $this->resign_date->gte(today());
    }

    public function salaryOn(CarbonInterface $date): ?EmployeeSalary
    {
        return $this->salaries()->whereDate('effective_from', '<=', $date)->first();
    }

    public function ageOn(CarbonInterface $date): ?int
    {
        return $this->date_of_birth ? (int) floor($this->date_of_birth->diffInYears($date)) : null;
    }

    /** Malaysian citizens and permanent residents are treated as "local" for EPF/SOCSO/EIS/HRDF. */
    public function isLocal(): bool
    {
        return in_array($this->citizenship, ['citizen', 'pr'], true);
    }
}
