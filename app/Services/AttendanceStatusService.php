<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSupplementRequest;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;

class AttendanceStatusService
{
    public function __construct(
        private readonly AttendanceCalculationService $calculator
    ) {
    }

    public function resolveDailyStatus(
        User $user,
        Carbon $workDate,
        ?Attendance $attendance = null,
        ?ShiftAssignment $assignment = null,
        ?LeaveRequest $leave = null,
        ?AttendanceSupplementRequest $supplement = null
    ): array {
        if ($user->role === 'admin') {
            return $this->status(
                'unknown',
                'Không áp dụng',
                'Không áp dụng trạng thái công cá nhân cho admin.'
            );
        }

        $workDate = $workDate->copy()->startOfDay();
        $today = today();
        /*
         * Phân ca của đúng ngày là nguồn ca hiện hành.
         * attendance.shift_id chỉ dùng làm fallback cho dữ liệu cũ
         * hoặc ngày chưa có ShiftAssignment.
         */
        $shift = $assignment?->shift ?? $attendance?->shift;

        /*
         * "Chưa Làm" chỉ dành cho ngày trước ngày
         * nhân sự chính thức bắt đầu làm việc.
         *
         * Kiểm tra điều kiện này trước ngày tương lai để:
         * nhân sự bắt đầu ngày 03 thì ngày 01 và 02
         * luôn là "Chưa Làm".
         */
        if (
            $user->start_work_date
            && $workDate->lessThan(
                Carbon::parse(
                    $user->start_work_date
                )->startOfDay()
            )
        ) {
            return $this->status(
                key: 'not_started',
                label: 'Chưa Làm',
                subtitle:
                    'Nhân sự chưa đến ngày bắt đầu làm việc.'
            );
        }

        /*
         * Ngày lớn hơn ngày hiện tại là "Sắp Đến".
         */
        if ($workDate->greaterThan($today)) {
            return $this->status(
                key: 'upcoming',
                label: 'Sắp Đến',
                subtitle: 'Ngày làm việc này chưa đến.'
            );
        }

        /*
         * Dữ liệu chấm công thực tế được ưu tiên trước
         * lịch nghỉ và phân ca.
         */
        if ($attendance) {
            return $this->attendanceStatus(
                $attendance,
                $workDate,
                $shift,
                $leave,
                $supplement
            );
        }

        if ($leave) {
            return $this->status(
                key: 'leave_approved',
                label: 'Nghỉ có phép',
                subtitle: 'Đơn xin nghỉ phép đã được duyệt.',
                badges: [$this->leaveTypeLabel($leave->leave_type)]
            );
        }

        /*
         * Không được gắn ca trong ngày.
         */
        if (!$assignment || !$shift) {
            return $this->status(
                key: 'no_shift',
                label: 'Nghỉ',
                subtitle: 'Nhân sự không được gắn ca trong ngày này.'
            );
        }

        /*
         * Hôm nay đã được gắn ca nhưng giờ bắt đầu ca
         * vẫn lớn hơn thời điểm hiện tại thì là "Sắp Đến".
         *
         * Ví dụ: hiện tại 14:00, ca bắt đầu 16:00.
         */
        if (
            $workDate->isSameDay($today)
            && now()->lessThan(
                $this->scheduledShiftStart(
                    $shift,
                    $workDate
                )
            )
        ) {
            return $this->status(
                key: 'upcoming',
                label: 'Sắp Đến',
                subtitle:
                    'Ca làm chưa đến giờ bắt đầu.'
            );
        }

        /*
         * Có ca, đã quá thời hạn checkin nhưng không có
         * attendance và không có đơn phép đã duyệt.
         */
        if ($this->canMarkAbsentWithoutLeave($workDate, $shift)) {
            return $this->status(
                key: 'absent_without_leave',
                label: 'Nghỉ không phép',
                subtitle: 'Đã quá giờ checkin, không có chấm công và không có phép đã duyệt.'
            );
        }

        return $this->status(
            key: 'not_checked_in_yet',
            label: 'Chưa Checkin',
            subtitle:
                'Ca đã bắt đầu nhưng nhân sự chưa checkin và vẫn còn trong thời gian cho phép.'
        );
    }

    private function attendanceStatus(
        Attendance $attendance,
        Carbon $workDate,
        ?Shift $shift,
        ?LeaveRequest $leave,
        ?AttendanceSupplementRequest $supplement
    ): array {
        /*
         * Nguồn dữ liệu chấm công duy nhất từ đây là bản ghi cha:
         * attendances.checkin_at và attendances.checkout_at.
         *
         * attendance_segments được giữ để bảo toàn lịch sử nhưng
         * không còn được dùng để kết luận thiếu checkin/checkout.
         */
        $badges = $this->baseBadges(
            $leave,
            $supplement
        );

        $checkin = $attendance->checkin_at
            ? Carbon::parse($attendance->checkin_at)
            : null;

        $checkout = $attendance->checkout_at
            ? Carbon::parse($attendance->checkout_at)
            : null;

        /*
         * Có giờ ra nhưng không có giờ vào.
         */
        if (!$checkin && $checkout) {
            return $this->status(
                key: 'missing_checkin',
                label: 'Thiếu checkin',
                subtitle:
                    'Có checkout nhưng không có checkin.',
                workStatus: 'missing',
                workLabel: 'Thiếu checkin',
                badges: $badges
            );
        }

        /*
         * Chưa có cả giờ vào lẫn giờ ra.
         */
        if (!$checkin && !$checkout) {
            return $this->status(
                key: 'not_checked_in_yet',
                label: 'Chưa làm',
                subtitle:
                    'Chưa có dữ liệu checkin và checkout.',
                workStatus: 'pending',
                workLabel: 'Chưa làm',
                badges: $badges
            );
        }

        /*
         * Có giờ vào nhưng chưa có giờ ra.
         *
         * Trước hạn checkout: Đang làm.
         * Sau hạn checkout: Thiếu checkout.
         */
        if ($checkin && !$checkout) {
            $attendanceWorkDate = $attendance->work_date
                ? Carbon::parse(
                    $attendance->work_date
                )->startOfDay()
                : $workDate->copy()->startOfDay();

            $deadline = $shift
                ? $this->checkoutDeadline(
                    $attendanceWorkDate,
                    $shift
                )
                : $this->unassignedCheckoutDeadline(
                    $attendanceWorkDate
                );

            if ($shift) {
                $openLateMinutes = AttendanceLateCalculator::minutes(
                    $shift,
                    $attendanceWorkDate,
                    $checkin
                );

                if ($openLateMinutes > 0) {
                    $badges[] = 'Đi trễ '
                        . $openLateMinutes
                        . ' phút';
                }
            }

            if (now()->lessThanOrEqualTo($deadline)) {
                if (!$shift) {
                    $badges[] = 'Chưa được gắn ca';
                }

                return $this->status(
                    key: 'working',
                    label: 'Đang làm',
                    subtitle:
                        'Đã checkin và vẫn còn trong thời hạn checkout.',
                    workStatus: 'working',
                    workLabel: 'Đang làm',
                    badges: $badges
                );
            }

            return $this->status(
                key: 'missing_checkout',
                label: 'Thiếu checkout',
                subtitle:
                    'Có checkin nhưng đã quá hạn checkout 08:50.',
                workStatus: 'missing',
                workLabel: 'Thiếu checkout',
                badges: $badges
            );
        }

        /*
         * Có đủ giờ vào và giờ ra nhưng chưa được gắn ca.
         */
        if (!$shift) {
            return $this->status(
                key: 'no_shift',
                label: 'Nghỉ',
                subtitle:
                    'Có dữ liệu chấm công nhưng nhân sự chưa được gắn ca.',
                workStatus: 'none',
                workLabel: 'Chưa được gắn ca',
                badges: $badges
            );
        }

        /*
         * Attendance đã hoàn tất phải dùng cùng calculator với:
         * - luồng checkout;
         * - bổ sung công;
         * - thống kê/lương.
         *
         * Nhờ đó trạng thái không còn hiểu rằng OT có thể
         * bù cho phần giờ công chính bị thiếu.
         */
        $calculated = $this->calculator
            ->recalculateForShift(
                $attendance,
                $shift
            );

        $lateMinutes = max(
            0,
            (int) (
                $calculated['late_minutes']
                ?? $this->lateMinutes(
                    $attendance,
                    $shift,
                    $workDate
                )
            )
        );

        $earlyLeaveMinutes = $this->earlyLeaveMinutes(
            $attendance,
            $shift,
            $workDate
        );

        $overtimeMinutes = max(
            0,
            (int) (
                $calculated['overtime_minutes']
                ?? 0
            )
        );

        $workDay = array_key_exists(
            'work_day',
            $calculated
        )
            ? (float) $calculated['work_day']
            : $this->workDayFromActualTimes(
                $attendance,
                $shift,
                $workDate
            );

        $hasInsufficientWork = $workDay < 1.0;

        if ($overtimeMinutes > 0) {
            $badges[] = 'Tăng ca '
                . $overtimeMinutes
                . ' phút';
        }

        if ($earlyLeaveMinutes > 0) {
            $badges[] = 'Về sớm '
                . $earlyLeaveMinutes
                . ' phút';
        }

        /*
         * Đi trễ vẫn là trạng thái chính để người dùng
         * dễ nhận biết nguyên nhân.
         *
         * Tuy nhiên nếu đi trễ làm thiếu giờ công chính,
         * work_status phải là missing.
         *
         * Ví dụ:
         * Ca 10:00 -> 22:00
         * Checkin 10:30, checkout 23:00
         * => 0,96 công + 60 phút OT
         * => trạng thái chính: Đi trễ
         * => công: Thiếu công
         */
        if ($lateMinutes > 0) {
            return $this->status(
                key: 'late',
                label: 'Đi trễ',
                subtitle: 'Checkin trễ '
                    . $lateMinutes
                    . ' phút so với giờ bắt đầu ca.',
                workStatus: $hasInsufficientWork
                    ? 'missing'
                    : 'complete',
                workLabel: $hasInsufficientWork
                    ? (
                        $earlyLeaveMinutes > 0
                            ? 'Đi trễ và về sớm, thiếu công'
                            : 'Đi trễ, thiếu công'
                    )
                    : 'Đi trễ',
                badges: $badges
            );
        }

        if (
            $earlyLeaveMinutes > 0
            || $hasInsufficientWork
        ) {
            $subtitle = $earlyLeaveMinutes > 0
                ? 'Checkout sớm '
                    . $earlyLeaveMinutes
                    . ' phút so với giờ kết thúc ca.'
                : 'Tổng giờ công chính chưa đủ thời lượng ca.';

            return $this->status(
                key: 'insufficient_work',
                label: 'Thiếu công',
                subtitle: $subtitle,
                workStatus: 'missing',
                workLabel: 'Thiếu công',
                badges: $badges
            );
        }

        return $this->status(
            key: 'full_day',
            label: 'Đủ công',
            subtitle:
                'Đã hoàn thành đủ thời lượng công chính của ca.',
            workStatus: 'complete',
            workLabel: 'Đủ công',
            badges: $badges
        );
    }

    private function baseBadges(
        ?LeaveRequest $leave,
        ?AttendanceSupplementRequest $supplement
    ): array {
        $badges = [];

        if ($leave) {
            $badges[] = $this->leaveTypeLabel($leave->leave_type);
        }

        if ($supplement) {
            $badges[] = 'Đã duyệt đơn';
        }

        return $badges;
    }

    private function status(
        string $key,
        string $label,
        string $subtitle,
        string $workStatus = 'none',
        ?string $workLabel = null,
        array $badges = []
    ): array {
        return [
            'key' => $key,
            'status' => $key,
            'label' => $label,
            'subtitle' => $subtitle,
            'color' => $this->colorFor($key),
            'classes' => $this->classesFor($key),
            'icon' => $this->iconFor($key),
            'work_status' => $workStatus,
            'work_label' => $workLabel ?? $label,
            'badges' => array_values(array_filter($badges)),
        ];
    }

    private function canMarkAbsentWithoutLeave(
        Carbon $workDate,
        Shift $shift
    ): bool {
        $today = today();

        if ($workDate->greaterThan($today)) {
            return false;
        }

        $checkinDeadline = $this
            ->scheduledShiftStart($shift, $workDate)
            ->addMinutes(
                max(
                    0,
                    (int) (
                        $shift->checkin_close_after_minutes
                        ?? 240
                    )
                )
            );

        return now()->greaterThan($checkinDeadline);
    }

    private function checkoutDeadline(
        Carbon $workDate,
        Shift $shift
    ): Carbon {
        $cutoff = $this->overnightCutoffTime();

        /*
         * Quy tắc chung cho tất cả ca:
         * hạn checkout là 08:50 sáng hôm sau.
         */
        $configuredCutoff = $workDate
            ->copy()
            ->startOfDay()
            ->addDay()
            ->setTime(
                $cutoff['hour'],
                $cutoff['minute']
            );

        $scheduledEnd = $this->scheduledShiftEnd(
            $shift,
            $workDate
        );

        /*
         * Nếu ca đặc biệt kết thúc sau 08:50,
         * không để deadline sớm hơn giờ kết thúc ca.
         */
        return $configuredCutoff->greaterThan($scheduledEnd)
            ? $configuredCutoff
            : $scheduledEnd;
    }

    private function unassignedCheckoutDeadline(
        Carbon $workDate
    ): Carbon {
        $cutoff = $this->overnightCutoffTime();

        return $workDate
            ->copy()
            ->startOfDay()
            ->addDay()
            ->setTime(
                $cutoff['hour'],
                $cutoff['minute']
            );
    }

    private function lateMinutes(
        Attendance $attendance,
        ?Shift $shift,
        Carbon $workDate
    ): int {
        if (!$shift || !$attendance->checkin_at) {
            return 0;
        }

        $attendanceWorkDate = $attendance->work_date
            ? Carbon::parse($attendance->work_date)
            : $workDate;

        return AttendanceLateCalculator::minutes(
            $shift,
            $attendanceWorkDate,
            $attendance->checkin_at
        );
    }

    private function earlyLeaveMinutes(
        Attendance $attendance,
        Shift $shift,
        Carbon $workDate
    ): int {
        if (!$attendance->checkout_at) {
            return 0;
        }

        $attendanceWorkDate = $attendance->work_date
            ? Carbon::parse($attendance->work_date)
            : $workDate;

        $scheduledEnd = $this->scheduledShiftEnd(
            $shift,
            $attendanceWorkDate
        );

        $checkout = Carbon::parse($attendance->checkout_at);

        return $checkout->lessThan($scheduledEnd)
            ? (int) $checkout->diffInMinutes($scheduledEnd)
            : 0;
    }

    private function workDayFromActualTimes(
        Attendance $attendance,
        Shift $shift,
        Carbon $workDate
    ): float {
        if (
            !$attendance->checkin_at
            || !$attendance->checkout_at
        ) {
            return 0.0;
        }

        $attendanceWorkDate = $attendance->work_date
            ? Carbon::parse(
                $attendance->work_date
            )->startOfDay()
            : $workDate->copy()->startOfDay();

        $shiftStart = $this->scheduledShiftStart(
            $shift,
            $attendanceWorkDate
        );

        $shiftEnd = $this->scheduledShiftEnd(
            $shift,
            $attendanceWorkDate
        );

        $checkin = Carbon::parse(
            $attendance->checkin_at
        );

        $checkout = Carbon::parse(
            $attendance->checkout_at
        );

        $workStart = $checkin->greaterThan(
            $shiftStart
        )
            ? $checkin
            : $shiftStart;

        $workEnd = $checkout->lessThan(
            $shiftEnd
        )
            ? $checkout
            : $shiftEnd;

        $workedMinutes = $workEnd->greaterThan(
            $workStart
        )
            ? (int) $workStart->diffInMinutes(
                $workEnd
            )
            : 0;

        $requiredMinutes = max(
            1,
            (int) $shiftStart->diffInMinutes(
                $shiftEnd
            )
        );

        return round(
            min(
                $workedMinutes / $requiredMinutes,
                1
            ),
            2
        );
    }

    private function scheduledShiftStart(
        Shift $shift,
        Carbon $workDate
    ): Carbon {
        return $this->shiftDateTimeFor(
            $shift,
            $workDate,
            'start_at'
        );
    }

    private function scheduledShiftEnd(
        Shift $shift,
        Carbon $workDate
    ): Carbon {
        return $this->shiftDateTimeFor(
            $shift,
            $workDate,
            'end_at'
        );
    }

    private function shiftDateTimeFor(
        Shift $shift,
        Carbon $baseDate,
        string $field
    ): Carbon {
        $time = Carbon::parse($shift->{$field});

        $dateTime = $baseDate
            ->copy()
            ->setTime(
                (int) $time->format('H'),
                (int) $time->format('i'),
                (int) $time->format('s')
            );

        if (
            $field === 'end_at'
            && $this->isOvernightShift($shift)
        ) {
            $dateTime->addDay();
        }

        return $dateTime;
    }

    private function isOvernightShift(
        Shift $shift
    ): bool {
        $start = Carbon::parse($shift->start_at);
        $end = Carbon::parse($shift->end_at);

        return $end->format('H:i:s')
            <= $start->format('H:i:s');
    }

    private function overnightCutoffTime(): array
    {
        $value = (string) Setting::getValue(
            'overnight_cutoff_time',
            '08:50'
        );

        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            return ['hour' => 8, 'minute' => 50];
        }

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $value)
        );

        if (
            $hour < 0
            || $hour > 23
            || $minute < 0
            || $minute > 59
        ) {
            return ['hour' => 8, 'minute' => 50];
        }

        return [
            'hour' => $hour,
            'minute' => $minute,
        ];
    }

    private function leaveTypeLabel(?string $type): string
    {
        /*
         * ChamCongV2 chỉ có một loại đơn nghỉ hợp lệ: nghỉ có phép.
         * Nghỉ không phép không phải LeaveRequest; trạng thái đó được
         * suy ra khi nhân sự có ca nhưng vắng và không có phép đã duyệt.
         *
         * Dữ liệu lịch sử có leave_type cũ vẫn hiển thị thống nhất là
         * "Nghỉ có phép" nếu bản ghi đã được truyền vào đây như một phép hợp lệ.
         */
        return 'Nghỉ có phép';
    }

    private function colorFor(string $key): string
    {
        return [
            'upcoming' => 'indigo',
            'not_started' => 'slate',
            'leave_approved' => 'violet',
            'absent_without_leave' => 'red',
            'not_checked_in_yet' => 'slate',
            'no_shift' => 'gray',
            'working' => 'sky',
            'full_day' => 'emerald',
            'late' => 'orange',
            'early_leave' => 'amber',
            'missing_checkout' => 'rose',
            'missing_checkin' => 'fuchsia',
            'insufficient_work' => 'amber',
            'pending' => 'slate',
            'unknown' => 'slate',
        ][$key] ?? 'slate';
    }

    private function classesFor(string $key): string
    {
        return [
            'upcoming' =>
                'bg-indigo-50 text-indigo-600 border-indigo-200',

            'not_started' =>
                'bg-slate-100 text-slate-700 border-slate-200',

            'leave_approved' =>
                'bg-violet-100 text-violet-800 border-violet-200',

            'absent_without_leave' =>
                'bg-red-100 text-red-800 border-red-200',

            'not_checked_in_yet' =>
                'bg-slate-100 text-slate-700 border-slate-200',

            'no_shift' =>
                'bg-gray-100 text-gray-700 border-gray-200',

            'working' =>
                'bg-sky-100 text-sky-800 border-sky-200',

            'full_day' =>
                'bg-emerald-100 text-emerald-800 border-emerald-200',

            'late' =>
                'bg-orange-100 text-orange-800 border-orange-200',

            'early_leave' =>
                'bg-amber-100 text-amber-800 border-amber-200',

            'missing_checkout' =>
                'bg-rose-100 text-rose-900 border-rose-300',

            'missing_checkin' =>
                'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200',

            'insufficient_work' =>
                'bg-amber-100 text-amber-800 border-amber-200',

            'pending' =>
                'bg-slate-100 text-slate-700 border-slate-200',

            'unknown' =>
                'bg-slate-100 text-slate-700 border-slate-200',
        ][$key] ?? 'bg-slate-100 text-slate-700 border-slate-200';
    }

    private function iconFor(string $key): string
    {
        return [
            'upcoming' => 'calendar-clock',
            'not_started' => 'user-minus',
            'leave_approved' => 'calendar-check',
            'absent_without_leave' => 'alert-triangle',
            'not_checked_in_yet' => 'clock',
            'no_shift' => 'calendar-off',
            'working' => 'activity',
            'full_day' => 'check-circle',
            'late' => 'clock-alert',
            'early_leave' => 'log-out',
            'missing_checkout' => 'x-circle',
            'missing_checkin' => 'log-in',
            'insufficient_work' => 'alert-circle',
            'pending' => 'clock',
            'unknown' => 'circle',
        ][$key] ?? 'circle';
    }
}