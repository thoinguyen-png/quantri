<?php

namespace Tests\Feature;

use App\Models\AttendanceSupplementRequest;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSupplementDateLimitRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-07 12:00:00'));
        Setting::setValue('attendance_supplement_limit_mode', 'last_3_days');
    }

    public function test_last_three_days_includes_today(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        foreach (['2026-08-05', '2026-08-06', '2026-08-07'] as $date) {
            $response = $this->actingAs($user)->post(route('attendance-supplements.store'), [
                'user_id' => $user->id,
                'work_date' => $date,
                'requested_checkin_time' => '10:00',
                'requested_checkout_time' => '22:00',
                'reason' => 'Bo sung cong dung quy tac 3 ngay.',
            ]);

            $response->assertRedirect(route('attendance-supplements.index', absolute: false));
        }

        $this->assertSame(3, AttendanceSupplementRequest::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('attendance_supplement_requests', ['user_id' => $user->id, 'work_date' => '2026-08-05']);
        $this->assertDatabaseHas('attendance_supplement_requests', ['user_id' => $user->id, 'work_date' => '2026-08-06']);
        $this->assertDatabaseHas('attendance_supplement_requests', ['user_id' => $user->id, 'work_date' => '2026-08-07']);
    }

    public function test_day_before_three_day_window_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->from(route('attendance-supplements.create'))
            ->post(route('attendance-supplements.store'), [
                'user_id' => $user->id,
                'work_date' => '2026-08-04',
                'requested_checkin_time' => '10:00',
                'requested_checkout_time' => '22:00',
                'reason' => 'Bo sung cong qua han cho phep.',
            ]);

        $response->assertRedirect(route('attendance-supplements.create', absolute: false));
        $response->assertSessionHasErrors('work_date');
        $this->assertDatabaseMissing('attendance_supplement_requests', [
            'user_id' => $user->id,
            'work_date' => '2026-08-04',
        ]);
    }

    public function test_admin_is_not_restricted_by_three_day_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($admin)->post(route('attendance-supplements.store'), [
            'user_id' => $employee->id,
            'work_date' => '2026-07-01',
            'requested_checkin_time' => '10:00',
            'requested_checkout_time' => '22:00',
            'reason' => 'Admin bo sung cong cu khong bi gioi han 3 ngay.',
        ]);

        $response->assertRedirect(route('attendance-supplements.index', absolute: false));

        $this->assertDatabaseHas('attendance_supplement_requests', [
            'user_id' => $employee->id,
            'work_date' => '2026-07-01',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_create_form_uses_05_06_07_for_staff_but_admin_has_no_three_day_min_max(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $staffResponse = $this->actingAs($staff)->get(route('attendance-supplements.create'));
        $staffResponse->assertOk();
        $staffResponse->assertSee('min="2026-08-05"', false);
        $staffResponse->assertSee('max="2026-08-07"', false);

        $adminResponse = $this->actingAs($admin)->get(route('attendance-supplements.create'));
        $adminResponse->assertOk();
        $adminResponse->assertDontSee('min="2026-08-05"', false);
        $adminResponse->assertDontSee('max="2026-08-07"', false);
        $adminResponse->assertSee('Admin được chọn ngày bổ sung công tự do.');
    }
}
