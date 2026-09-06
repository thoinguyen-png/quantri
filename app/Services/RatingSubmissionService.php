<?php

namespace App\Services;

use App\Exceptions\RatingSubmissionException;
use App\Models\CustomerRating;
use App\Models\RatingQrToken;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RatingSubmissionService
{
    public function __construct(
        private readonly RatingQrService $ratingQr,
        private readonly GuestBrowserTokenService $guestBrowser,
        private readonly RatingEligibilityService $eligibility,
        private readonly RewardCalculationService $rewards,
        private readonly RatingFraudRiskService $fraudRisk,
        private readonly CustomerRatingNotificationService $notifications
    ) {
    }

    public function submit(
        string $qrToken,
        string $guestBrowserToken,
        string $rating,
        ?string $comment = null,
        ?Carbon $now = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $routeType = 'service'
    ): CustomerRating {
        $now ??= now();
        $rating = $this->normalizeRating($rating);
        $guestBrowserHash = $this->guestBrowser->hashToken($guestBrowserToken);
        $ipHash = $this->guestBrowser->hashNullable($ipAddress);
        $userAgentHash = $this->guestBrowser->hashNullable($userAgent);

        $customerRating = DB::transaction(function () use ($qrToken, $guestBrowserHash, $rating, $comment, $now, $ipHash, $userAgentHash, $routeType) {
            $token = RatingQrToken::with('user')
                ->where('token_hash', $this->ratingQr->hashToken($qrToken))
                ->lockForUpdate()
                ->first();

            if (!$token) {
                throw new RatingSubmissionException('qr_invalid', 'QR danh gia khong hop le.');
            }

            $context = $this->eligibility->resolve($token, $now);
            $attendance = $context['attendance'];
            $settingsSnapshot = $this->settingsSnapshot($context);

            $this->ensureNoDuplicateToday($guestBrowserHash, $context);

            $this->ensureBrowserLimit($guestBrowserHash, $context, $settingsSnapshot);
            $riskReasons = $this->fraudRisk->reasons(
                employeeId: (int) $context['employee']->id,
                rating: $rating,
                ipHash: $ipHash,
                submittedAt: $now,
                settingsSnapshot: $settingsSnapshot
            );
            $riskStatus = $riskReasons === []
                ? CustomerRating::RISK_CLEAR
                : CustomerRating::RISK_PENDING_REVIEW;

            try {
                $customerRating = CustomerRating::create([
                    'employee_id' => $context['employee']->id,
                    'branch_id' => $context['branch_id'],
                    'shift_id' => $context['shift_id'],
                    'shift_assignment_id' => $context['shift_assignment_id'],
                    'attendance_id' => $attendance?->id,
                    'attendance_segment_id' => $context['attendance_segment']?->id,
                    'rating_qr_token_id' => $token->id,
                    'work_date' => $context['work_date'],
                    'business_date' => $context['business_date'],
                    'rating' => $rating,
                    'comment' => $comment,
                    'guest_browser_hash' => $guestBrowserHash,
                    'ip_hash' => $ipHash,
                    'user_agent_hash' => $userAgentHash,
                    'settings_snapshot' => $settingsSnapshot,
                    'risk_status' => $riskStatus,
                    'risk_reasons' => $riskReasons === [] ? null : $riskReasons,
                    'risk_checked_at' => $riskReasons === [] ? null : $now,
                    'submitted_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new RatingSubmissionException('duplicate_rating_today', 'Thiet bi nay da danh gia nhan vien nay hom nay.');
            }

            if ($riskStatus === CustomerRating::RISK_PENDING_REVIEW) {
                $this->rewards->createRiskReviewForRating($customerRating, $settingsSnapshot);
            } else {
                $this->rewards->createForRating($customerRating, $settingsSnapshot);
            }

            $this->logRiskDecision($customerRating, $riskReasons, $guestBrowserHash, $ipHash, $routeType);

            return $customerRating->load('reward');
        });

        $this->notifications->notifyRatingCreated($customerRating);

        return $customerRating;
    }

    private function normalizeRating(string $rating): string
    {
        $rating = strtolower(trim($rating));

        if (!in_array($rating, [
            CustomerRating::RATING_BAD,
            CustomerRating::RATING_AVERAGE,
            CustomerRating::RATING_GOOD,
        ], true)) {
            throw new RatingSubmissionException('invalid_rating', 'Muc danh gia khong hop le.');
        }

        return $rating;
    }

    private function ensureBrowserLimit(string $guestBrowserHash, array $context, array $settingsSnapshot): void
    {
        $maxEmployees = (int) $settingsSnapshot['max_distinct_employees_per_guest_browser_per_branch_per_business_date'];
        $employeeId = (int) $context['employee']->id;

        $ratedEmployeeIds = CustomerRating::query()
            ->where('guest_browser_hash', $guestBrowserHash)
            ->where('branch_id', $context['branch_id'])
            ->whereDate('business_date', $context['business_date'])
            ->distinct()
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id);

        if (!$ratedEmployeeIds->contains($employeeId) && $ratedEmployeeIds->count() >= $maxEmployees) {
            throw new RatingSubmissionException('browser_employee_limit_reached', 'Trinh duyet nay da vuot gioi han so nhan su duoc danh gia trong ngay.');
        }
    }

    private function ensureNoDuplicateToday(string $guestBrowserHash, array $context): void
    {
        if (CustomerRating::query()
            ->where('employee_id', $context['employee']->id)
            ->whereDate('business_date', $context['business_date'])
            ->where('guest_browser_hash', $guestBrowserHash)
            ->exists()
        ) {
            throw new RatingSubmissionException('duplicate_rating_today', 'Thiet bi nay da danh gia nhan vien nay hom nay.');
        }
    }

    private function settingsSnapshot(array $context): array
    {
        return Setting::customerRatingSettingsSnapshot() + [
            'business_date' => $context['business_date'],
            'attendance_id' => $context['attendance']?->id,
            'branch_id' => $context['branch_id'],
            'reward_rejection_reason' => null,
        ];
    }

    private function logRiskDecision(CustomerRating $rating, array $riskReasons, string $guestBrowserHash, ?string $ipHash, string $routeType): void
    {
        Log::info('QR rating risk decision.', [
            'employee_id' => $rating->employee_id,
            'branch_id' => $rating->branch_id,
            'business_date' => $rating->business_date?->toDateString(),
            'rating' => $rating->rating,
            'risk_status' => $rating->risk_status,
            'risk_reason_codes' => $riskReasons,
            'ip_hash_prefix' => $ipHash ? substr($ipHash, 0, 10) : null,
            'guest_browser_hash_prefix' => substr($guestBrowserHash, 0, 10),
            'route_type' => $routeType,
        ]);
    }
}
