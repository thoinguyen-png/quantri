<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

class AttendanceDailyMetricsService
{
    public function __construct(
        private readonly AttendanceCalculationService $calculator
    ) {
    }

    public function calculate(
        ?Attendance $attendance,
        ?ShiftAssignment $assignment,
        Carbon $day,
        string $statusKey
    ): array {
        $metrics = [
            'work_units' => 0.0,
            'work_with_overtime' => 0.0,
            'worked_minutes' => 0,
            'required_minutes' => 0,
            'early_leave_minutes' => 0,
            'early_leave_hours' => 0.0,
            'late_minutes' => 0,
            'late_hours' => 0.0,
            'overtime_minutes' => 0,
            'overtime_hours' => 0.0,
            'unauthorized_absence_days' => 0,
            'late_days' => 0,
            'early_leave_days' => 0,
        ];

        /*
         * Không có attendance:
         * chỉ AttendanceStatusService mới quyết định
         * ngày đó có phải nghỉ không phép hay không.
         */
        if (!$attendance) {
            if ($statusKey === 'absent_without_leave') {
                $metrics['unauthorized_absence_days'] = 1;
            }

            return $metrics;
        }

        /*
         * Phân ca của đúng ngày là nguồn ca hiện hành.
         * Nếu chưa có phân ca thì mới fallback về ca snapshot
         * đang lưu trên attendance.
         */
        $shift = $assignment?->shift
            ?? $attendance->shift;

        /*
         * Nếu có ca + đủ checkin/checkout thì phải tính lại
         * trực tiếp từ giờ thực tế bằng cùng một calculator
         * với luồng chấm công và bổ sung công.
         *
         * Không tin tuyệt đối worked_minutes / overtime_minutes
         * đã lưu vì dữ liệu cũ có thể được tính theo rule trước:
         * - đi sớm từng được cộng vào giờ công;
         * - OT từng có thể bù phần công bị thiếu;
         * - attendance có thể giữ snapshot ca cũ.
         */
        $calculated = null;

        if (
            $shift
            && $attendance->checkin_at
            && $attendance->checkout_at
        ) {
            $calculated = $this->calculator
                ->recalculateForShift(
                    $attendance,
                    $shift
                );
        }

        $workedMinutes = is_array($calculated)
            ? max(
                0,
                (int) (
                    $calculated['worked_minutes']
                    ?? 0
                )
            )
            : $this->workedMinutes($attendance);

        $requiredMinutes = $shift
            ? $this->shiftRequiredMinutes(
                $shift,
                $day
            )
            : 0;

        $workUnits = $this->workUnits(
            $attendance,
            $shift,
            $statusKey,
            $workedMinutes,
            $requiredMinutes
        );

        /*
         * OT chỉ hợp lệ khi có ca và có checkout.
         * Nếu chưa có ca thì không được lấy tổng thời gian
         * checkin -> checkout làm OT.
         */
        $overtimeMinutes = is_array($calculated)
            ? max(
                0,
                (int) (
                    $calculated['overtime_minutes']
                    ?? 0
                )
            )
            : 0;

        $metrics['work_units'] = $workUnits;
        $metrics['worked_minutes'] = $workedMinutes;
        $metrics['required_minutes'] = $requiredMinutes;
        $metrics['overtime_minutes'] = $overtimeMinutes;
        $metrics['overtime_hours'] =
            $overtimeMinutes / 60;

        /*
         * Công + tăng ca quy đổi theo đúng thời lượng
         * ca của ngày đó, không mặc định ca 8 giờ.
         */
        $metrics['work_with_overtime'] =
            $workUnits;

        if (
            $requiredMinutes > 0
            && $overtimeMinutes > 0
        ) {
            $metrics['work_with_overtime'] +=
                $overtimeMinutes
                / $requiredMinutes;
        }

        /*
         * Thiếu ca hoặc thiếu checkin:
         * không suy diễn trễ, về sớm hay OT.
         */
        if (
            !$shift
            || !$attendance->checkin_at
        ) {
            return $metrics;
        }

        $shiftStart = $this->shiftDateTimeFor(
            $shift,
            $day,
            'start_at'
        );

        $shiftEnd = $this->shiftDateTimeFor(
            $shift,
            $day,
            'end_at'
        );

        $checkin = Carbon::parse(
            $attendance->checkin_at
        );

        /*
         * late_after_minutes chỉ là ngưỡng xác định có đi trễ.
         * Khi vượt ngưỡng, tính toàn bộ số phút từ giờ bắt đầu ca.
         * Ví dụ ca 10:00, cho phép 3 phút: 10:03 = 0, 10:04 = 4.
         */
        $lateMinutes = AttendanceLateCalculator::minutes(
            $shift,
            $day,
            $checkin
        );

        if ($lateMinutes > 0) {
            $metrics['late_minutes'] =
                $lateMinutes;

            $metrics['late_hours'] =
                $lateMinutes / 60;

            $metrics['late_days'] = 1;
        }

        if (
            $attendance->checkout_at
            && Carbon::parse(
                $attendance->checkout_at
            )->lessThan($shiftEnd)
        ) {
            $earlyMinutes = (int) Carbon::parse(
                $attendance->checkout_at
            )->diffInMinutes($shiftEnd);

            $metrics['early_leave_minutes'] =
                $earlyMinutes;

            $metrics['early_leave_hours'] =
                $earlyMinutes / 60;

            $metrics['early_leave_days'] = 1;
        }

        return $metrics;
    }

    private function workUnits(
        Attendance $attendance,
        $shift,
        string $statusKey,
        int $workedMinutes,
        int $requiredMinutes
    ): float {
        if (
            $statusKey === 'missing_checkout'
            && $shift
        ) {
            return 0.5;
        }

        if (
            $statusKey === 'missing_checkin'
            || !$shift
            || !$attendance->checkin_at
            || !$attendance->checkout_at
            || $workedMinutes <= 0
            || $requiredMinutes <= 0
        ) {
            return 0.0;
        }

        /*
         * Giữ đúng cách bảng công hiện tại:
         * tính tỷ lệ theo thời lượng ca và tối đa 1 công
         * cho phần công chính của một ca.
         */
        return round(
            min(
                $workedMinutes / $requiredMinutes,
                1
            ),
            2
        );
    }

    private function workedMinutes(
        Attendance $attendance
    ): int {
        /*
         * Đây chỉ là fallback khi không thể tính theo ca.
         *
         * Với attendance có ca + đủ checkin/checkout,
         * calculate() đã dùng AttendanceCalculationService
         * phía trên nên không đi vào nhánh này.
         *
         * Attendance chưa có ca vẫn có thể giữ tổng thời gian
         * thực tế để đối chiếu lịch sử, nhưng work_units = 0
         * và overtime_minutes = 0.
         */
        if (
            !$attendance->checkin_at
            || !$attendance->checkout_at
        ) {
            return 0;
        }

        return max(
            0,
            (int) Carbon::parse(
                $attendance->checkin_at
            )->diffInMinutes(
                Carbon::parse(
                    $attendance->checkout_at
                )
            )
        );
    }

    private function shiftRequiredMinutes(
        $shift,
        Carbon $day
    ): int {
        return max(
            1,
            (int) $this->shiftDateTimeFor(
                $shift,
                $day,
                'start_at'
            )->diffInMinutes(
                $this->shiftDateTimeFor(
                    $shift,
                    $day,
                    'end_at'
                )
            )
        );
    }

    private function shiftDateTimeFor(
        $shift,
        Carbon $day,
        string $field
    ): Carbon {
        $time = Carbon::parse(
            $shift->{$field}
        );

        $dateTime = $day->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at') {
            $start = Carbon::parse(
                $shift->start_at
            );

            $end = Carbon::parse(
                $shift->end_at
            );

            if (
                $end->format('H:i:s')
                <= $start->format('H:i:s')
            ) {
                $dateTime->addDay();
            }
        }

        return $dateTime;
    }
}