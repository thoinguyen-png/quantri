<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkHistory;

class EmploymentStatusService
{
    public const DEFAULT_REQUIRED_WORKDAYS = 3;

    public function requiredWorkdays(): int
    {
        return Setting::getInt('probation_required_workdays', self::DEFAULT_REQUIRED_WORKDAYS);
    }

    public function autoPromoteEnabled(): bool
    {
        return Setting::getBool('probation_auto_promote_enabled', true);
    }

    public function validWorkdayCount(User $user): int
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('checkin_at')
            ->whereNotNull('checkout_at')
            ->where('worked_minutes', '>', 0)
            ->selectRaw('COUNT(DISTINCT COALESCE(work_date, DATE(checkin_at))) as workday_count')
            ->value('workday_count') ?? 0;
    }

    public function promoteIfEligible(User $user): bool
    {
        if (! $this->autoPromoteEnabled()) {
            return false;
        }

        if ($user->status !== 'thu_viec') {
            return false;
        }

        if ($this->validWorkdayCount($user) < $this->requiredWorkdays()) {
            return false;
        }

        $promoted = $user->forceFill([
            'status' => 'chinh_thuc',
            'official_at' => now()->toDateString(),
            'official_by' => null,
            'status_changed_by' => null,
        ])->save();

        if ($promoted) {
            WorkHistory::create([
                'user_id' => $user->id,
                'old_branch_id' => $user->branch_id,
                'new_branch_id' => $user->branch_id,
                'old_department_id' => null,
                'new_department_id' => null,
                'old_role' => $user->role,
                'new_role' => $user->role,
                'old_status' => 'thu_viec',
                'new_status' => 'chinh_thuc',
                'effective_date' => today()->toDateString(),
                'changed_by' => null,
                'note' => 'He thong tu dong chuyen chinh thuc khi du ngay cong hop le.',
            ]);
        }

        return $promoted;
    }
}
