<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSegment;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSegment;
use App\Models\User;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSingleFlowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('attendance_strict_mode', '0');
        Setting::setValue('overnight_cutoff_time', '08:50');
    }

    /**
     * Luồng cơ bản:
     * lần quét đầu = checkin
     * lần quét sau = checkout
     */
    public function test_normal_shift_has_only_one_checkin_and_one_checkout(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');
        $workDate = Carbon::parse('2026-08-03');

        $this->assignShift($user, $shift, $workDate);

        $this->travelTo(Carbon::parse('2026-08-03 10:00:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'status' => 'checked_in',
        ]);

        $this->travelTo(Carbon::parse('2026-08-03 22:00:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', '2026-08-03')
            ->firstOrFail();

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertSame('completed', $attendance->status);
        $this->assertSame(
            '2026-08-03 10:00:00',
            $attendance->checkin_at?->toDateTimeString()
        );
        $this->assertSame(
            '2026-08-03 22:00:00',
            $attendance->checkout_at?->toDateTimeString()
        );
        $this->assertEquals(1.0, (float) $attendance->work_day);
    }

    /**
     * Đây là case quan trọng nhất:
     * - ca ngày 03: 10:00 -> 22:00
     * - đã checkin ngày 03
     * - 00:50 ngày 04 mới checkout
     *
     * Dù ngày 04 cũng đã có ca được gán, lần quét 00:50
     * vẫn phải checkout ca ngày 03, KHÔNG được tạo checkin ngày 04.
     */
    public function test_checkout_at_0050_next_day_closes_previous_work_date(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');

        $day03 = Carbon::parse('2026-08-03');
        $day04 = Carbon::parse('2026-08-04');

        $this->assignShift($user, $shift, $day03);
        $this->assignShift($user, $shift, $day04);

        $this->travelTo(Carbon::parse('2026-08-03 10:00:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $this->travelTo(Carbon::parse('2026-08-04 00:50:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $this->assertSame(
            1,
            Attendance::where('user_id', $user->id)->count(),
            '00:50 ngày 04 không được tạo attendance mới cho ngày 04.'
        );

        $attendance = Attendance::where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('2026-08-03', $attendance->work_date?->toDateString());
        $this->assertSame('completed', $attendance->status);
        $this->assertSame(
            '2026-08-04 00:50:00',
            $attendance->checkout_at?->toDateTimeString()
        );

        $this->assertSame(170, (int) $attendance->overtime_minutes);
        $this->assertEquals(1.0, (float) $attendance->work_day);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-08-04',
        ]);
    }

    /**
     * Ca qua đêm 17:00 -> 04:00.
     * Nếu lần quét ĐẦU TIÊN xảy ra lúc 00:50 ngày 04
     * thì vẫn phải là checkin của ngày công 03.
     */
    public function test_first_scan_after_midnight_uses_previous_overnight_work_date(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('17:00:00', '04:00:00');
        $workDate = Carbon::parse('2026-08-03');

        $this->assignShift($user, $shift, $workDate);

        $this->travelTo(Carbon::parse('2026-08-04 00:50:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $attendance = Attendance::where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('2026-08-03', $attendance->work_date?->toDateString());
        $this->assertSame($shift->id, $attendance->shift_id);
        $this->assertSame('checked_in', $attendance->status);
        $this->assertSame(
            '2026-08-04 00:50:00',
            $attendance->checkin_at?->toDateTimeString()
        );

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-08-04',
        ]);
    }

    /**
     * Đúng 08:50 sáng hôm sau vẫn còn được checkout.
     */
    public function test_checkout_exactly_at_0850_is_still_allowed(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');
        $workDate = Carbon::parse('2026-08-03');

        $this->assignShift($user, $shift, $workDate);

        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'checkin_at' => '2026-08-03 10:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'checked_in',
        ]);

        $this->travelTo(Carbon::parse('2026-08-04 08:50:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $attendance = Attendance::where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('completed', $attendance->status);
        $this->assertSame(
            '2026-08-04 08:50:00',
            $attendance->checkout_at?->toDateTimeString()
        );
        $this->assertSame('2026-08-03', $attendance->work_date?->toDateString());
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    /**
     * 08:51 đã quá hạn:
     * attendance ngày cũ phải thành missing_checkout và 0.5 công.
     *
     * Vì test này không gán ca ngày 04, lần quét 08:51 sau đó
     * sẽ tạo một attendance không ca cho ngày 04.
     */
    public function test_after_0850_previous_open_attendance_becomes_missing_checkout(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');
        $workDate = Carbon::parse('2026-08-03');

        $this->assignShift($user, $shift, $workDate);

        $oldAttendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'checkin_at' => '2026-08-03 10:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'checked_in',
        ]);

        $this->travelTo(Carbon::parse('2026-08-04 08:51:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $oldAttendance->refresh();

        $this->assertSame('missing_checkout', $oldAttendance->status);
        $this->assertNull($oldAttendance->checkout_at);
        $this->assertEquals(0.5, (float) $oldAttendance->work_day);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => null,
            'work_date' => '2026-08-04',
            'status' => 'checked_in',
        ]);
    }

    /**
     * shift_segments cũ có thể vẫn còn trong DB,
     * nhưng hệ thống mới phải bỏ qua nó hoàn toàn.
     *
     * Đồng thời luồng mới không được tạo attendance_segments.
     */
    public function test_legacy_shift_segment_is_ignored_and_no_attendance_segments_are_created(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');
        $workDate = Carbon::parse('2026-08-03');

        // Dữ liệu segment cũ cố tình để giờ khác với shifts.start_at/end_at.
        ShiftSegment::create([
            'shift_id' => $shift->id,
            'segment_order' => 1,
            'start_at' => '06:15:00',
            'end_at' => '08:45:00',
        ]);

        $this->assignShift($user, $shift, $workDate);

        $this->travelTo(Carbon::parse('2026-08-03 10:00:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $this->travelTo(Carbon::parse('2026-08-03 22:00:00'));
        $this->postAttendance($user, $branch)
            ->assertRedirect(route('dashboard'));

        $attendance = Attendance::where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('completed', $attendance->status);
        $this->assertSame(
            '2026-08-03 10:00:00',
            $attendance->checkin_at?->toDateTimeString()
        );
        $this->assertSame(
            '2026-08-03 22:00:00',
            $attendance->checkout_at?->toDateTimeString()
        );

        $this->assertSame(
            0,
            AttendanceSegment::where('attendance_id', $attendance->id)->count(),
            'Luồng mới không được ghi attendance_segments.'
        );
    }

    /**
     * Dữ liệu cũ có segment bị lỗi:
     * parent attendance có đủ checkin/checkout nhưng segment thiếu checkin.
     *
     * Trạng thái hiển thị phải dựa vào parent attendance,
     * không được tiếp tục báo "Thiếu checkin đoạn 1".
     */
    public function test_broken_legacy_attendance_segment_does_not_force_missing_checkin(): void
    {
        $this->travelTo(Carbon::parse('2026-08-03 23:00:00'));

        [$user] = $this->attendanceUser();

        $shift = $this->normalShift('10:00:00', '22:00:00');
        $workDate = Carbon::parse('2026-08-03');

        $assignment = $this->assignShift($user, $shift, $workDate);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-08-03',
            'checkin_at' => '2026-08-03 10:00:00',
            'checkout_at' => '2026-08-03 22:00:00',
            'worked_minutes' => 720,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 1,
            // Cố tình giữ status cũ bị sai để kiểm tra service không tin status cũ.
            'status' => 'missing_checkin',
        ]);

        AttendanceSegment::create([
            'attendance_id' => $attendance->id,
            'segment_order' => 1,
            'checkin_at' => null,
            'checkout_at' => '2026-08-03 22:00:00',
            'worked_minutes' => 0,
        ]);

        $result = app(AttendanceStatusService::class)
            ->resolveDailyStatus(
                $user,
                $workDate,
                $attendance,
                $assignment
            );

        $this->assertSame('full_day', $result['key']);
        $this->assertSame('Đủ công', $result['label']);
    }

    private function attendanceUser(): array
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
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

    private function normalShift(string $start, string $end): Shift
    {
        return Shift::create([
            'name' => 'Ca test',
            'start_at' => $start,
            'end_at' => $end,
            'late_after_minutes' => 0,
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
        $token = 'single-flow-' . uniqid();

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