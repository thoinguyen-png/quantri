<?php

namespace App\Services;

use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\CustomerRatingRewardCounter;
use Illuminate\Support\Facades\DB;

class RewardCalculationService
{
    public function createForRating(CustomerRating $rating, array $settingsSnapshot): ?CustomerRatingReward
    {
        if ($rating->rating !== CustomerRating::RATING_GOOD) {
            return null;
        }

        DB::table('customer_rating_reward_counters')->insertOrIgnore([
            'employee_id' => $rating->employee_id,
            'business_date' => $rating->business_date->toDateString(),
            'rewarded_good_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = CustomerRatingRewardCounter::query()
            ->where('employee_id', $rating->employee_id)
            ->whereDate('business_date', $rating->business_date->toDateString())
            ->lockForUpdate()
            ->firstOrFail();

        $maxRewarded = (int) $settingsSnapshot['max_rewarded_good_per_employee_per_business_date'];
        $amount = (int) $settingsSnapshot['good_reward_amount'];

        if ($counter->rewarded_good_count >= $maxRewarded) {
            return CustomerRatingReward::create([
                'customer_rating_id' => $rating->id,
                'employee_id' => $rating->employee_id,
                'branch_id' => $rating->branch_id,
                'attendance_id' => $rating->attendance_id,
                'business_date' => $rating->business_date,
                'amount' => 0,
                'status' => CustomerRatingReward::STATUS_REJECTED,
                'reason_code' => 'daily_cap_reached',
                'settings_snapshot' => $settingsSnapshot + ['reward_rejection_reason' => 'daily_cap_reached'],
            ]);
        }

        $counter->increment('rewarded_good_count');

        return CustomerRatingReward::create([
            'customer_rating_id' => $rating->id,
            'employee_id' => $rating->employee_id,
            'branch_id' => $rating->branch_id,
            'attendance_id' => $rating->attendance_id,
            'business_date' => $rating->business_date,
            'amount' => $amount,
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'settings_snapshot' => $settingsSnapshot,
        ]);
    }

    public function createRiskReviewForRating(CustomerRating $rating, array $settingsSnapshot): ?CustomerRatingReward
    {
        if ($rating->rating !== CustomerRating::RATING_GOOD) {
            return null;
        }

        return CustomerRatingReward::create([
            'customer_rating_id' => $rating->id,
            'employee_id' => $rating->employee_id,
            'branch_id' => $rating->branch_id,
            'attendance_id' => $rating->attendance_id,
            'business_date' => $rating->business_date,
            'amount' => (int) $settingsSnapshot['good_reward_amount'],
            'status' => CustomerRatingReward::STATUS_RISK_REVIEW,
            'reason_code' => 'fraud_review_required',
            'settings_snapshot' => $settingsSnapshot + ['reward_rejection_reason' => 'fraud_review_required'],
        ]);
    }

    public function approveRiskReviewForRating(CustomerRating $rating, array $settingsSnapshot): ?CustomerRatingReward
    {
        if ($rating->rating !== CustomerRating::RATING_GOOD) {
            return null;
        }

        $reward = CustomerRatingReward::query()
            ->where('customer_rating_id', $rating->id)
            ->lockForUpdate()
            ->first();

        if (!$reward) {
            $reward = CustomerRatingReward::create([
                'customer_rating_id' => $rating->id,
                'employee_id' => $rating->employee_id,
                'branch_id' => $rating->branch_id,
                'attendance_id' => $rating->attendance_id,
                'business_date' => $rating->business_date,
                'amount' => (int) $settingsSnapshot['good_reward_amount'],
                'status' => CustomerRatingReward::STATUS_RISK_REVIEW,
                'reason_code' => 'fraud_review_required',
                'settings_snapshot' => $settingsSnapshot + ['reward_rejection_reason' => 'fraud_review_required'],
            ]);
        }

        if ($reward->status !== CustomerRatingReward::STATUS_RISK_REVIEW) {
            return $reward;
        }

        DB::table('customer_rating_reward_counters')->insertOrIgnore([
            'employee_id' => $rating->employee_id,
            'business_date' => $rating->business_date->toDateString(),
            'rewarded_good_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = CustomerRatingRewardCounter::query()
            ->where('employee_id', $rating->employee_id)
            ->whereDate('business_date', $rating->business_date->toDateString())
            ->lockForUpdate()
            ->firstOrFail();

        $maxRewarded = (int) $settingsSnapshot['max_rewarded_good_per_employee_per_business_date'];

        if ($counter->rewarded_good_count >= $maxRewarded) {
            $reward->forceFill([
                'amount' => 0,
                'status' => CustomerRatingReward::STATUS_REJECTED,
                'reason_code' => 'reward_cap_reached_after_manual_review',
                'settings_snapshot' => $settingsSnapshot + [
                    'reward_rejection_reason' => 'reward_cap_reached_after_manual_review',
                ],
            ])->save();

            return $reward;
        }

        $counter->increment('rewarded_good_count');

        $reward->forceFill([
            'amount' => (int) $settingsSnapshot['good_reward_amount'],
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'reason_code' => null,
            'settings_snapshot' => $settingsSnapshot,
        ])->save();

        return $reward;
    }
}
