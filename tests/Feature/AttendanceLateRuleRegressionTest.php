<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceDailyMetricsService;
use App\Services\AttendanceLateCalculator;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLateRuleRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('attendance_strict_mode', '0');
        Setting::setValue('overnight_cutoff_time', '08:50');
    }

    public function test_three_minute_grace_uses_whole_minute_rule(): void
    {
        $shift = $this->normalShift('10:00:00', '22:00:00', 3);
        $day = Carbon::parse('2026-08-03');

        $this->assertSame(
            0,
            AttendanceLateCalculator::minutes(
                $shift,
                $day,
                Carbon::parse('2026-08-03 10:03:59')
            )
        );

        $this->assertSame(
            4,
            AttendanceLateCalculator::minutes(
                $shift,
                $day,
                Carbon::parse('2026-08-03 10:04:00')
            )
        );

        $this->assertSame(
            10,
            AttendanceLateCalculator::minutes(
                $shift,
                $day,
                Carbon::parse('2026-08-03 10:10:45')
            )
        );
    }

    public function test_checkin_at_1003_is_not_late_but_1004_is_four_minutes_late(): void
    {
        [$userA, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('10:00:00', '22:00:00', 3);
        $this->assignShift($userA, $shift, Carbon::parse('2026-08-03'));

        $this->travelTo(Carbon::parse('2026-08-03 10:03:00'));
        $this->postAttendance($userA, $branch)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $userA->id,
            'work_date' => '2026-08-03',
            'late_minutes' => 0,
        ]);

        [$userB] = $this->attendanceUser($branch);
        $this->assignShift($userB, $shift, Carbon::parse('2026-08-03'));

        $this->travelTo(Carbon::parse('2026-08-03 10:04:00'));
        $this->postAttendance($userB, $branch)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $userB->id,
            'work_date' => '2026-08-03',
            'late_minutes' => 4,
        ]);
    }

    public function test_statistics_payroll_and_status_use_the_same_late_rule(): void
    {
        [$user] = $this->attendanceUser();
        $shift = $this->normalShift('10:00:00', '22:00:00', 3);
        $day = Carbon::parse('2026-08-03');
        $assignment = $this->assignShift($user, $shift, $day);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'checkin_at' => '2026-08-03 10:04:00',
            'checkout_at' => '2026-08-03 22:04:00',
            // Cố tình để giá trị cũ sai để chứng minh phần thống kê/lương
            // tính lại từ giờ thực tế và cấu hình ca.
            'late_minutes' => 1,
            'worked_minutes' => 720,
            'overtime_minutes' => 4,
            'work_day' => 1,
            'status' => 'completed',
        ]);

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

        $this->assertSame('late', $display['key']);
        $this->assertStringContainsString('4 phút', $display['subtitle']);
        $this->assertSame(4, $metrics['late_minutes']);
        $this->assertSame(1, $metrics['late_days']);
        $this->assertEqualsWithDelta(4 / 60, $metrics['late_hours'], 0.00001);

        /*
         * Ca 10:00 -> 22:00, checkin 10:04:
         * công chính chỉ tính 10:04 -> 22:00 = 716 phút.
         *
         * 22:00 -> 22:04 là OT riêng, không bù 4 phút đi trễ.
         * 716 / 720 = 0.9944... -> 0.99 công.
         */
        $this->assertEquals(0.99, $metrics['work_units']);
    }

    public function test_recalculate_uses_the_same_rule(): void
    {
        [$user] = $this->attendanceUser();
        $shift = $this->normalShift('10:00:00', '22:00:00', 3);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'checkin_at' => '2026-08-03 10:04:00',
            'checkout_at' => '2026-08-03 22:04:00',
            'late_minutes' => 0,
            'worked_minutes' => 720,
            'overtime_minutes' => 0,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $data = app(AttendanceCalculationService::class)
            ->recalculateForShift($attendance, $shift);

        $this->assertSame(4, $data['late_minutes']);
        $this->assertSame(4, $data['overtime_minutes']);

        /*
         * Ca 10:00 -> 22:00 = 720 phút.
         * Checkin 10:04 nên công chính chỉ còn 716 phút.
         * 22:00 -> 22:04 là 4 phút OT riêng và không được
         * dùng để bù lại 4 phút đi trễ.
         *
         * 716 / 720 = 0.9944... -> 0.99 công.
         */
        $this->assertEquals(0.99, $data['work_day']);
    }

    private function attendanceUser(?Branch $existingBranch = null): array
    {
        $branch = $existingBranch ?: Branch::create([
            'name' => 'Test Branch ' . uniqid(),
            'latitude' => 10.0,
            'longitude' => 106.0,
            'gps_radius' => 100,
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'face_descriptor' => '[0.1,0.2,0.3]',
            'face_verification_mode' => 'normal',
            'status' => 'chinh_thuc',
            'start_work_date' => '2026-01-01',
        ]);

        return [$user, $branch];
    }

    private function normalShift(
        string $start,
        string $end,
        int $lateAfterMinutes
    ): Shift {
        return Shift::create([
            'name' => 'Ca test ' . uniqid(),
            'start_at' => $start,
            'end_at' => $end,
            'late_after_minutes' => $lateAfterMinutes,
            'checkin_open_before_minutes' => 60,
            'checkin_close_after_minutes' => 240,
            'checkout_min_after_checkin_minutes' => 3,
            'checkout_close_after_shift_end_minutes' => null,
        ]);
    }

    private function assignShift(
        User $user,
        Shift $shift,
        Carbon $workDate
    ): ShiftAssignment {
        return ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);
    }

    private function postAttendance(User $user, Branch $branch)
    {
        $token = 'late-rule-' . uniqid();

        QrToken::create([
            'token' => $token,
            'branch_id' => $branch->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this
            ->actingAs($user)
            ->withSession([
                'qr_token' => $token,
                'qr_branch_id' => $branch->id,
                'qr_scanned_at' => now(),
                'face_verified_user_id' => $user->id,
                'face_verified_at' => now(),
                'face_verified_distance' => 0.1,
            ])
            ->post(route('attendance.store'), [
                'latitude' => 10.0,
                'longitude' => 106.0,
                'face_verified' => '1',
            ]);
    }
}