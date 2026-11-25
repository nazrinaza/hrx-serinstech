<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation: every query on a model using this trait is filtered to the
 * current company, and new rows are stamped with it automatically.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $companyId = app(CurrentCompany::class)->id();
            if ($companyId !== null) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $model->company_id = app(CurrentCompany::class)->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
