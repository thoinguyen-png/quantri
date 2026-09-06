<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftScheduleService
{
    /**
     * Hệ thống hiện chỉ dùng một khoảng ca:
     * shifts.start_at -> shifts.end_at.
     *
     * Bảng shift_segments được giữ nguyên để bảo toàn dữ liệu cũ,
     * nhưng không còn tham gia vào luồng chấm công mới.
     */
    public function hasStoredSegments(Shift $shift): bool
    {
        return false;
    }

    /**
     * Trả về đúng một khoảng làm việc tuyệt đối cho ngày công.
     */
    public function scheduledSegments(
        Shift $shift,
        Carbon $workDate
    ): Collection {
        $start = $this->timeOnDate(
            $workDate,
            $shift->start_at
        );

        $end = $this->timeOnDate(
            $workDate,
            $shift->end_at
        );

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return collect([[
            'segment_order' => 1,
            'start_at' => $start,
            'end_at' => $end,
        ]]);
    }

    public function requiredMinutes(
        Shift $shift,
        Carbon $workDate
    ): int {
        $scheduled = $this->scheduledSegments(
            $shift,
            $workDate
        );

        return max(
            1,
            (int) $scheduled->sum(
                fn (array $segment): int =>
                    (int) $segment['start_at']
                        ->diffInMinutes(
                            $segment['end_at']
                        )
            )
        );
    }

    /**
     * Hạn checkout chung:
     * 08:50 gần nhất sau khi ca kết thúc.
     *
     * Ví dụ:
     * - Ca 10:00-22:00 ngày 03: checkout tới 08:50 ngày 04.
     * - Ca 17:00-04:00 ngày 03: checkout tới 08:50 ngày 04.
     */
    public function checkoutDeadline(
        Shift $shift,
        Carbon $workDate
    ): Carbon {
        $scheduledEnd = $this
            ->scheduledSegments($shift, $workDate)
            ->last()['end_at']
            ->copy();

        $cutoff = $this->overnightCutoffTime();

        $deadline = $scheduledEnd
            ->copy()
            ->setTime(
                $cutoff['hour'],
                $cutoff['minute'],
                0
            );

        if ($deadline->lessThan($scheduledEnd)) {
            $deadline->addDay();
        }

        return $deadline;
    }

    private function timeOnDate(
        Carbon $date,
        mixed $time
    ): Carbon {
        $parsed = Carbon::parse($time);

        return $date
            ->copy()
            ->startOfDay()
            ->setTime(
                (int) $parsed->format('H'),
                (int) $parsed->format('i'),
                (int) $parsed->format('s')
            );
    }

    private function overnightCutoffTime(): array
    {
        $value = (string) Setting::getValue(
            'overnight_cutoff_time',
            '08:50'
        );

        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            return [
                'hour' => 8,
                'minute' => 50,
            ];
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
            return [
                'hour' => 8,
                'minute' => 50,
            ];
        }

        return compact('hour', 'minute');
    }
}
