<?php

namespace App\Services;

use App\Models\ShiftAssignment;
use Carbon\Carbon;

class AttendanceShiftResolver
{
    public function __construct(private readonly ShiftScheduleService $schedule)
    {
    }

    public function resolveForUser(
        $user,
        Carbon $now,
        bool $strictMode
    ): ?ShiftAssignment {
        if (!$user) {
            return null;
        }

        $todayDate = $now->copy()->startOfDay();
        $previousDate = $todayDate
            ->copy()
            ->subDay();

        /*
         * Phân ca theo đúng ngày luôn được ưu tiên.
         *
         * Nếu ngày đó không có bản phân ca riêng thì mới
         * dùng phân ca mặc định work_date = null gần nhất
         * đã tồn tại trước ngày công.
         */
        $today = $this->effectiveAssignmentForDate(
            (int) $user->id,
            $todayDate
        );

        $previous = $this->effectiveAssignmentForDate(
            (int) $user->id,
            $previousDate
        );

        /*
         * Ưu tiên ca hôm nay khi hiện tại đang nằm trong
         * cửa sổ chấm công của chính ca hôm nay.
         */
        if (
            $today?->shift
            && $this->contains(
                $today,
                $now,
                $strictMode
            )
        ) {
            return $today;
        }

        /*
         * Sau nửa đêm, nếu ca của ngày hôm trước vẫn còn
         * hiệu lực thì phải giữ work_date của ngày hôm trước.
         */
        if (
            $previous?->shift
            && $this->contains(
                $previous,
                $now,
                $strictMode
            )
        ) {
            return $previous;
        }

        /*
         * Giữ hành vi cũ: nếu có phân ca của hôm nay nhưng
         * hiện tại nằm ngoài cửa sổ active, vẫn trả ca hôm nay
         * để các lớp phía trên quyết định checkin/nhắc nhở.
         */
        return $today;
    }

    /**
     * Trả về phân ca có hiệu lực cho một ngày công.
     *
     * work_date = null là phân ca mặc định. Khi dùng làm
     * phân ca hiệu lực, clone model và gắn work_date tạm thời
     * trong bộ nhớ để các resolver phía trên luôn có ngày công
     * cụ thể mà không sửa bản ghi phân ca trong database.
     */
    private function effectiveAssignmentForDate(
        int $userId,
        Carbon $workDate
    ): ?ShiftAssignment {
        $datedAssignment = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->whereDate(
                'work_date',
                $workDate->toDateString()
            )
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($datedAssignment) {
            return $datedAssignment;
        }

        $defaultAssignment = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->whereNull('work_date')
            ->where(
                'created_at',
                '<=',
                $workDate
                    ->copy()
                    ->endOfDay()
            )
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (!$defaultAssignment) {
            return null;
        }

        $effective = clone $defaultAssignment;

        $effective->setAttribute(
            'work_date',
            $workDate->toDateString()
        );

        return $effective;
    }

    private function contains(ShiftAssignment $assignment, Carbon $now, bool $strictMode): bool
    {
        if (!$assignment->shift || !$assignment->work_date) {
            return false;
        }

        $workDate = Carbon::parse($assignment->work_date);
        $segments = $this->schedule->scheduledSegments($assignment->shift, $workDate);

        if ($segments->isEmpty()) {
            return false;
        }

        $startsAt = $segments->first()['start_at']->copy()
            ->subMinutes(max(0, (int) ($assignment->shift->checkin_open_before_minutes ?? 60)));
        $endsAt = $this->attendanceWindowEnd($assignment, $workDate, $strictMode);

        return $now->betweenIncluded($startsAt, $endsAt);
    }

    private function attendanceWindowEnd(ShiftAssignment $assignment, Carbon $workDate, bool $strictMode): Carbon
    {
        $segments = $this->schedule->scheduledSegments($assignment->shift, $workDate);
        $finalEnd = $segments->last()['end_at']->copy();

        if ($finalEnd->toDateString() !== $workDate->toDateString()) {
            return $this->schedule->checkoutDeadline($assignment->shift, $workDate);
        }

        if ($strictMode && $assignment->shift->checkout_close_after_shift_end_minutes !== null) {
            return $finalEnd->addMinutes((int) $assignment->shift->checkout_close_after_shift_end_minutes);
        }

        return $finalEnd->endOfDay();
    }
}