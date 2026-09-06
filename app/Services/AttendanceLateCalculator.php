<?php

namespace App\Services;

use App\Models\Shift;
use Carbon\Carbon;

final class AttendanceLateCalculator
{
    /**
     * Quy tắc đi trễ dùng chung cho toàn hệ thống.
     *
     * Ví dụ ca 10:00, late_after_minutes = 3:
     * - 10:00 -> 10:03: không trễ.
     * - 10:04: trễ 4 phút.
     * - 10:10: trễ 10 phút.
     *
     * late_after_minutes chỉ là ngưỡng để quyết định có bị tính trễ hay không.
     * Khi đã vượt ngưỡng, số phút trễ được tính từ giờ bắt đầu ca.
     */
    public static function minutes(
        Shift $shift,
        Carbon $workDate,
        Carbon|string|null $checkin
    ): int {
        if (!$checkin) {
            return 0;
        }

        $startTime = Carbon::parse($shift->start_at);

        // Hệ thống chấm công theo phút, bỏ phần giây để tránh 10:03:30
        // bị xem là vượt ngưỡng 3 phút của ca 10:00.
        $scheduledStart = $workDate
            ->copy()
            ->startOfDay()
            ->setTime(
                (int) $startTime->format('H'),
                (int) $startTime->format('i'),
                0
            );

        $checkinMinute = Carbon::parse($checkin)
            ->copy()
            ->startOfMinute();

        $graceMinutes = max(
            0,
            (int) ($shift->late_after_minutes ?? 0)
        );

        $allowedLateTime = $scheduledStart
            ->copy()
            ->addMinutes($graceMinutes);

        if ($checkinMinute->lessThanOrEqualTo($allowedLateTime)) {
            return 0;
        }

        return max(
            0,
            (int) $scheduledStart->diffInMinutes($checkinMinute)
        );
    }
}
