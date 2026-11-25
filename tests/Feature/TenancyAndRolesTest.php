<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_cannot_open_another_companys_employee(): void
    {
        $a = $this->makeCompany('Company A');
        $b = $this->makeCompany('Company B');
        $foreign = $this->employeeIn($b);
        $hr = $this->userIn($a, 'hr');

        $this->actingAs($hr)->get("/employees/{$foreign->id}")->assertNotFound();
        $this->actingAs($hr)->get('/employees')->assertOk()->assertDontSee($foreign->full_name);
    }

    public function test_cannot_switch_to_a_company_you_do_not_belong_to(): void
    {
        $a = $this->makeCompany('Company A');
        $b = $this->makeCompany('Company B');
        $hr = $this->userIn($a, 'hr');

        $this->actingAs($hr)->post('/company/switch', ['company_id' => $b->id])->assertForbidden();
    }

    public function test_user_in_two_companies_sees_only_the_current_one(): void
    {
        $a = $this->makeCompany('Company A');
        $b = $this->makeCompany('Company B');
        $inA = $this->employeeIn($a, ['full_name' => 'Alice In A']);
        $inB = $this->employeeIn($b, ['full_name' => 'Bob In B']);
        $user = $this->userIn($a, 'hr');
        $b->users()->attach($user->id, ['role' => 'hr']);

        $this->actingAs($user)->post('/company/switch', ['company_id' => $b->id])->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/employees')->assertSee('Bob In B')->assertDontSee('Alice In A');
    }

    public function test_new_records_are_stamped_with_the_current_company(): void
    {
        $a = $this->makeCompany('Company A');
        $hr = $this->userIn($a, 'hr');

        $this->actingAs($hr)->post('/setup/departments', ['name' => 'Logistics'])->assertRedirect();
        $this->assertDatabaseHas('departments', ['name' => 'Logistics', 'company_id' => $a->id]);
    }

    public function test_employee_role_cannot_reach_hr_or_payroll_pages(): void
    {
        $a = $this->makeCompany();
        $employee = $this->userIn($a, 'employee');

        foreach (['/employees', '/payroll', '/setup/departments', '/users', '/company'] as $url) {
            $this->actingAs($employee)->get($url)->assertForbidden();
        }
        $this->actingAs($employee)->get('/me/leave')->assertOk();
    }

    public function test_admin_cannot_demote_an_owner(): void
    {
        $a = $this->makeCompany();
        $owner = $this->userIn($a, 'owner');
        $admin = $this->userIn($a, 'admin');

        $this->actingAs($admin)->put("/users/{$owner->id}", ['role' => 'employee'])->assertForbidden();
    }

    public function test_user_without_company_is_blocked(): void
    {
        $this->makeCompany();
        $loner = \App\Models\User::factory()->create();

        $this->actingAs($loner)->get('/dashboard')->assertForbidden();
    }
}
