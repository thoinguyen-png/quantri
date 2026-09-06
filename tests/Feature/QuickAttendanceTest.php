<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_quick_attendance_for_manager_and_staff(): void
    {
        $branch = Branch::create(['name' => 'Chi nhánh Quận 1']);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'name' => 'Nguyễn Quản Lý',
            'branch_id' => $branch->id,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Trần Nhân Viên',
            'branch_id' => $branch->id,
        ]);

        $shift = Shift::create([
            'name' => 'Ca Hành Chính',
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
            'work_day' => 1,
        ]);

        $workDate = Carbon::today();

        ShiftAssignment::create([
            'user_id' => $manager->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get(route('quick-attendance.index'));
        $response->assertOk();
        $response->assertSee('Nguyễn Quản Lý');
        $response->assertSee('Trần Nhân Viên');

        $postResponse = $this->actingAs($admin)->post(route('quick-attendance.store'), [
            'user_ids' => [$manager->id],
            'work_dates' => [$workDate->toDateString()],
            'reason' => 'Admin cập nhật công nhanh cho quản lý',
        ]);

        $postResponse->assertRedirect();
        $postResponse->assertSessionHas('success');

        $attendance = Attendance::where('user_id', $manager->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(1, (int) $attendance->work_day);
        $this->assertSame('completed', $attendance->status);
    }

    public function test_manager_cannot_update_attendance_for_another_manager(): void
    {
        $branch = Branch::create(['name' => 'Chi nhánh Quận 1']);

        $manager1 = User::factory()->create([
            'role' => 'manager',
            'name' => 'Quản lý 1',
            'branch_id' => $branch->id,
        ]);

        $manager2 = User::factory()->create([
            'role' => 'manager',
            'name' => 'Quản lý 2',
            'branch_id' => $branch->id,
        ]);

        $shift = Shift::create([
            'name' => 'Ca Hành Chính',
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
            'work_day' => 1,
        ]);

        $workDate = Carbon::today();

        ShiftAssignment::create([
            'user_id' => $manager2->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $response = $this->actingAs($manager1)->get(route('quick-attendance.index'));
        $response->assertOk();
        $response->assertDontSee('Quản lý 2');

        $postResponse = $this->actingAs($manager1)->post(route('quick-attendance.store'), [
            'user_ids' => [$manager2->id],
            'work_dates' => [$workDate->toDateString()],
            'reason' => 'Quản lý 1 thử cập nhật cho Quản lý 2',
        ]);

        $postResponse->assertRedirect();
        $sessionResult = session('quick_attendance_result');
        $this->assertSame(0, $sessionResult['success_count']);
        $this->assertSame(1, $sessionResult['fail_count']);
    }
}
