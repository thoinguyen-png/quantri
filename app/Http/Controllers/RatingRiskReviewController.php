<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\User;
use App\Services\RatingRiskReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingRiskReviewController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->scopedPendingRatings($request)
            ->with(['employee.branch', 'branch', 'reward', 'reviewer'])
            ->latest('submitted_at');

        if ($request->filled('business_date')) {
            $query->whereDate('business_date', $request->query('business_date'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }

        if (in_array($request->query('rating'), [
            CustomerRating::RATING_BAD,
            CustomerRating::RATING_AVERAGE,
            CustomerRating::RATING_GOOD,
        ], true)) {
            $query->where('rating', $request->query('rating'));
        }

        if ($request->user()->role === 'admin' && $request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if ($request->filled('risk_reason')) {
            $query->whereJsonContains('risk_reasons', $request->query('risk_reason'));
        }

        if ($request->filled('reward_status')) {
            $query->whereHas('reward', fn (Builder $reward) => $reward->where('status', $request->query('reward_status')));
        }

        return view('ratings_risk_review.index', [
            'ratings' => $query->paginate(15)->withQueryString(),
            'branchOptions' => $request->user()->role === 'admin' ? Branch::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'employeeOptions' => $this->employeeOptions($request),
            'filters' => $request->only([
                'business_date',
                'employee_id',
                'rating',
                'branch_id',
                'risk_reason',
                'reward_status',
            ]),
            'reasonLabels' => $this->reasonLabels(),
            'rewardStatusOptions' => ['risk_review', 'eligible', 'rejected', 'paid', 'pending'],
        ]);
    }

    public function approve(CustomerRating $customerRating, Request $request, RatingRiskReviewService $review): RedirectResponse
    {
        $this->authorizeRatingScope($customerRating, $request);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $review->approve($customerRating, $request->user(), $data['review_note'] ?? null);

        return redirect()
            ->to($this->backUrl($request))
            ->with('success', 'Đã duyệt đánh giá nghi ngờ.');
    }

    public function reject(CustomerRating $customerRating, Request $request, RatingRiskReviewService $review): RedirectResponse
    {
        $this->authorizeRatingScope($customerRating, $request);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $review->reject($customerRating, $request->user(), $data['review_note'] ?? null);

        return redirect()
            ->to($this->backUrl($request))
            ->with('success', 'Đã từ chối thưởng cho đánh giá nghi ngờ.');
    }

    private function scopedPendingRatings(Request $request): Builder
    {
        return $this->scopedRatings($request)
            ->where('risk_status', CustomerRating::RISK_PENDING_REVIEW);
    }

    private function scopedRatings(Request $request): Builder
    {
        $query = CustomerRating::query();

        if ($request->user()->role === 'admin') {
            return $query;
        }

        return $query->where('branch_id', $request->user()->branch_id);
    }

    private function employeeOptions(Request $request)
    {
        $query = User::query()
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->orderBy('name');

        if ($request->user()->role !== 'admin') {
            $query->where('branch_id', $request->user()->branch_id);
        }

        return $query->get(['id', 'name', 'branch_id']);
    }

    private function authorizeRatingScope(CustomerRating $rating, Request $request): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless((int) $rating->branch_id === (int) $request->user()->branch_id, 403);
    }

    private function backUrl(Request $request): string
    {
        $back = (string) $request->input('back', '');

        return str_starts_with($back, url('/')) || str_starts_with($back, '/')
            ? $back
            : route('ratings.risk-review.index');
    }

    private function reasonLabels(): array
    {
        return [
            'duplicate_ip_employee_15m' => 'Cùng mạng đánh giá cùng nhân sự liên tiếp trong 15 phút.',
            'repeated_ip_employee_60m' => 'Cùng mạng đánh giá nhân sự này quá nhiều lần trong 1 giờ.',
            'many_employees_same_ip_30m' => 'Cùng mạng đánh giá quá nhiều nhân sự trong 30 phút.',
            'burst_good_rating_15m' => 'Nhân sự nhận quá nhiều đánh giá tốt trong 15 phút.',
        ];
    }
}
