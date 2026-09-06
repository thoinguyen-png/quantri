<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSegment;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSegment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Setting;
use App\Services\ShiftScheduleService;
use App\Services\AttendanceCalculationService;
use Illuminate\Support\Facades\Schema;

class AttendanceCheckInFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_shift_checkin_and_checkout_success(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('08:00:00', '17:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'checked_in',
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'completed',
        ]);
        $attendance = Attendance::where(
            'user_id',
            $user->id
        )
            ->whereDate(
                'work_date',
                $workDate->toDateString()
            )
            ->firstOrFail();

        $this->assertEquals(
            1.0,
            (float) $attendance->work_day
        );
    }

    public function test_normal_shift_blocks_scan_after_completed_without_creating_second_attendance(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('08:00:00', '17:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at')->copy()->addMinutes(10));
        $this->postAttendance($user, $branch)->assertSessionHasErrors('attendance');

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'completed',
        ]);
    }

    public function test_scan_before_0850_closes_previous_open_attendance_before_starting_new_day(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('08:00:00', '17:00:00');
        $today = Carbon::parse('2026-06-25');

        $previousAttendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $today->copy()->subDay()->toDateString(),
            'checkin_at' => $this->shiftDateTimeFor($shift, $today->copy()->subDay(), 'start_at'),
            'status' => 'checked_in',
        ]);

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $today->toDateString(),
        ]);

        /* 08:00 vẫn trước hạn checkout 08:50 của ngày công hôm trước. */
        $this->travelTo($this->shiftDateTimeFor($shift, $today, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $previousAttendance->refresh();

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertSame($today->toDateString(), $previousAttendance->checkout_at?->toDateString());
        $this->assertSame('08:00:00', $previousAttendance->checkout_at?->format('H:i:s'));
        $this->assertSame('completed', $previousAttendance->status);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'work_date' => $today->toDateString(),
        ]);
    }

    public function test_overnight_first_scan_after_midnight_uses_previous_work_date_assignment(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('17:00:00', '01:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at')->copy()->subMinutes(30));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'checked_in',
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'work_date' => $workDate->copy()->addDay()->toDateString(),
        ]);
    }

    public function test_immediate_double_checkin_request_does_not_create_second_attendance(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('08:00:00', '17:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));
        $this->postAttendance($user, $branch)->assertSessionHasErrors('attendance');

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    public function test_double_checkout_request_does_not_update_other_attendance_or_create_duplicate(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('08:00:00', '17:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));
        $this->postAttendance($user, $branch)->assertSessionHasErrors('attendance');

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'completed',
        ]);
    }

    public function test_recent_checkout_does_not_checkin_next_day_assignment(): void
    {
        [$user, $branch] = $this->attendanceUser();

        /*
     * Ca cũ thuộc ngày 25, kết thúc lúc 12:20
     * ngày 26.
     */
        [$oldShift, $oldWorkDate] = $this->brokenShift([
            [1, '20:00:00', '12:20:00'],
        ]);

        /*
     * Ca mới bắt đầu đúng lúc ca cũ vừa checkout.
     */
        $nextShift = $this->normalShift(
            '12:20:00',
            '20:00:00'
        );

        $nextWorkDate = $oldWorkDate
            ->copy()
            ->addDay();

        $checkoutAt = Carbon::parse(
            '2026-06-26 12:20:00'
        );

        /*
     * Giả lập request checkout đầu tiên
     * đã hoàn thành ca cũ.
     */
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $oldShift->id,
            'work_date' =>
            $oldWorkDate->toDateString(),
            'checkin_at' =>
            '2026-06-25 20:00:00',
            'checkout_at' => $checkoutAt,
            'worked_minutes' => 980,
            'overtime_minutes' => 0,
            'status' => 'completed',
        ]);

        $attendance->segments()->create([
            'segment_order' => 1,
            'checkin_at' =>
            '2026-06-25 20:00:00',
            'checkout_at' => $checkoutAt,
            'worked_minutes' => 980,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
        ]);

        /*
     * Chỉ gán ca mới để lần request tiếp theo
     * có thể bị resolver hiểu nhầm thành check-in.
     */
        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $nextShift->id,
            'work_date' =>
            $nextWorkDate->toDateString(),
        ]);

        /*
     * Request spam xảy ra đúng thời điểm
     * checkout vừa được lưu.
     */
        $this->travelTo($checkoutAt);

        $response = $this->postAttendance(
            $user,
            $branch
        );

        $response->assertSessionHasErrors(
            'attendance'
        );

        /*
     * Không được sinh attendance cho ca ngày mới.
     */
        $this->assertSame(
            1,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'work_date' =>
            $oldWorkDate->toDateString(),
            'status' => 'completed',
        ]);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'shift_id' => $nextShift->id,
            'work_date' =>
            $nextWorkDate->toDateString(),
            'status' => 'checked_in',
        ]);
    }

    public function test_overnight_shift_checkout_after_midnight_uses_previous_work_date_and_does_not_create_new_work_date_attendance(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $shift = $this->normalShift('17:00:00', '01:00:00');
        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'start_at'));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->travelTo($this->shiftDateTimeFor($shift, $workDate, 'end_at')->copy()->subMinutes(30));
        $this->postAttendance($user, $branch)->assertRedirect(route('dashboard'));

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'status' => 'completed',
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'work_date' => $workDate->copy()->addDay()->toDateString(),
        ]);
    }

    public function test_day_shift_checkout_deadline_is_0850_next_morning(): void
    {
        Setting::setValue('overnight_cutoff_time', '08:50');

        $shift = $this->normalShift(
            '08:00:00',
            '17:00:00'
        );

        $workDate = Carbon::parse('2026-06-25');

        $deadline = app(
            ShiftScheduleService::class
        )->checkoutDeadline(
            $shift,
            $workDate
        );

        $this->assertSame(
            '2026-06-26 08:50:00',
            $deadline->toDateTimeString()
        );
    }

    public function test_overnight_shift_checkout_deadline_is_0850_after_shift_end(): void
    {
        Setting::setValue('overnight_cutoff_time', '08:50');

        $shift = $this->normalShift(
            '17:00:00',
            '04:00:00'
        );

        $workDate = Carbon::parse('2026-06-25');

        $deadline = app(
            ShiftScheduleService::class
        )->checkoutDeadline(
            $shift,
            $workDate
        );

        $this->assertSame(
            '2026-06-26 08:50:00',
            $deadline->toDateTimeString()
        );
    }

    public function test_overnight_shift_can_be_late_and_have_overtime(): void
    {
        [$user] = $this->attendanceUser();

        $shift = $this->normalShift(
            '17:00:00',
            '04:00:00'
        );

        // Cố tình đặt 10 phút để kiểm tra hệ thống
        // không còn miễn phút đi trễ.
        $shift->update([
            'late_after_minutes' => 10,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 17:15:00',
            'checkout_at' => '2026-06-26 05:00:00',
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceCalculationService::class
        )->recalculateForShift(
            $attendance,
            $shift->fresh()
        );

        $this->assertSame(
            15,
            $result['late_minutes']
        );

        $this->assertSame(
            60,
            $result['overtime_minutes']
        );

        $this->assertSame(
            645,
            $result['worked_minutes']
        );

        $this->assertEquals(
            0.98,
            (float) $result['work_day']
        );

        $this->assertSame(
            'completed',
            $result['status']
        );
    }

    public function test_early_checkin_does_not_count_before_shift_start(): void
    {
        [$user] = $this->attendanceUser();

        $shift = $this->normalShift(
            '10:00:00',
            '22:00:00'
        );

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 09:00:00',
            'checkout_at' => '2026-06-25 21:30:00',
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceCalculationService::class
        )->recalculateForShift(
            $attendance,
            $shift
        );

        /*
         * Ca 10:00 -> 22:00.
         * Checkin sớm 09:00 không được cộng vào giờ công.
         * Công chính chỉ tính 10:00 -> 21:30 = 690 phút.
         */
        $this->assertSame(
            690,
            $result['worked_minutes']
        );

        $this->assertSame(
            0,
            $result['overtime_minutes']
        );

        $this->assertEquals(
            0.96,
            (float) $result['work_day']
        );
    }

    public function test_early_checkin_with_full_shift_still_counts_only_one_full_workday(): void
    {
        [$user] = $this->attendanceUser();

        $shift = $this->normalShift(
            '10:00:00',
            '22:00:00'
        );

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 09:00:00',
            'checkout_at' => '2026-06-25 22:00:00',
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceCalculationService::class
        )->recalculateForShift(
            $attendance,
            $shift
        );

        $this->assertSame(
            720,
            $result['worked_minutes']
        );

        $this->assertSame(
            0,
            $result['overtime_minutes']
        );

        $this->assertEquals(
            1.0,
            (float) $result['work_day']
        );
    }

    public function test_overtime_does_not_compensate_for_late_main_shift_minutes(): void
    {
        [$user] = $this->attendanceUser();

        $shift = $this->normalShift(
            '10:00:00',
            '22:00:00'
        );

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 10:30:00',
            'checkout_at' => '2026-06-25 23:00:00',
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceCalculationService::class
        )->recalculateForShift(
            $attendance,
            $shift
        );

        /*
         * 10:30 -> 22:00 = 690 phút công chính.
         * 22:00 -> 23:00 = 60 phút OT riêng.
         * OT không được dùng để bù 30 phút đi trễ.
         */
        $this->assertSame(
            690,
            $result['worked_minutes']
        );

        $this->assertSame(
            30,
            $result['late_minutes']
        );

        $this->assertSame(
            60,
            $result['overtime_minutes']
        );

        $this->assertEquals(
            0.96,
            (float) $result['work_day']
        );
    }

    public function test_open_attendance_after_0850_becomes_missing_checkout_with_half_workday(): void
    {
        [$user, $branch] = $this->attendanceUser();

        Setting::setValue(
            'overnight_cutoff_time',
            '08:50'
        );

        $shift = $this->normalShift(
            '08:00:00',
            '17:00:00'
        );

        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => '2026-06-25 08:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'overtime_minutes' => 99,
            'work_day' => 1,
            'status' => 'checked_in',
        ]);

        /*
         * Hạn checkout là 08:50 ngày hôm sau.
         * Đến 08:51:
         * - Bản công cũ chuyển thành thiếu checkout và 0,5 công.
         * - Nhân viên vẫn được check-in ngày mới khi chưa được gán ca.
         */
        $this->travelTo(
            Carbon::parse('2026-06-26 08:51:00')
        );

        $response = $this->postAttendance(
            $user,
            $branch
        );

        $response->assertRedirect(
            route('dashboard')
        );

        $response->assertSessionHas(
            'warning',
            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
        );

        $attendance->refresh();

        $this->assertSame(
            'missing_checkout',
            $attendance->status
        );

        $this->assertEquals(
            0.5,
            (float) $attendance->work_day
        );

        $this->assertSame(
            0,
            (int) $attendance->overtime_minutes
        );

        $this->assertNull(
            $attendance->checkout_at
        );

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => null,
            'work_date' => '2026-06-26',
            'status' => 'checked_in',
            'work_day' => 0,
        ]);

        $this->assertSame(
            2,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );
    }

    public function test_user_without_shift_assignment_can_still_checkin_with_warning(): void
    {
        [$user, $branch] = $this->attendanceUser();

        $this->travelTo(
            Carbon::parse('2026-06-25 08:00:00')
        );

        $response = $this->postAttendance(
            $user,
            $branch
        );

        $response->assertRedirect(
            route('dashboard')
        );

        $response->assertSessionHas(
            'warning',
            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
        );

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => null,
            'work_date' => '2026-06-25',
            'status' => 'checked_in',
            'work_day' => 0,
        ]);

        $attendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertNotNull(
            $attendance->checkin_at
        );

        $this->assertNull(
            $attendance->checkout_at
        );

        $this->assertSame(
            0,
            (int) $attendance->overtime_minutes
        );
    }

    public function test_user_without_shift_assignment_can_checkout_but_keeps_zero_workday_and_overtime(): void
    {
        [$user, $branch] = $this->attendanceUser();

        /*
     * Không tạo ShiftAssignment.
     *
     * Lần quét đầu tiên lúc 08:00 là check-in.
     */
        $this->travelTo(
            Carbon::parse('2026-06-25 08:00:00')
        );

        $checkinResponse = $this->postAttendance(
            $user,
            $branch
        );

        $checkinResponse->assertRedirect(
            route('dashboard')
        );

        $checkinResponse->assertSessionHas(
            'warning',
            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
        );

        $attendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertNull($attendance->shift_id);
        $this->assertNotNull($attendance->checkin_at);
        $this->assertNull($attendance->checkout_at);

        /*
     * Lần quét tiếp theo lúc 12:30 là checkout.
     * Tổng thời gian thực tế là 270 phút.
     */
        $this->travelTo(
            Carbon::parse('2026-06-25 12:30:00')
        );

        $checkoutResponse = $this->postAttendance(
            $user,
            $branch
        );

        $checkoutResponse->assertRedirect(
            route('dashboard')
        );

        $attendance->refresh();

        $this->assertSame(
            'completed',
            $attendance->status
        );

        $this->assertNotNull(
            $attendance->checkout_at
        );

        $this->assertSame(
            270,
            (int) $attendance->worked_minutes
        );

        /*
     * Chưa có ca nên chưa thể xác định công,
     * đi trễ, về sớm hoặc tăng ca.
     */
        $this->assertEquals(
            0.0,
            (float) $attendance->work_day
        );

        $this->assertSame(
            0,
            (int) $attendance->late_minutes
        );

        $this->assertSame(
            0,
            (int) $attendance->overtime_minutes
        );

        if (
            Schema::hasColumn(
                'attendances',
                'overtime_hours'
            )
        ) {
            $this->assertEquals(
                0.0,
                (float) $attendance->overtime_hours
            );
        }

        $this->assertSame(
            1,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );
    }

    public function test_user_without_shift_assignment_can_checkout_at_0850_next_morning(): void
    {
        [$user, $branch] = $this->attendanceUser();

        Setting::setValue(
            'overnight_cutoff_time',
            '08:50'
        );

        /*
     * Không tạo ShiftAssignment.
     * Check-in lúc 22:00 ngày 25/06.
     */
        $this->travelTo(
            Carbon::parse('2026-06-25 22:00:00')
        );

        $checkinResponse = $this->postAttendance(
            $user,
            $branch
        );

        $checkinResponse->assertRedirect(
            route('dashboard')
        );

        $attendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertNull(
            $attendance->shift_id
        );

        $this->assertSame(
            '2026-06-25',
            $attendance->work_date->toDateString()
        );

        $this->assertSame(
            'checked_in',
            $attendance->status
        );

        /*
     * 08:50 sáng hôm sau vẫn nằm trong hạn.
     * 22:00 → 08:50 = 650 phút.
     */
        $this->travelTo(
            Carbon::parse('2026-06-26 08:50:00')
        );

        $checkoutResponse = $this->postAttendance(
            $user,
            $branch
        );

        $checkoutResponse->assertRedirect(
            route('dashboard')
        );

        $checkoutResponse->assertSessionHas(
            'warning',
            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
        );

        $attendance->refresh();

        $this->assertSame(
            'completed',
            $attendance->status
        );

        $this->assertSame(
            '2026-06-26 08:50:00',
            $attendance->checkout_at->toDateTimeString()
        );

        $this->assertSame(
            650,
            (int) $attendance->worked_minutes
        );

        $this->assertEquals(
            0.0,
            (float) $attendance->work_day
        );

        $this->assertSame(
            0,
            (int) $attendance->late_minutes
        );

        $this->assertSame(
            0,
            (int) $attendance->overtime_minutes
        );

        $this->assertSame(
            1,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );
    }

    public function test_unassigned_attendance_after_0850_becomes_missing_checkout_with_zero_workday(): void
    {
        [$user, $branch] = $this->attendanceUser();

        Setting::setValue(
            'overnight_cutoff_time',
            '08:50'
        );

        /*
     * Không tạo ShiftAssignment.
     * Check-in lúc 22:00 ngày 25/06.
     */
        $this->travelTo(
            Carbon::parse('2026-06-25 22:00:00')
        );

        $this->postAttendance(
            $user,
            $branch
        )->assertRedirect(
            route('dashboard')
        );

        $oldAttendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertNull(
            $oldAttendance->shift_id
        );

        $this->assertSame(
            'checked_in',
            $oldAttendance->status
        );

        /*
     * Sau hạn 08:50:
     * attendance cũ phải chuyển thành thiếu checkout.
     *
     * Đồng thời người dùng vẫn được check-in ngày mới
     * dù chưa được gắn ca.
     */
        $this->travelTo(
            Carbon::parse('2026-06-26 08:51:00')
        );

        $response = $this->postAttendance(
            $user,
            $branch
        );

        $response->assertRedirect(
            route('dashboard')
        );

        $response->assertSessionHas(
            'warning',
            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
        );

        $oldAttendance->refresh();

        $this->assertSame(
            'missing_checkout',
            $oldAttendance->status
        );

        $this->assertNull(
            $oldAttendance->checkout_at
        );

        $this->assertSame(
            0,
            (int) $oldAttendance->worked_minutes
        );

        $this->assertSame(
            0,
            (int) $oldAttendance->overtime_minutes
        );

        $this->assertEquals(
            0.0,
            (float) $oldAttendance->work_day
        );

        /*
     * Request lúc 08:51 đồng thời tạo attendance mới
     * cho ngày 26/06.
     */
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'shift_id' => null,
            'work_date' => '2026-06-26',
            'status' => 'checked_in',
            'work_day' => 0,
        ]);

        $this->assertSame(
            2,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );
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

    private function brokenShift(array $segments): array
    {
        $workDate = Carbon::parse('2026-06-25');
        $shift = $this->normalShift($segments[0][1], $segments[array_key_last($segments)][2]);

        foreach ($segments as [$order, $start, $end]) {
            ShiftSegment::create([
                'shift_id' => $shift->id,
                'segment_order' => $order,
                'start_at' => $start,
                'end_at' => $end,
            ]);
        }

        return [$shift, $workDate];
    }

    private function shiftDateTimeFor(Shift $shift, Carbon $workDate, string $field): Carbon
    {
        $time = Carbon::parse($shift->{$field});
        $dateTime = $workDate->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at' && Carbon::parse($shift->end_at)->lessThanOrEqualTo(Carbon::parse($shift->start_at))) {
            $dateTime->addDay();
        }

        return $dateTime;
    }

    private function segmentDateTimeFor(Shift $shift, Carbon $workDate, int $segmentOrder, string $field): Carbon
    {
        $segment = $shift->segments()->where('segment_order', $segmentOrder)->firstOrFail();
        $time = Carbon::parse($segment->{$field});
        $dateTime = $workDate->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at' && Carbon::parse($segment->end_at)->lessThanOrEqualTo(Carbon::parse($segment->start_at))) {
            $dateTime->addDay();
        }

        return $dateTime;
    }

    private function postAttendance(User $user, Branch $branch)
    {
        $token = 'qr-' . uniqid();

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

    public function test_day_shift_can_checkout_next_morning_before_0850(): void
    {
        [$user, $branch] = $this->attendanceUser();

        Setting::setValue(
            'overnight_cutoff_time',
            '08:50'
        );

        $shift = $this->normalShift(
            '08:00:00',
            '17:00:00'
        );

        $workDate = Carbon::parse('2026-06-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => '2026-06-25 08:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'checked_in',
        ]);

        /*
     * Ca đã kết thúc từ 17:00 hôm trước,
     * nhưng 06:00 vẫn chưa quá hạn 08:50.
     */
        $this->travelTo(
            Carbon::parse('2026-06-26 06:00:00')
        );

        $response = $this->postAttendance(
            $user,
            $branch
        );

        $response->assertRedirect(
            route('dashboard')
        );

        $this->assertSame(
            1,
            Attendance::where(
                'user_id',
                $user->id
            )->count()
        );

        $attendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertSame(
            'completed',
            $attendance->status
        );

        $this->assertSame(
            '2026-06-26 06:00:00',
            $attendance->checkout_at?->toDateTimeString()
        );
    }
    public function test_day_shift_can_checkout_next_morning_with_six_hours_overtime(): void
    {
        [$user, $branch] = $this->attendanceUser();

        Setting::setValue(
            'overnight_cutoff_time',
            '08:50'
        );

        $shift = $this->normalShift(
            '11:00:00',
            '23:00:00'
        );

        $workDate = Carbon::parse('2026-07-25');

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => '2026-07-25 11:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'checked_in',
        ]);

        $this->travelTo(
            Carbon::parse('2026-07-26 05:00:00')
        );

        $this->postAttendance(
            $user,
            $branch
        )->assertRedirect(
            route('dashboard')
        );

        $attendance = Attendance::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertSame(
            'completed',
            $attendance->status
        );

        $this->assertSame(
            '2026-07-26 05:00:00',
            $attendance->checkout_at?->toDateTimeString()
        );

        $this->assertSame(
            720,
            (int) $attendance->worked_minutes
        );

        $this->assertEquals(
            1.0,
            (float) $attendance->work_day
        );

        $this->assertSame(
            360,
            (int) $attendance->overtime_minutes
        );

        $this->assertEquals(
            6.0,
            (float) $attendance->overtime_hours
        );
    }
}