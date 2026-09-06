<?php

namespace App\Services;

use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingRiskReviewService
{
    public function __construct(
        private readonly RewardCalculationService $rewards
    ) {
    }

    public function approve(CustomerRating $rating, User $actor, ?string $note = null): CustomerRating
    {
        return DB::transaction(function () use ($rating, $actor, $note) {
            $lockedRating = CustomerRating::query()
                ->with('reward')
                ->whereKey($rating->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRating->risk_status !== CustomerRating::RISK_PENDING_REVIEW) {
                return $lockedRating->fresh('reward');
            }

            $lockedRating->forceFill([
                'risk_status' => CustomerRating::RISK_APPROVED_MANUAL,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            if ($lockedRating->rating === CustomerRating::RATING_GOOD) {
                $this->rewards->approveRiskReviewForRating($lockedRating, $this->settingsSnapshot($lockedRating));
            }

            return $lockedRating->fresh('reward');
        });
    }

    public function reject(CustomerRating $rating, User $actor, ?string $note = null): CustomerRating
    {
        return DB::transaction(function () use ($rating, $actor, $note) {
            $lockedRating = CustomerRating::query()
                ->whereKey($rating->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRating->risk_status !== CustomerRating::RISK_PENDING_REVIEW) {
                return $lockedRating->fresh('reward');
            }

            $lockedRating->forceFill([
                'risk_status' => CustomerRating::RISK_REJECTED_MANUAL,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            CustomerRatingReward::query()
                ->where('customer_rating_id', $lockedRating->id)
                ->where('status', CustomerRatingReward::STATUS_RISK_REVIEW)
                ->lockForUpdate()
                ->first()
                ?->forceFill([
                    'amount' => 0,
                    'status' => CustomerRatingReward::STATUS_REJECTED,
                    'reason_code' => 'fraud_rejected_manual',
                ])
                ->save();

            return $lockedRating->fresh('reward');
        });
    }

    private function settingsSnapshot(CustomerRating $rating): array
    {
        $ratingSnapshot = is_array($rating->settings_snapshot) ? $rating->settings_snapshot : [];

        return $ratingSnapshot + [
            'business_date' => $rating->business_date?->toDateString(),
            'attendance_id' => $rating->attendance_id,
            'branch_id' => $rating->branch_id,
            'reward_rejection_reason' => null,
        ] + Setting::customerRatingSettingsSnapshot();
    }
}
