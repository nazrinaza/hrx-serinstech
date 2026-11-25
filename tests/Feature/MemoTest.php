<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Memo;
use App\Notifications\MemoPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_posts_memo_that_all_staff_see_on_dashboard(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $staff = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $staff->id]);

        $this->actingAs($hr)->post('/memos', [
            'title' => 'Office closed on Friday', 'body' => "Line one\nLine two", 'priority' => 'important',
            'publish_at' => now()->subMinute()->format('Y-m-d\TH:i'), 'audience' => 'all', 'send_email' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get('/dashboard')->assertSee('Office closed on Friday')->assertSee('Important');
        Notification::assertSentTo($staff, MemoPublished::class);
    }

    public function test_department_memo_is_hidden_from_other_departments(): void
    {
        $company = $this->makeCompany();
        $ops = Department::create(['name' => 'Ops']);
        $fin = Department::create(['name' => 'Finance']);
        $opsUser = $this->userIn($company, 'employee');
        $finUser = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $opsUser->id, 'department_id' => $ops->id]);
        $this->employeeIn($company, ['user_id' => $finUser->id, 'department_id' => $fin->id]);
        $memo = Memo::create(['title' => 'Ops shift change', 'body' => 'x', 'publish_at' => now()->subHour(), 'department_ids' => [$ops->id]]);

        $this->actingAs($opsUser)->get('/dashboard')->assertSee('Ops shift change');
        $this->actingAs($finUser)->get('/dashboard')->assertDontSee('Ops shift change');
        $this->actingAs($finUser)->get("/memos/{$memo->id}")->assertNotFound();
    }

    public function test_scheduled_and_expired_memos_are_not_shown(): void
    {
        $company = $this->makeCompany();
        $staff = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $staff->id]);
        Memo::create(['title' => 'Future memo', 'body' => 'x', 'publish_at' => now()->addDay()]);
        Memo::create(['title' => 'Old memo', 'body' => 'x', 'publish_at' => now()->subWeek(), 'expires_at' => now()->subDay()]);

        $this->actingAs($staff)->get('/memos')->assertDontSee('Future memo')->assertDontSee('Old memo');
    }

    public function test_staff_acknowledge_and_hr_sees_who_has(): void
    {
        $company = $this->makeCompany();
        $hr = $this->userIn($company, 'hr');
        $staff = $this->userIn($company, 'employee');
        $this->employeeIn($company, ['user_id' => $staff->id]);
        $memo = Memo::create(['title' => 'New safety policy', 'body' => 'x', 'publish_at' => now()->subHour(), 'requires_ack' => true]);

        $this->actingAs($staff)->get('/dashboard')->assertSee('1 memo needs your acknowledgement');
        $this->actingAs($staff)->post("/memos/{$memo->id}/acknowledge")->assertSessionHasNoErrors();
        $this->actingAs($staff)->get('/dashboard')->assertDontSee('needs your acknowledgement');

        $this->actingAs($hr)->get("/memos/{$memo->id}/readers")->assertSee($staff->name)->assertSee('1 acknowledged', false);
    }

    public function test_staff_cannot_manage_memos_or_see_other_companies_memos(): void
    {
        $a = $this->makeCompany('A');
        $staff = $this->userIn($a, 'employee');
        $b = $this->makeCompany('B');
        $memoB = Memo::create(['title' => 'B only', 'body' => 'x', 'publish_at' => now()->subHour()]);

        $this->actingAs($staff)->get('/memos/manage')->assertForbidden();
        $this->actingAs($staff)->post('/memos', ['title' => 'x'])->assertForbidden();
        $this->actingAs($staff)->get("/memos/{$memoB->id}")->assertNotFound();
    }
}
