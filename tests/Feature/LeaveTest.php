<?php

namespace Tests\Feature;

use App\Leave\LeaveService;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequested;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_days_skip_weekends_and_holidays(): void
    {
        $company = $this->makeCompany();
        Holiday::create(['date' => '2026-12-25', 'name' => 'Christmas']);

        // Mon 21 – Sun 27 Dec 2026: 5 weekdays, minus Christmas (Fri) = 4
        $days = app(LeaveService::class)->workingDays($company, CarbonImmutable::parse('2026-12-21'), CarbonImmutable::parse('2026-12-27'));
        $this->assertSame(4.0, $days);
    }

    public function test_annual_leave_follows_employment_act_tiers_and_prorates_joiners(): void
    {
        $company = $this->makeCompany();
        $al = LeaveType::where('code', 'AL')->first();
        $svc = app(LeaveService::class);

        $this->assertSame(8.0, $svc->defaultDays($al, $this->employeeIn($company, ['join_date' => '2025-01-01']), 2026));
        $this->assertSame(12.0, $svc->defaultDays($al, $this->employeeIn($company, ['join_date' => '2023-06-01']), 2026));
        $this->assertSame(16.0, $svc->defaultDays($al, $this->employeeIn($company, ['join_date' => '2018-01-01']), 2026));
        // Joined 1 July: 6 of 12 months → 4 days
        $this->assertSame(4.0, $svc->defaultDays($al, $this->employeeIn($company, ['join_date' => '2026-07-01']), 2026));
    }

    public function test_self_service_request_goes_to_manager_who_approves(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $mgrUser = $this->userIn($company, 'manager');
        $manager = $this->employeeIn($company, ['user_id' => $mgrUser->id]);
        $empUser = $this->userIn($company, 'employee');
        $employee = $this->employeeIn($company, ['user_id' => $empUser->id, 'manager_id' => $manager->id]);
        $al = LeaveType::where('code', 'AL')->first();

        $this->actingAs($empUser)->post('/me/leave', [
            'leave_type_id' => $al->id, 'start_date' => '2026-12-21', 'end_date' => '2026-12-22', 'reason' => 'Family trip',
        ])->assertSessionHasNoErrors();

        $request = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame($mgrUser->id, $request->approver_id);
        $this->assertSame(2.0, (float) $request->days);
        Notification::assertSentTo($mgrUser, LeaveRequested::class);

        // The employee cannot approve their own request
        $this->actingAs($empUser)->post("/approvals/{$request->id}", ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($mgrUser)->post("/approvals/{$request->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);
        Notification::assertSentTo($empUser, LeaveDecided::class);
    }

    public function test_without_a_manager_the_request_goes_to_hr(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $empUser = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $empUser->id]);

        $this->actingAs($empUser)->post('/me/leave', [
            'leave_type_id' => LeaveType::where('code', 'AL')->value('id'),
            'start_date' => '2026-12-21', 'end_date' => '2026-12-21',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hr->id, LeaveRequest::first()->approver_id);
    }

    public function test_request_beyond_balance_is_refused(): void
    {
        $company = $this->makeCompany();
        $empUser = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $empUser->id, 'join_date' => '2025-01-01']); // 8 days AL

        $this->actingAs($empUser)->post('/me/leave', [
            'leave_type_id' => LeaveType::where('code', 'AL')->value('id'),
            'start_date' => '2026-12-01', 'end_date' => '2026-12-14', // 10 working days
        ])->assertSessionHasErrors('end_date');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_overlapping_requests_are_refused(): void
    {
        $company = $this->makeCompany();
        $empUser = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $empUser->id]);
        $al = LeaveType::where('code', 'AL')->value('id');
        $payload = ['leave_type_id' => $al, 'start_date' => '2026-12-21', 'end_date' => '2026-12-22'];

        $this->actingAs($empUser)->post('/me/leave', $payload)->assertSessionHasNoErrors();
        $this->actingAs($empUser)->post('/me/leave', $payload)->assertSessionHasErrors('start_date');
    }

    public function test_maternity_leave_is_not_offered_to_men(): void
    {
        $company = $this->makeCompany();
        $empUser = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $empUser->id, 'gender' => 'male']);

        $this->actingAs($empUser)->post('/me/leave', [
            'leave_type_id' => LeaveType::where('code', 'ML')->value('id'),
            'start_date' => '2026-12-21', 'end_date' => '2026-12-21',
        ])->assertSessionHasErrors('leave_type_id');
    }
}
