<?php

namespace App\Support;

use App\Models\Company;

/**
 * The company (tenant) the signed-in user is working in for this request,
 * plus their role in it. Bound as a singleton; set by EnsureCurrentCompany.
 */
class CurrentCompany
{
    private ?Company $company = null;

    private ?string $role = null;

    public function set(?Company $company, ?string $role = null): void
    {
        $this->company = $company;
        $this->role = $role;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    /** @param  string[]  $roles */
    public function roleIs(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
