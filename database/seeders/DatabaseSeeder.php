<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayItem;
use App\Models\Position;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEMO DATA ONLY — never run on production (`php artisan db:seed` refuses there).
 * Logins (password "password"):
 *   admin@serinstech.test     super admin / owner of both demo companies
 *   hr@serinstech.test        HR
 *   manager@serinstech.test   manager (approves Siti's leave)
 *   employee@serinstech.test  employee self service (Siti)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Demo seeder refused on production. Use: php artisan hrx:install');

            return;
        }

        $admin = User::create(['name' => 'Demo Admin', 'email' => 'admin@serinstech.test', 'password' => 'password']);
        $admin->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $main = $this->company('Serinstech Sdn Bhd', '202301012345 (1501234-X)', $admin);
        $this->company('Serinstech Training Sdn Bhd', '202401054321 (1555555-K)', $admin);

        // Fill the main company
        app(CurrentCompany::class)->set($main, 'owner');
        $ops = Department::create(['name' => 'Operations']);
        $fin = Department::create(['name' => 'Finance & HR']);
        $tech = Position::create(['name' => 'Technician']);
        $lead = Position::create(['name' => 'Operations Lead']);
        $exec = Position::create(['name' => 'HR Executive']);

        foreach ([['2026-01-01', 'New Year'], ['2026-03-21', 'Hari Raya Aidilfitri'], ['2026-03-22', 'Hari Raya Aidilfitri (2nd day)'],
                  ['2026-05-01', 'Labour Day'], ['2026-08-31', 'National Day'], ['2026-09-16', 'Malaysia Day'],
                  ['2026-12-25', 'Christmas Day']] as [$d, $n]) {
            Holiday::create(['date' => $d, 'name' => $n]);
        }

        $hrUser = $this->user('Nurul Huda', 'hr@serinstech.test', $main, 'hr');
        $mgrUser = $this->user('Raj Kumar', 'manager@serinstech.test', $main, 'manager');
        $empUser = $this->user('Siti Aminah', 'employee@serinstech.test', $main, 'employee');

        $raj = $this->employee('E001', 'Raj Kumar a/l Muthu', $mgrUser, $ops, $lead, 'male', '1985-04-12', 'married', false, 2, '2019-03-01', 7800);
        $this->employee('E002', 'Nurul Huda binti Ismail', $hrUser, $fin, $exec, 'female', '1992-09-30', 'single', true, 0, '2022-06-15', 5200);
        $siti = $this->employee('E003', 'Siti Aminah binti Yusof', $empUser, $ops, $tech, 'female', '1996-01-20', 'married', true, 1, '2024-02-01', 3400, $raj);
        $this->employee('E004', 'Lim Wei Jie', null, $ops, $tech, 'male', '1999-11-05', 'single', true, 0, '2025-08-18', 2900, $raj);
        $this->employee('E005', 'Budi Santoso', null, $ops, $tech, 'male', '1990-07-07', 'married', true, 0, '2026-10-12', 2200, $raj, 'foreigner');

        $transport = PayItem::where('name', 'Transport allowance')->first();
        $siti->payItems()->create(['pay_item_id' => $transport->id, 'amount' => 200, 'effective_from' => '2024-02-01']);
        $raj->payItems()->create(['pay_item_id' => PayItem::where('name', 'Fixed allowance')->first()->id, 'amount' => 500, 'effective_from' => '2019-03-01']);
    }

    private function company(string $name, string $reg, User $owner): Company
    {
        $company = Company::create([
            'name' => $name, 'registration_no' => $reg, 'work_days' => [1, 2, 3, 4, 5], 'hrdf_rate' => 1,
            'address' => "Level 5, Menara Contoh\nJalan Contoh, 50450 Kuala Lumpur", 'email' => 'hr@serinstech.test',
        ]);
        $company->users()->attach($owner->id, ['role' => 'owner']);
        app(CurrentCompany::class)->set($company, 'owner');
        CompanyDefaults::apply($company);

        return $company;
    }

    private function user(string $name, string $email, Company $company, string $role): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'current_company_id' => $company->id])->save();
        $company->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    private function employee(string $no, string $name, ?User $user, $dept, $pos, string $gender, string $dob, string $marital,
        bool $spouseWorking, int $children, string $joined, float $basic, ?Employee $manager = null, string $citizenship = 'citizen'): Employee
    {
        $e = Employee::create([
            'employee_no' => $no, 'full_name' => $name, 'user_id' => $user?->id, 'department_id' => $dept->id,
            'position_id' => $pos->id, 'gender' => $gender, 'date_of_birth' => $dob, 'marital_status' => $marital,
            'spouse_working' => $spouseWorking, 'children_count' => $children, 'join_date' => $joined,
            'manager_id' => $manager?->id, 'citizenship' => $citizenship, 'email' => $user?->email,
            'ic_no' => $citizenship === 'citizen' ? substr(str_replace('-', '', $dob), 2).'-14-'.rand(1000, 9999) : null,
            'passport_no' => $citizenship === 'foreigner' ? 'B'.rand(1000000, 9999999) : null,
            'bank_name' => 'Maybank', 'bank_account_no' => (string) rand(100000000000, 999999999999),
            'epf_no' => (string) rand(10000000, 99999999),
        ]);
        $e->salaries()->create(['effective_from' => $joined, 'basic_salary' => $basic, 'reason' => 'New hire']);

        return $e;
    }
}
