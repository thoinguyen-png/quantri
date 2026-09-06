<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Collection;

class AttendanceMonthlyOvertimeService
{
    /**
     * Chọn duy nhất attendance mới nhất của mỗi user/ngày công.
     */
    public function latestByUserAndWorkDate(iterable $attendances): Collection
    {
        return collect($attendances)
            ->groupBy(fn (Attendance $attendance) => $this->attendanceKey($attendance))
            ->map(fn (Collection $records) => $records
                ->sort(function (Attendance $left, Attendance $right): int {
                    $timeComparison = ($right->created_at?->getTimestamp() ?? 0)
                        <=> ($left->created_at?->getTimestamp() ?? 0);

                    return $timeComparison !== 0
                        ? $timeComparison
                        : ((int) $right->id <=> (int) $left->id);
                })
                ->first());
    }

    /**
     * Tổng hợp tăng ca tháng từ phút gốc, chỉ làm tròn sau khi cộng xong.
     */
    public function totalsByUser(iterable $latestAttendances): Collection
    {
        return collect($latestAttendances)
            ->filter()
            ->groupBy('user_id')
            ->map(function (Collection $records): array {
                $minutes = (int) $records->sum(
                    fn (Attendance $attendance) => max(
                        0,
                        (int) ($attendance->overtime_minutes ?? 0)
                    )
                );

                return [
                    'overtime_minutes' => $minutes,
                    'overtime_hours' => round($minutes / 60, 1),
                ];
            });
    }

    private function attendanceKey(Attendance $attendance): string
    {
        $workDate = $attendance->work_date?->toDateString()
            ?? $attendance->checkin_at?->toDateString()
            ?? $attendance->checkout_at?->toDateString()
            ?? 'unknown-' . $attendance->id;

        return $attendance->user_id . '|' . $workDate;
    }
}
