<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayItem extends Model
{
    protected $fillable = ['employee_id', 'pay_item_id', 'amount', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function payItem(): BelongsTo { return $this->belongsTo(PayItem::class); }

    public function scopeActiveOn(Builder $q, CarbonInterface $date): Builder
    {
        return $q->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
