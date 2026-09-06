<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAdjustmentLog;

class AttendanceAdjustmentLogger
{
    public function log(Attendance $attendance, string $action, ?array $oldValues = null, ?array $newValues = null, ?string $note = null): void
    {
        AttendanceAdjustmentLog::create([
            'attendance_id' => $attendance->exists ? $attendance->id : null,
            'user_id' => $attendance->user_id,
            'work_date' => $attendance->work_date?->toDateString() ?? $attendance->checkin_at?->toDateString(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => auth()->id(),
            'note' => $note,
        ]);
    }
}
