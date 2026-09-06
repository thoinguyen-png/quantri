<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceCalculationService
{
    public function recalculateForShift(
        Attendance $attendance,
        Shift $shift
    ): array {
        $workDate = $this->resolveWorkDate(
            $attendance
        );

        $data = [
            'shift_id' => $shift->id,
        ];

        $checkin = $attendance->checkin_at
            ? Carbon::parse(
                $attendance->checkin_at
            )
            : null;

        $checkout = $attendance->checkout_at
            ? Carbon::parse(
                $attendance->checkout_at
            )
            : null;

        /*
         * Có checkout nhưng không có checkin.
         *
         * Chưa thể tính công, đi trễ hoặc OT.
         */
        if (!$checkin && $checkout) {
            return $this->missingCheckinData(
                $data
            );
        }

        /*
         * Có checkin nhưng chưa checkout.
         *
         * Chỉ ghi 0,5 công khi attendance đã được
         * luồng khác đánh dấu missing_checkout.
         */
        if ($checkin && !$checkout) {
            if (
                $attendance->status ===
                'missing_checkout'
            ) {
                return $this->missingCheckoutData(
                    $data
                );
            }

            return $data;
        }

        /*
         * Chưa có đủ cả checkin và checkout.
         */
        if (!$checkin || !$checkout) {
            return $data;
        }

        /*
         * Hệ thống hiện tại chỉ dùng một ca liên tục:
         * start_at -> end_at.
         *
         * Nếu end_at <= start_at thì đây là ca qua đêm
         * và end_at thuộc ngày hôm sau.
         */
        $shiftStart = $this->shiftDateTimeFor(
            $shift,
            $workDate,
            'start_at'
        );

        $shiftEnd = $this->shiftDateTimeFor(
            $shift,
            $workDate,
            'end_at'
        );

        $shiftMinutes = max(
            1,
            (int) $shiftStart->diffInMinutes(
                $shiftEnd
            )
        );

        /*
         * Giờ công chính chỉ tính bên trong khung giờ ca.
         *
         * Ví dụ ca 10:00 -> 22:00:
         *
         * - Checkin 09:00, checkout 21:30
         *   => worked_minutes = 690.
         *
         * - Checkin 10:30, checkout 23:00
         *   => worked_minutes = 690.
         *
         * Phần đến sớm không được cộng công.
         * Phần sau giờ kết thúc ca được tách riêng thành OT.
         */
        $workStart = $checkin->greaterThan(
            $shiftStart
        )
            ? $checkin->copy()
            : $shiftStart->copy();

        $workEnd = $checkout->lessThan(
            $shiftEnd
        )
            ? $checkout->copy()
            : $shiftEnd->copy();

        $workedMinutes =
            $workEnd->greaterThan($workStart)
            ? (int) $workStart->diffInMinutes(
                $workEnd
            )
            : 0;

        /*
         * late_after_minutes chỉ là ngưỡng xác định
         * có bị tính đi trễ hay không.
         *
         * Khi vượt ngưỡng, tính toàn bộ số phút
         * từ giờ bắt đầu ca.
         */
        $lateMinutes =
            AttendanceLateCalculator::minutes(
                $shift,
                $workDate,
                $checkin
            );

        /*
         * OT chỉ tính thời gian thực tế làm
         * sau giờ kết thúc ca.
         *
         * Nếu nhân sự checkin sau cả giờ kết thúc ca,
         * OT chỉ bắt đầu từ thời điểm checkin thực tế.
         */
        $overtimeStart =
            $checkin->greaterThan($shiftEnd)
            ? $checkin->copy()
            : $shiftEnd->copy();

        $overtimeMinutes =
            $checkout->greaterThan(
                $overtimeStart
            )
            ? (int) $overtimeStart
                ->diffInMinutes($checkout)
            : 0;

        $data += [
            'late_minutes' => $lateMinutes,
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' =>
                $overtimeMinutes,
            'status' => 'completed',
        ];

        if (
            Schema::hasColumn(
                'attendances',
                'overtime_hours'
            )
        ) {
            $data['overtime_hours'] = round(
                $overtimeMinutes / 60,
                2
            );
        }

        if (
            Schema::hasColumn(
                'attendances',
                'work_day'
            )
        ) {
            $data['work_day'] = $this->workDay(
                $workedMinutes,
                $shiftMinutes
            );
        }

        if (
            Schema::hasColumn(
                'attendances',
                'penalty_workday'
            )
        ) {
            $data['penalty_workday'] =
                $lateMinutes > 120
                ? 0.5
                : 0;
        }

        return $data;
    }

    /**
     * Có checkout nhưng thiếu checkin.
     */
    private function missingCheckinData(
        array $data
    ): array {
        $data += [
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'missing_checkin',
        ];

        if (
            Schema::hasColumn(
                'attendances',
                'overtime_hours'
            )
        ) {
            $data['overtime_hours'] = 0;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'work_day'
            )
        ) {
            $data['work_day'] = 0;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'penalty_workday'
            )
        ) {
            $data['penalty_workday'] = 0;
        }

        return $data;
    }

    /**
     * Có checkin nhưng đã quá hạn checkout.
     */
    private function missingCheckoutData(
        array $data
    ): array {
        $data += [
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'missing_checkout',
        ];

        if (
            Schema::hasColumn(
                'attendances',
                'overtime_hours'
            )
        ) {
            $data['overtime_hours'] = 0;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'work_day'
            )
        ) {
            $data['work_day'] = 0.5;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'penalty_workday'
            )
        ) {
            $data['penalty_workday'] = 0;
        }

        return $data;
    }

    private function workDay(
        int $workedMinutes,
        int $shiftMinutes
    ): float {
        if (
            $workedMinutes <= 0
            || $shiftMinutes <= 0
        ) {
            return 0.0;
        }

        /*
         * worked_minutes đã chỉ chứa phần thời gian
         * thực tế làm nằm trong khung giờ ca.
         *
         * Vì vậy:
         * - đi sớm không làm tăng công;
         * - OT không bù giờ công bị thiếu;
         * - công tối đa là 1.
         */
        return round(
            min(
                $workedMinutes / $shiftMinutes,
                1
            ),
            2
        );
    }

    private function resolveWorkDate(
        Attendance $attendance
    ): Carbon {
        if ($attendance->work_date) {
            return Carbon::parse(
                $attendance->work_date
            )->startOfDay();
        }

        if ($attendance->checkin_at) {
            return Carbon::parse(
                $attendance->checkin_at
            )->startOfDay();
        }

        if ($attendance->checkout_at) {
            return Carbon::parse(
                $attendance->checkout_at
            )->startOfDay();
        }

        return now()->startOfDay();
    }

    private function shiftDateTimeFor(
        Shift $shift,
        Carbon $baseDate,
        string $field
    ): Carbon {
        $time = Carbon::parse(
            $shift->{$field}
        );

        $dateTime = $baseDate
            ->copy()
            ->setTime(
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