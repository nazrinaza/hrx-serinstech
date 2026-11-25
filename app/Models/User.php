<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'current_company_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Roles allowed to run HR admin screens (employees, leave admin, setup). */
    public const HR_ROLES = ['owner', 'admin', 'hr'];

    /** Roles allowed to run payroll. */
    public const PAYROLL_ROLES = ['owner', 'admin', 'hr'];

    /** Roles allowed to manage company settings and users. */
    public const ADMIN_ROLES = ['owner', 'admin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /** Employee records (one per company the user works in). Not company-scoped on purpose. */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class)->withoutGlobalScope('company');
    }

    /** Companies this user can open. Super admins can open all of them. */
    public function accessibleCompanies()
    {
        return $this->is_super_admin
            ? Company::orderBy('name')->get()
            : $this->companies()->orderBy('name')->get();
    }

    public function roleIn(?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }
        if ($this->is_super_admin) {
            return 'owner';
        }

        return $this->companies()->whereKey($company->id)->first()?->pivot->role;
    }

    public function hasRoleIn(?Company $company, array $roles): bool
    {
        return in_array($this->roleIn($company), $roles, true);
    }

    public function employeeIn(?Company $company): ?Employee
    {
        return $company ? $this->employees()->where('company_id', $company->id)->first() : null;
    }
}
