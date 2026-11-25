<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Year-to-date totals from before this system, used for PCB. */
class EmployeeYtdOpening extends Model
{
    protected $fillable = ['employee_id', 'year', 'remuneration', 'epf', 'pcb'];

    protected function casts(): array
    {
        return ['remuneration' => 'decimal:2', 'epf' => 'decimal:2', 'pcb' => 'decimal:2'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
