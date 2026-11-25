<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    protected $fillable = ['payslip_id', 'pay_item_id', 'name', 'type', 'amount', 'is_manual', 'flags'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_manual' => 'boolean', 'flags' => 'array'];
    }

    public function payslip(): BelongsTo { return $this->belongsTo(Payslip::class); }
}
