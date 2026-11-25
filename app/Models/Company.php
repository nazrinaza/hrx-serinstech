<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public const ROLES = ['owner', 'admin', 'hr', 'manager', 'employee'];

    protected $fillable = [
        'name', 'registration_no', 'address', 'phone', 'email', 'logo_path',
        'epf_employer_no', 'socso_employer_no', 'tax_employer_no', 'hrdf_rate',
        'work_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'hrdf_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** ISO weekdays that are working days; Mon–Fri by default. */
    public function workDays(): array
    {
        return $this->work_days ?: [1, 2, 3, 4, 5];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
