<?php

namespace App\Services;

use App\Models\CustomerRating;
use Carbon\Carbon;

class RatingFraudRiskService
{
    public const REASON_DUPLICATE_IP_EMPLOYEE_15M = 'duplicate_ip_employee_15m';
    public const REASON_REPEATED_IP_EMPLOYEE_60M = 'repeated_ip_employee_60m';
    public const REASON_MANY_EMPLOYEES_SAME_IP_30M = 'many_employees_same_ip_30m';
    public const REASON_BURST_GOOD_RATING_15M = 'burst_good_rating_15m';

    public function reasons(
        int $employeeId,
        string $rating,
        ?string $ipHash,
        Carbon $submittedAt,
        array $settingsSnapshot
    ): array {
        $reasons = [];

        if ($ipHash !== null) {
            $reasons = array_merge(
                $reasons,
                $this->ipEmployeeReasons($employeeId, $ipHash, $submittedAt, $settingsSnapshot),
                $this->manyEmployeesSameIpReasons($employeeId, $ipHash, $submittedAt, $settingsSnapshot)
            );
        }

        if ($rating === CustomerRating::RATING_GOOD) {
            $reasons = array_merge(
                $reasons,
                $this->burstGoodReasons($employeeId, $submittedAt, $settingsSnapshot)
            );
        }

        return array_values(array_unique($reasons));
    }

    private function ipEmployeeReasons(int $employeeId, string $ipHash, Carbon $submittedAt, array $settingsSnapshot): array
    {
        $reasons = [];
        $shortWindowMinutes = max(1, (int) $settingsSnapshot['rating_risk_ip_employee_window_minutes']);
        $repeatWindowMinutes = max(1, (int) $settingsSnapshot['rating_risk_ip_employee_repeat_window_minutes']);
        $repeatThreshold = max(1, (int) $settingsSnapshot['rating_risk_ip_employee_repeat_threshold']);

        $shortWindowCount = CustomerRating::query()
            ->where('ip_hash', $ipHash)
            ->where('employee_id', $employeeId)
            ->where('submitted_at', '>=', $submittedAt->copy()->subMinutes($shortWindowMinutes))
            ->where('submitted_at', '<', $submittedAt)
            ->count();

        if ($shortWindowCount >= 1) {
            $reasons[] = self::REASON_DUPLICATE_IP_EMPLOYEE_15M;
        }

        $repeatWindowCount = CustomerRating::query()
            ->where('ip_hash', $ipHash)
            ->where('employee_id', $employeeId)
            ->where('submitted_at', '>=', $submittedAt->copy()->subMinutes($repeatWindowMinutes))
            ->where('submitted_at', '<', $submittedAt)
            ->count();

        if ($repeatWindowCount >= ($repeatThreshold - 1)) {
            $reasons[] = self::REASON_REPEATED_IP_EMPLOYEE_60M;
        }

        return $reasons;
    }

    private function manyEmployeesSameIpReasons(int $employeeId, string $ipHash, Carbon $submittedAt, array $settingsSnapshot): array
    {
        $windowMinutes = max(1, (int) $settingsSnapshot['rating_risk_many_employees_ip_window_minutes']);
        $threshold = max(1, (int) $settingsSnapshot['rating_risk_many_employees_ip_threshold']);

        $employeeIds = CustomerRating::query()
            ->where('ip_hash', $ipHash)
            ->where('submitted_at', '>=', $submittedAt->copy()->subMinutes($windowMinutes))
            ->where('submitted_at', '<', $submittedAt)
            ->distinct()
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id);

        if (!$employeeIds->contains($employeeId)) {
            $employeeIds->push($employeeId);
        }

        return $employeeIds->unique()->count() >= $threshold
            ? [self::REASON_MANY_EMPLOYEES_SAME_IP_30M]
            : [];
    }

    private function burstGoodReasons(int $employeeId, Carbon $submittedAt, array $settingsSnapshot): array
    {
        $windowMinutes = max(1, (int) $settingsSnapshot['rating_risk_burst_good_window_minutes']);
        $threshold = max(1, (int) $settingsSnapshot['rating_risk_burst_good_threshold']);

        $goodCount = CustomerRating::query()
            ->where('employee_id', $employeeId)
            ->where('rating', CustomerRating::RATING_GOOD)
            ->where('submitted_at', '>=', $submittedAt->copy()->subMinutes($windowMinutes))
            ->where('submitted_at', '<', $submittedAt)
            ->count();

        return $goodCount >= ($threshold - 1)
            ? [self::REASON_BURST_GOOD_RATING_15M]
            : [];
    }
}
