<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceDailyMetricsService;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAssignmentConsistencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-08 10:00:00'));
        Setting::setValue('shift_assignment_show_all_shifts', '1');
        Setting::setValue('overnight_cutoff_time', '08:50');
    }

    public function test_current_assignment_wins_over_stale_attendance_shift_for_status_and_metrics(): void
    {
        [$user] = $this->staffUser();

        $oldShift = $this->shift('Ca Tạp vụ', '08:00:00', '17:00:00');
        $newShift = $this->shift('Ca Bảo vệ', '10:00:00', '22:00:00');
        $day = Carbon::parse('2026-08-07');

        $assignment = ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $newShift->id,
            'work_date' => $day->toDateString(),
        ])->load('shift');

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $oldShift->id, // snapshot cũ bị lệch
            'work_date' => $day->toDateString(),
            'checkin_at' => '2026-08-07 10:00:00',
            'checkout_at' => '2026-08-07 22:00:00',
            'worked_minutes' => 720,
            'late_minutes' => 120,
            'overtime_minutes' => 300,
            'work_day' => 1,
            'status' => 'completed',
        ])->load('shift');

        $display = app(AttendanceStatusService::class)
            ->resolveDailyStatus(
                $user,
                $day,
                $attendance,
                $assignment
            );

        $metrics = app(AttendanceDailyMetricsService::class)
            ->calculate(
                $attendance,
                $assignment,
                $day,
                $display['key']
            );

        $this->assertSame('full_day', $display['key']);
        $this->assertSame(0, $metrics['late_minutes']);
        $this->assertSame(720, $metrics['required_minutes']);
        $this->assertEquals(1.0, (float) $metrics['work_units']);
    }

    public function test_statistics_page_displays_current_assignment_shift_not_stale_attendance_shift(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$user] = $this->staffUser();

        $oldShift = $this->shift('Ca Tạp vụ', '08:00:00', '17:00:00');
        $newShift = $this->shift('Ca Bảo vệ', '10:00:00', '22:00:00');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $newShift->id,
            'work_date' => '2026-08-07',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $oldShift->id,
            'work_date' => '2026-08-07',
            'checkin_at' => '2026-08-07 10:00:00',
            'checkout_at' => '2026-08-07 22:00:00',
            'worked_minutes' => 720,
            'late_minutes' => 120,
            'overtime_minutes' => 300,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->get(route('attendance-statistics.index', [
                'month' => '2026-08',
                'search' => $user->name,
            ]))
            ->assertOk()
            ->assertSee('Ca Bảo vệ');
    }

    public function test_saving_same_assignment_detects_stale_attendance_and_offers_recalculate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$user] = $this->staffUser();

        $oldShift = $this->shift('Ca Tạp vụ', '08:00:00', '17:00:00');
        $newShift = $this->shift('Ca Bảo vệ', '10:00:00', '22:00:00');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $newShift->id,
            'work_date' => '2026-08-07',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $oldShift->id,
            'work_date' => '2026-08-07',
            'checkin_at' => '2026-08-07 10:00:00',
            'checkout_at' => '2026-08-07 22:00:00',
            'worked_minutes' => 720,
            'late_minutes' => 120,
            'overtime_minutes' => 300,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('shift-assignments.index', ['month' => '2026-08']))
            ->post(route('shift-assignments.store'), [
                'user_ids' => [$user->id],
                'shift_id' => $newShift->id,
                'work_dates' => ['2026-08-07'],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'warning',
                'Nhân viên đã có dữ liệu chấm công trong ngày này. Vui lòng dùng chức năng Đổi ca và tính lại công.'
            );
    }

    public function test_recalculate_repairs_stale_attendance_shift_without_deleting_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$user] = $this->staffUser();

        $oldShift = $this->shift('Ca Tạp vụ', '08:00:00', '17:00:00');
        $newShift = $this->shift('Ca Bảo vệ', '10:00:00', '22:00:00');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $newShift->id,
            'work_date' => '2026-08-07',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $oldShift->id,
            'work_date' => '2026-08-07',
            'checkin_at' => '2026-08-07 10:00:00',
            'checkout_at' => '2026-08-07 22:00:00',
            'worked_minutes' => 720,
            'late_minutes' => 120,
            'overtime_minutes' => 300,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->post(route('shift-assignments.recalculate'), [
                'user_ids' => [$user->id],
                'shift_id' => $newShift->id,
                'work_dates' => ['2026-08-07'],
            ])
            ->assertRedirect(route('shift-assignments.index', [
                'month' => '2026-08',
            ]));

        $attendance->refresh();

        $this->assertSame($newShift->id, $attendance->shift_id);
        $this->assertSame(0, (int) $attendance->late_minutes);
        $this->assertSame(0, (int) $attendance->overtime_minutes);
        $this->assertEquals(1.0, (float) $attendance->work_day);

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $user->id,
            'shift_id' => $newShift->id,
            'work_date' => '2026-08-07',
        ]);
    }

    private function staffUser(): array
    {
        $branch = Branch::create([
            'name' => 'Chi nhánh test',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'gps_radius' => 100,
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'status' => 'chinh_thuc',
            'start_work_date' => '2026-01-01',
        ]);

        return [$user, $branch];
    }

    private function shift(string $name, string $start, string $end): Shift
    {
        return Shift::create([
            'name' => $name,
            'start_at' => $start,
            'end_at' => $end,
            'late_after_minutes' => 3,
            'checkin_open_before_minutes' => 60,
            'checkin_close_after_minutes' => 240,
            'checkout_min_after_checkin_minutes' => 3,
            'checkout_close_after_shift_end_minutes' => null,
        ]);
    }
}
