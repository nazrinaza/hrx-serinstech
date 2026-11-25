<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'year', 'month', 'pay_date', 'status', 'is_published', 'finalized_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['pay_date' => 'date', 'is_published' => 'boolean', 'finalized_at' => 'datetime'];
    }

    public function payslips(): HasMany { return $this->hasMany(Payslip::class); }

    public function isDraft(): bool { return $this->status === 'draft'; }

    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
    }

    public function periodEnd(): CarbonImmutable
    {
        return $this->periodStart()->endOfMonth()->startOfDay();
    }

    public function label(): string
    {
        return $this->periodStart()->format('F Y');
    }
}
