<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    protected $fillable = ['employee_id', 'effective_from', 'basic_salary', 'reason'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'basic_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
