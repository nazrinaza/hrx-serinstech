<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = array_fill_keys([
            'basic_salary', 'gross_pay', 'total_deductions', 'net_pay', 'epf_wages', 'epf_employee',
            'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer', 'pcb',
            'pcb_taxable', 'hrdf',
        ], 'decimal:2');

        return $money + [
            'days_worked_ratio' => 'decimal:4',
            'unpaid_leave_days' => 'decimal:1',
            'employee_snapshot' => 'array',
        ];
    }

    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function lines(): HasMany { return $this->hasMany(PayslipLine::class); }
}
