<?php

namespace App\Http\Controllers;

use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CustomerRatingReceivedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MyRatingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return response()
            ->view('my_ratings.index', [
                'month' => $month,
                'ratingDashboard' => $this->ratingDashboardData($user, $start, $end),
                'user' => $user,
            ])
            ->withHeaders($this->noStoreHeaders());
    }

    private function ratingDashboardData(User $user, Carbon $start, Carbon $end): array
    {
        $ratingQuery = CustomerRating::query()
            ->where('employee_id', $user->id)
            ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        $ratingCounts = (clone $ratingQuery)
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $latestRatings = (clone $ratingQuery)
            ->with('reward')
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRating $rating) => [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'rating_label' => $this->ratingLabel($rating->rating),
                'rating_tone' => $this->ratingTone($rating->rating),
                'comment' => $rating->comment,
                'submitted_at' => $rating->submitted_at?->format('H:i d/m/Y'),
                'reward_status' => $rating->reward?->status,
                'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                    $rating->reward?->status,
                    $rating->reward?->reason_code
                ),
            ]);

        $ratingHistory = CustomerRating::query()
            ->where('employee_id', $user->id)
            ->with('reward')
            ->latest('submitted_at')
            ->paginate(12, ['*'], 'ratings_page')
            ->withQueryString();

        $today = today();
        $maxRewardedGood = Setting::getCustomerRatingInt('max_rewarded_good_per_employee_per_business_date');
        $todayRewards = CustomerRatingReward::query()
            ->where('employee_id', $user->id)
            ->whereDate('business_date', $today)
            ->get();
        $rewardedToday = $todayRewards
            ->whereIn('status', [CustomerRatingReward::STATUS_ELIGIBLE, CustomerRatingReward::STATUS_PAID])
            ->count();
        $rewardProgressPercent = $maxRewardedGood > 0
            ? min(100, round($rewardedToday / $maxRewardedGood * 100))
            : 0;

        $rewardHistory = CustomerRatingReward::with('customerRating')
            ->where('employee_id', $user->id)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->latest('business_date')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (CustomerRatingReward $reward) => [
                'id' => $reward->id,
                'business_date' => $reward->business_date?->format('d/m/Y'),
                'rating_label' => $this->ratingLabel($reward->customerRating?->rating),
                'amount' => (int) $reward->amount,
                'amount_label' => number_format((int) $reward->amount, 0, ',', '.') . 'đ',
                'status' => $reward->status,
                'status_label' => CustomerRatingReceivedNotification::rewardStatusLabel($reward->status, $reward->reason_code),
                'reason_code' => $reward->reason_code,
            ]);

        return [
            'counts' => [
                CustomerRating::RATING_GOOD => (int) ($ratingCounts[CustomerRating::RATING_GOOD] ?? 0),
                CustomerRating::RATING_AVERAGE => (int) ($ratingCounts[CustomerRating::RATING_AVERAGE] ?? 0),
                CustomerRating::RATING_BAD => (int) ($ratingCounts[CustomerRating::RATING_BAD] ?? 0),
            ],
            'latest' => $latestRatings,
            'rating_history' => $ratingHistory,
            'month_label' => $start->format('m/Y'),
            'reward_progress' => [
                'eligible_count' => $rewardedToday,
                'max_count' => $maxRewardedGood,
                'percent' => $rewardProgressPercent,
                'amount' => (int) $todayRewards->sum('amount'),
                'amount_label' => number_format((int) $todayRewards->sum('amount'), 0, ',', '.') . 'đ',
            ],
            'reward_history' => $rewardHistory,
        ];
    }

    private function ratingLabel(?string $rating): string
    {
        return match ($rating) {
            CustomerRating::RATING_BAD => 'Tệ',
            CustomerRating::RATING_AVERAGE => 'Trung bình',
            CustomerRating::RATING_GOOD => 'Tốt',
            default => 'Đánh giá',
        };
    }

    private function ratingTone(?string $rating): string
    {
        return match ($rating) {
            CustomerRating::RATING_BAD => 'bad',
            CustomerRating::RATING_AVERAGE => 'average',
            CustomerRating::RATING_GOOD => 'good',
            default => 'neutral',
        };
    }

    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
