<?php

namespace Tests;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\CompanyDefaults;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** A company with Employment Act leave types and default pay items. */
    protected function makeCompany(string $name = 'Acme Sdn Bhd', array $attrs = []): Company
    {
        $company = Company::create(['name' => $name, 'work_days' => [1, 2, 3, 4, 5]] + $attrs);
        $this->useCompany($company);
        CompanyDefaults::apply($company);

        return $company;
    }

    /** Point the tenant scope at a company (for test setup outside HTTP requests). */
    protected function useCompany(Company $company, string $role = 'owner'): void
    {
        app(CurrentCompany::class)->set($company, $role);
    }

    protected function userIn(Company $company, string $role): User
    {
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    protected function employeeIn(Company $company, array $attrs = [], float $basic = 3000): Employee
    {
        $this->useCompany($company);
        $employee = Employee::create($attrs + [
            'employee_no' => 'E'.fake()->unique()->numerify('####'),
            'full_name' => fake()->name(),
            'citizenship' => 'citizen',
            'gender' => 'female',
            'date_of_birth' => '1990-05-05',
            'marital_status' => 'single',
            'join_date' => '2020-01-01',
        ]);
        $employee->salaries()->create(['effective_from' => $employee->join_date, 'basic_salary' => $basic]);

        return $employee;
    }
}
