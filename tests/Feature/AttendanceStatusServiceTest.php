<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_work_date_is_upcoming(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 09:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-26')
        );

        $this->assertSame(
            'upcoming',
            $result['key']
        );

        $this->assertSame(
            'Sắp Đến',
            $result['label']
        );
    }

    public function test_date_before_employment_start_is_not_started(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 09:00:00')
        );

        $user = $this->staffUser(
            '2026-06-11'
        );

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-10')
        );

        $this->assertSame(
            'not_started',
            $result['key']
        );

        $this->assertSame(
            'Chưa Làm',
            $result['label']
        );
    }

    public function test_day_without_assignment_is_no_shift(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 09:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25')
        );

        $this->assertSame(
            'no_shift',
            $result['key']
        );

        $this->assertSame(
            'Nghỉ',
            $result['label']
        );
    }

    public function test_assignment_before_checkin_deadline_is_not_checked_in_yet(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 09:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $assignment = ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            null,
            $assignment
        );

        $this->assertSame(
            'not_checked_in_yet',
            $result['key']
        );

        $this->assertSame(
            'Chưa Checkin',
            $result['label']
        );
    }

    public function test_assignment_after_checkin_deadline_is_absent_without_leave(): void
    {
        /*
         * Ca bắt đầu 08:00 và cho checkin muộn tối đa
         * 240 phút, nên hạn checkin là 12:00.
         */
        $this->travelTo(
            Carbon::parse('2026-06-25 12:01:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $assignment = ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            null,
            $assignment
        );

        $this->assertSame(
            'absent_without_leave',
            $result['key']
        );

        $this->assertSame(
            'Nghỉ không phép',
            $result['label']
        );
    }

    public function test_approved_leave_is_leave_approved(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 09:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $leave = new LeaveRequest();
        $leave->leave_type = 'nghi_phep';
        $leave->status = 'approved';
        $leave->work_date = '2026-06-25';

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            null,
            null,
            $leave
        );

        $this->assertSame(
            'leave_approved',
            $result['key']
        );

        $this->assertSame(
            'Nghỉ có phép',
            $result['label']
        );
    }

    public function test_open_attendance_before_0850_next_morning_is_working(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-26 05:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift(
            '11:00:00',
            '23:00:00'
        );

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 11:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'checked_in',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'working',
            $result['key']
        );

        $this->assertSame(
            'Đang làm',
            $result['label']
        );
    }

    public function test_open_attendance_after_0850_is_missing_checkout(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-26 08:51:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift(
            '11:00:00',
            '23:00:00'
        );

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 11:00:00',
            'checkout_at' => null,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0.5,
            'status' => 'missing_checkout',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'missing_checkout',
            $result['key']
        );

        $this->assertSame(
            'Thiếu checkout',
            $result['label']
        );
    }

    public function test_completed_on_time_attendance_is_full_day(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 18:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 08:00:00',
            'checkout_at' => '2026-06-25 17:00:00',
            'worked_minutes' => 540,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'full_day',
            $result['key']
        );

        $this->assertSame(
            'Đủ công',
            $result['label']
        );
    }

    public function test_early_checkout_is_insufficient_work(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 18:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 08:00:00',
            'checkout_at' => '2026-06-25 16:00:00',
            'worked_minutes' => 480,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'insufficient_work',
            $result['key']
        );

        $this->assertSame(
            'Thiếu công',
            $result['label']
        );
    }

    public function test_late_checkin_is_late_even_when_work_is_completed(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 18:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => '2026-06-25 08:15:00',
            'checkout_at' => '2026-06-25 17:15:00',
            'worked_minutes' => 540,
            'late_minutes' => 15,
            'overtime_minutes' => 15,
            'work_day' => 1,
            'status' => 'completed',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'late',
            $result['key']
        );

        $this->assertSame(
            'Đi trễ',
            $result['label']
        );
    }

    public function test_checkout_without_checkin_is_missing_checkin(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 18:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => null,
            'checkout_at' => '2026-06-25 17:00:00',
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day' => 0,
            'status' => 'missing_checkin',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            $attendance
        );

        $this->assertSame(
            'missing_checkin',
            $result['key']
        );

        $this->assertSame(
            'Thiếu checkin',
            $result['label']
        );
    }

    public function test_today_shift_before_start_time_is_upcoming(): void
    {
        $this->travelTo(
            Carbon::parse('2026-06-25 14:00:00')
        );

        $user = $this->staffUser(
            '2026-06-01'
        );

        $shift = $this->normalShift(
            '16:00:00',
            '23:00:00'
        );

        $assignment = ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
        ]);

        $result = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            $user,
            Carbon::parse('2026-06-25'),
            null,
            $assignment
        );

        $this->assertSame(
            'upcoming',
            $result['key']
        );

        $this->assertSame(
            'Sắp Đến',
            $result['label']
        );
    }


    private function staffUser(
        string $startWorkDate
    ): User {
        return User::factory()->create([
            'role' => 'staff',
            'start_work_date' => $startWorkDate,
            'status' => 'chinh_thuc',
        ]);
    }

    private function normalShift(
        string $startAt = '08:00:00',
        string $endAt = '17:00:00'
    ): Shift {
        return Shift::create([
            'name' => 'Ca trạng thái',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'late_after_minutes' => 0,
            'checkin_open_before_minutes' => 60,
            'checkin_close_after_minutes' => 240,
            'checkout_min_after_checkin_minutes' => 3,
            'checkout_close_after_shift_end_minutes' => null,
        ]);
    }
}