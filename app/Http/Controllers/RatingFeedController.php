<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomerRating;
use App\Notifications\CustomerRatingReceivedNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingFeedController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $ratings = $this->scopedRatings($request)
            ->with(['employee', 'reward'])
            ->latest('submitted_at')
            ->limit(50)
            ->get()
            ->unique('employee_id')
            ->take(3)
            ->map(fn (CustomerRating $rating) => $this->payload($rating))
            ->values();

        return response()->json(['ratings' => $ratings])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function index(Request $request)
    {
        $query = $this->scopedRatings($request)
            ->with(['employee', 'reward'])
            ->latest('submitted_at');

        if (in_array($request->query('rating'), [
            CustomerRating::RATING_BAD,
            CustomerRating::RATING_AVERAGE,
            CustomerRating::RATING_GOOD,
        ], true)) {
            $query->where('rating', $request->query('rating'));
        }

        $ratings = $query
            ->paginate(15)
            ->withQueryString();

        $weeklyLeaderboard = $this->weeklyLeaderboard($request);

        return response()
            ->view('ratings_feed.index', [
                'branchOptions' => $this->branchOptions($request),
                'ratings' => $ratings,
                'ratingFilter' => $request->query('rating'),
                'branchFilter' => $request->user()->role === 'admin' ? $request->query('branch_id') : null,
                'topEmployees' => $weeklyLeaderboard['items'],
                'topPeriodLabel' => $weeklyLeaderboard['label'],
                'topPeriodFrom' => $weeklyLeaderboard['from']->format('d/m/Y'),
                'topPeriodTo' => $weeklyLeaderboard['to']->format('d/m/Y'),
            ])
            ->withHeaders($this->noStoreHeaders());
    }

    public function top(Request $request)
    {
        $period = $this->resolveLeaderboardPeriod($request);
        $query = $this->leaderboardQuery($request);

        if ($period['from'] !== null) {
            $query->where('submitted_at', '>=', $period['from']);
        }

        if ($period['to'] !== null) {
            $query->where('submitted_at', '<=', $period['to']);
        }

        $leaders = $query
            ->paginate(10)
            ->withQueryString();

        return response()
            ->view('ratings_feed.top', [
                'branchOptions' => $this->branchOptions($request),
                'branchFilter' => $request->user()->role === 'admin' ? $request->query('branch_id') : null,
                'leaders' => $leaders,
                'periodFilter' => $period['period'],
                'periodLabel' => $period['label'],
                'periodFrom' => $period['from']?->format('Y-m-d'),
                'periodTo' => $period['to']?->format('Y-m-d'),
                'monthFilter' => $request->query('month'),
                'yearFilter' => $request->query('year'),
                'fromFilter' => $request->query('from'),
                'toFilter' => $request->query('to'),
            ])
            ->withHeaders($this->noStoreHeaders());
    }

    private function weeklyLeaderboard(Request $request): array
    {
        $now = CarbonImmutable::now();
        $from = $now->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $to = $now->endOfWeek(CarbonInterface::SUNDAY)->endOfDay();

        $items = $this->leaderboardQuery($request)
            ->whereBetween('submitted_at', [$from, $to])
            ->limit(3)
            ->get();

        return [
            'items' => $items,
            'from' => $from,
            'to' => $to,
            'label' => sprintf(
                'Tuần %s - %s',
                $from->format('d/m/Y'),
                $to->format('d/m/Y')
            ),
        ];
    }

    private function leaderboardQuery(Request $request): Builder
    {
        return $this->scopedRatings($request)
            ->whereNotNull('employee_id')
            ->select('employee_id')
            ->selectRaw(
                'SUM(CASE WHEN rating = ? THEN 1 ELSE 0 END) as good_count',
                [CustomerRating::RATING_GOOD]
            )
            ->selectRaw(
                'SUM(CASE WHEN rating = ? THEN 1 ELSE 0 END) as average_count',
                [CustomerRating::RATING_AVERAGE]
            )
            ->selectRaw(
                'SUM(CASE WHEN rating = ? THEN 1 ELSE 0 END) as bad_count',
                [CustomerRating::RATING_BAD]
            )
            ->selectRaw(
                'MAX(CASE WHEN rating = ? THEN submitted_at END) as latest_good_at',
                [CustomerRating::RATING_GOOD]
            )
            ->groupBy('employee_id')
            ->havingRaw(
                'SUM(CASE WHEN rating = ? THEN 1 ELSE 0 END) > 0',
                [CustomerRating::RATING_GOOD]
            )
            ->orderByDesc('good_count')
            ->orderByDesc('latest_good_at')
            ->orderBy('employee_id')
            ->with('employee');
    }

    private function resolveLeaderboardPeriod(Request $request): array
    {
        $period = strtolower((string) $request->query('period', 'week'));

        if (! in_array($period, ['week', 'month', 'year', 'all', 'custom'], true)) {
            $period = 'week';
        }

        $now = CarbonImmutable::now();

        if ($period === 'all') {
            return [
                'period' => 'all',
                'from' => null,
                'to' => null,
                'label' => 'Tất cả thời gian',
            ];
        }

        if ($period === 'month') {
            $month = $this->parseMonth((string) $request->query('month')) ?? $now->startOfMonth();
            $from = $month->startOfMonth()->startOfDay();
            $to = $month->endOfMonth()->endOfDay();

            return [
                'period' => 'month',
                'from' => $from,
                'to' => $to,
                'label' => 'Tháng '.$from->format('m/Y'),
            ];
        }

        if ($period === 'year') {
            $year = (int) $request->query('year');

            if ($year < 2000 || $year > 2100) {
                $year = (int) $now->format('Y');
            }

            $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $to = CarbonImmutable::create($year, 12, 31)->endOfDay();

            return [
                'period' => 'year',
                'from' => $from,
                'to' => $to,
                'label' => 'Năm '.$year,
            ];
        }

        if ($period === 'custom') {
            $from = $this->parseDate((string) $request->query('from'))?->startOfDay();
            $to = $this->parseDate((string) $request->query('to'))?->endOfDay();

            if ($from !== null && $to !== null && $from->greaterThan($to)) {
                $oldFrom = $from;
                $from = $to->startOfDay();
                $to = $oldFrom->endOfDay();
            }

            if ($from !== null || $to !== null) {
                if ($from !== null && $to !== null) {
                    $label = sprintf('Từ %s đến %s', $from->format('d/m/Y'), $to->format('d/m/Y'));
                } elseif ($from !== null) {
                    $label = 'Từ '.$from->format('d/m/Y');
                } else {
                    $label = 'Đến '.$to->format('d/m/Y');
                }

                return [
                    'period' => 'custom',
                    'from' => $from,
                    'to' => $to,
                    'label' => $label,
                ];
            }
        }

        $from = $now->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $to = $now->endOfWeek(CarbonInterface::SUNDAY)->endOfDay();

        return [
            'period' => 'week',
            'from' => $from,
            'to' => $to,
            'label' => sprintf(
                'Tuần %s - %s',
                $from->format('d/m/Y'),
                $to->format('d/m/Y')
            ),
        ];
    }

    private function parseMonth(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function branchOptions(Request $request)
    {
        return $request->user()->role === 'admin'
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    private function scopedRatings(Request $request): Builder
    {
        $user = $request->user();
        $query = CustomerRating::query();

        if ($user->role === 'admin') {
            if ($request->filled('branch_id')) {
                $query->where('branch_id', (int) $request->query('branch_id'));
            }

            return $query;
        }

        return $query->where('branch_id', $user->branch_id);
    }

    private function payload(CustomerRating $rating): array
    {
        return [
            'id' => $rating->id,
            'employee_name' => $rating->employee?->name ?? 'Nhân sự',
            'employee_initial' => mb_strtoupper(mb_substr(trim($rating->employee?->name ?? 'N'), 0, 1)),
            'rating' => $rating->rating,
            'rating_label' => $this->ratingLabel($rating->rating),
            'comment' => $rating->comment,
            'submitted_at_label' => $rating->submitted_at?->format('H:i d/m/Y'),
            'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                $rating->reward?->status,
                $rating->reward?->reason_code
            ),
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

    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}