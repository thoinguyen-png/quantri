<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\AttendanceSupplementRequest;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\LeaveRequest;
use App\Models\ShiftAssignment;
use App\Notifications\CustomerRatingReceivedNotification;
use App\Services\AttendanceStatusService;
use App\Services\AttendanceDailyMetricsService;
use App\Services\AttendanceLateCalculator;
use App\Services\AttendanceMonthlyOvertimeService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return response()
            ->view('dashboard.home', [
            'summaryEndpoint' => route('dashboard.rating-summary', absolute: false),
            'user' => $request->user(),
            ])
            ->withHeaders($this->noStoreHeaders());
    }

    public function attendance(Request $request)
    {
        $user = auth()->user();

        if (in_array($user->role, ['staff', 'manager', 'cashier'], true)) {
            return response()
                ->view('dashboard.staff', $this->employeeDashboardData($user, $request))
                ->withHeaders($this->noStoreHeaders());
        }

        return response()
            ->view('dashboard.admin', $this->adminDashboardData())
            ->withHeaders($this->noStoreHeaders());
    }

    public function ratingSummary(Request $request): JsonResponse
    {
        return response()
            ->json($this->homeRatingData($request->user()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function homeRatingData(User $user): array
    {
        return [
            'personal' => $this->homePersonalRatingData($user),
            'branch' => $this->homeBranchRatingData($user),
        ];
    }

    private function homePersonalRatingData(User $user): array
    {
        $canReceiveRatings = in_array($user->role, ['staff', 'cashier', 'manager'], true)
            && (bool) $user->rating_qr_enabled;

        if (!$canReceiveRatings) {
            return [
                'can_receive_ratings' => false,
                'counts' => $this->emptyRatingCounts(),
                'items' => [],
            ];
        }

        $query = CustomerRating::with(['employee', 'reward'])
            ->where('employee_id', $user->id);

        $ratingCounts = (clone $query)
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $items = (clone $query)
            ->latest('submitted_at')
            ->limit(2)
            ->get()
            ->map(fn (CustomerRating $rating) => $this->homeRatingPayload($rating))
            ->values();

        return [
            'can_receive_ratings' => true,
            'counts' => [
                CustomerRating::RATING_GOOD => (int) ($ratingCounts[CustomerRating::RATING_GOOD] ?? 0),
                CustomerRating::RATING_AVERAGE => (int) ($ratingCounts[CustomerRating::RATING_AVERAGE] ?? 0),
                CustomerRating::RATING_BAD => (int) ($ratingCounts[CustomerRating::RATING_BAD] ?? 0),
            ],
            'items' => $items,
        ];
    }

    private function homeBranchRatingData(User $user): array
    {
        $query = CustomerRating::with(['employee', 'reward'])
            ->latest('submitted_at');

        if ($user->role !== 'admin') {
            if (!$user->branch_id) {
                return ['items' => []];
            }

            $query->where('branch_id', $user->branch_id);
        }

        $items = $query
            ->limit(50)
            ->get()
            ->unique('employee_id')
            ->take(3)
            ->map(fn (CustomerRating $rating) => $this->homeRatingPayload($rating))
            ->values();

        return ['items' => $items];
    }

    private function emptyRatingCounts(): array
    {
        return [
            CustomerRating::RATING_GOOD => 0,
            CustomerRating::RATING_AVERAGE => 0,
            CustomerRating::RATING_BAD => 0,
        ];
    }

    private function homeRatingPayload(CustomerRating $rating): array
    {
        $employeeName = $rating->employee?->name ?? 'Nhan su';

        return [
            'id' => $rating->id,
            'employee_name' => $employeeName,
            'employee_initial' => mb_strtoupper(mb_substr(trim($employeeName), 0, 1)),
            'rating' => $rating->rating,
            'rating_label' => $this->ratingLabel($rating->rating),
            'rating_tone' => $this->ratingTone($rating->rating),
            'comment' => $rating->comment,
            'submitted_at_label' => $rating->submitted_at?->format('H:i d/m/Y'),
            'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                $rating->reward?->status,
                $rating->reward?->reason_code
            ),
        ];
    }

    private function employeeDashboardData(User $user, Request $request): array
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = collect(CarbonPeriod::create($start, $end))->values();

        $attendanceRecords = Attendance::with('shift')
            ->where('user_id', $user->id)
            ->forWorkDateBetween($start, $end)
            ->get();

        $monthlyOvertimeService = app(
            AttendanceMonthlyOvertimeService::class
        );

        $attendances = $monthlyOvertimeService
            ->latestByUserAndWorkDate($attendanceRecords);

        $monthlyOvertime = $monthlyOvertimeService
            ->totalsByUser($attendances)
            ->get($user->id, [
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
            ]);

        $assignments = ShiftAssignment::with('shift')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($start, $end) {
                $query->whereNull('work_date')
                    ->orWhereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        $leaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $supplements = AttendanceSupplementRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceSupplementRequest $request) => $request->work_date?->toDateString());

        $cells = $days->mapWithKeys(function (Carbon $day) use ($user, $attendances, $assignments, $leaves, $supplements) {
            $attendance = $attendances->get(
                $user->id . '|' . $day->toDateString()
            );
            $assignment = $this->effectiveAssignmentForDay($assignments, $day);
            $leave = $leaves->first(fn (LeaveRequest $leave) => $day->betweenIncluded($leave->start_date, $leave->end_date));
            $supplement = $supplements->get($day->toDateString());

            return [$day->toDateString() => $this->employeeDayCell($day, $attendance, $assignment, $leave, $supplement)];
        });

        $summary = [
            'complete' => $cells->where('work_status', 'complete')->count(),
            'missing' => $cells->where('work_status', 'missing')->count(),
            'off' => $cells->where('status', 'leave_approved')->count(),
            'absent' => $cells->where('status', 'absent_without_leave')->count(),
            'overtime' => $monthlyOvertime['overtime_hours'],
        ];

        $firstWeekday = (int) $start->dayOfWeekIso;
        $calendarLeadingDays = $firstWeekday - 1;

        $managerRatingDashboard = $user->role === 'manager'
            ? $this->managerRatingDashboardData($user, $start, $end)
            : null;

        return compact('calendarLeadingDays', 'cells', 'days', 'managerRatingDashboard', 'month', 'summary', 'user');
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

    private function managerRatingDashboardData(User $manager, Carbon $start, Carbon $end): array
    {
        $branchId = (int) $manager->branch_id;

        if ($branchId <= 0) {
            return [
                'branch_id' => null,
                'counts' => [
                    CustomerRating::RATING_GOOD => 0,
                    CustomerRating::RATING_AVERAGE => 0,
                    CustomerRating::RATING_BAD => 0,
                ],
                'latest' => collect(),
                'bad_feedback' => collect(),
                'performance' => collect(),
                'reward_history' => collect(),
                'qr_users' => collect(),
                'month_label' => $start->format('m/Y'),
            ];
        }

        $ratingQuery = CustomerRating::with(['employee', 'reward'])
            ->where('branch_id', $branchId)
            ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        $ratingCounts = (clone $ratingQuery)
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $latestRatings = (clone $ratingQuery)
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRating $rating) => $this->managerRatingPayload($rating));

        $badFeedback = (clone $ratingQuery)
            ->where('rating', CustomerRating::RATING_BAD)
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRating $rating) => $this->managerRatingPayload($rating));

        $employees = User::query()
            ->where('branch_id', $branchId)
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->withCount([
                'customerRatings as good_ratings_count' => function ($query) use ($start, $end, $branchId) {
                    $query->where('branch_id', $branchId)
                        ->where('rating', CustomerRating::RATING_GOOD)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
                'customerRatings as average_ratings_count' => function ($query) use ($start, $end, $branchId) {
                    $query->where('branch_id', $branchId)
                        ->where('rating', CustomerRating::RATING_AVERAGE)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
                'customerRatings as bad_ratings_count' => function ($query) use ($start, $end, $branchId) {
                    $query->where('branch_id', $branchId)
                        ->where('rating', CustomerRating::RATING_BAD)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
            ])
            ->orderByDesc('good_ratings_count')
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role,
                'good' => (int) $employee->good_ratings_count,
                'average' => (int) $employee->average_ratings_count,
                'bad' => (int) $employee->bad_ratings_count,
                'total' => (int) $employee->good_ratings_count + (int) $employee->average_ratings_count + (int) $employee->bad_ratings_count,
            ]);

        $rewardHistory = CustomerRatingReward::with(['employee', 'customerRating'])
            ->where('branch_id', $branchId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->latest('business_date')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRatingReward $reward) => [
                'id' => $reward->id,
                'employee_name' => $reward->employee?->name ?? 'Nhan su',
                'business_date' => $reward->business_date?->format('d/m/Y'),
                'amount_label' => number_format((int) $reward->amount, 0, ',', '.') . 'đ',
                'status' => $reward->status,
                'status_label' => CustomerRatingReceivedNotification::rewardStatusLabel($reward->status, $reward->reason_code),
                'rating_label' => $this->ratingLabel($reward->customerRating?->rating),
            ]);

        $qrUsers = User::query()
            ->where('branch_id', $branchId)
            ->whereIn('role', ['staff', 'cashier'])
            ->where('rating_qr_enabled', true)
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role,
                'show_url' => route('rating-qrs.svg', $employee, absolute: false),
                'download_url' => route('rating-qrs.download', $employee, absolute: false),
            ]);

        return [
            'branch_id' => $branchId,
            'counts' => [
                CustomerRating::RATING_GOOD => (int) ($ratingCounts[CustomerRating::RATING_GOOD] ?? 0),
                CustomerRating::RATING_AVERAGE => (int) ($ratingCounts[CustomerRating::RATING_AVERAGE] ?? 0),
                CustomerRating::RATING_BAD => (int) ($ratingCounts[CustomerRating::RATING_BAD] ?? 0),
            ],
            'latest' => $latestRatings,
            'bad_feedback' => $badFeedback,
            'performance' => $employees,
            'reward_history' => $rewardHistory,
            'qr_users' => $qrUsers,
            'month_label' => $start->format('m/Y'),
        ];
    }

    private function managerRatingPayload(CustomerRating $rating): array
    {
        return [
            'id' => $rating->id,
            'employee_name' => $rating->employee?->name ?? 'Nhan su',
            'rating' => $rating->rating,
            'rating_label' => $this->ratingLabel($rating->rating),
            'rating_tone' => $this->ratingTone($rating->rating),
            'comment' => $rating->comment,
            'submitted_at' => $rating->submitted_at?->format('H:i d/m/Y'),
            'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                $rating->reward?->status,
                $rating->reward?->reason_code
            ),
        ];
    }

    private function adminDashboardData(): array
    {
        $today = today();
        $usersQuery = User::whereIn('role', ['staff', 'manager', 'cashier']);
        $totalUsers = (clone $usersQuery)->count();
        $todayAttendances = Attendance::with(['user', 'shift'])
            ->forWorkDate($today)
            ->get();

        $todayAssignments = ShiftAssignment::with('shift')
            ->whereIn(
                'user_id',
                $todayAttendances
                    ->pluck('user_id')
                    ->unique()
                    ->values()
            )
            ->where(function ($query) use ($today) {
                $query->whereNull('work_date')
                    ->orWhereDate(
                        'work_date',
                        $today->toDateString()
                    );
            })
            ->get()
            ->groupBy('user_id');

        $checkedIn = $todayAttendances->pluck('user_id')->unique()->count();
        $checkedPercent = $totalUsers > 0 ? round($checkedIn / $totalUsers * 100) : 0;
        $notChecked = max($totalUsers - $checkedIn, 0);
        $notCheckedPercent = $totalUsers > 0 ? round($notChecked / $totalUsers * 100) : 0;
        $lateCount = $todayAttendances
            ->filter(function (Attendance $attendance) use (
                $today,
                $todayAssignments
            ): bool {
                $assignment =
                    $this->effectiveAssignmentForDay(
                        $todayAssignments->get(
                            $attendance->user_id,
                            collect()
                        ),
                        $today
                    );

                $shift = $assignment?->shift
                    ?? $attendance->shift;

                if (
                    !$shift
                    || !$attendance->checkin_at
                ) {
                    return false;
                }

                $workDate = $attendance->work_date
                    ? Carbon::parse(
                        $attendance->work_date
                    )
                    : $today;

                return AttendanceLateCalculator::minutes(
                    $shift,
                    $workDate,
                    $attendance->checkin_at
                ) > 0;
            })
            ->pluck('user_id')
            ->unique()
            ->count();
        $leaveCount = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();
        $missingCheckoutCount = $todayAttendances->filter(fn (Attendance $attendance) => !$attendance->checkout_at)->count();

        $pendingLeaves = LeaveRequest::where('status', 'pending')->count();
        $pendingSupplements = AttendanceSupplementRequest::where('status', 'pending')->count();
        $noFaceCount = (clone $usersQuery)
            ->where(function ($query) {
                $query->whereNull('face_descriptor')
                    ->orWhere('face_descriptor', '');
            })
            ->count();
        $ratingDashboard = $this->adminRatingDashboardData($today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        return [
            'ratingDashboard' => $ratingDashboard,
            'alerts' => [
                ['label' => 'Thiếu checkout hôm nay', 'value' => $missingCheckoutCount . ' nhân viên', 'route' => 'attendance-reports.index'],
                ['label' => 'Chưa đăng ký gương mặt', 'value' => $noFaceCount . ' nhân viên', 'route' => 'users.index'],
            ],
            'pending' => [
                ['label' => 'Đơn xin OFF', 'value' => $pendingLeaves . ' đơn', 'route' => 'leave-requests.index'],
                ['label' => 'Bổ sung công', 'value' => $pendingSupplements . ' đơn', 'route' => 'attendance-supplements.index'],
            ],
            'quickLinks' => [
                ['label' => 'Nhân sự', 'route' => 'users.index', 'icon' => 'users'],
                ['label' => 'QR chấm công', 'route' => 'qr.show', 'icon' => 'qr'],
                ['label' => 'Báo cáo công', 'route' => 'attendance-reports.index', 'icon' => 'report'],
                ['label' => 'Thống kê công', 'route' => 'attendance-statistics.index', 'icon' => 'stats'],
            ],
            'summary' => [
                ['label' => 'Tổng nhân sự', 'value' => $totalUsers, 'meta' => '', 'tone' => 'purple'],
                ['label' => 'Đã check-in', 'value' => $checkedIn, 'meta' => $checkedPercent . '%', 'tone' => 'green'],
                ['label' => 'Chưa check-in', 'value' => $notChecked, 'meta' => $notCheckedPercent . '%', 'tone' => 'red'],
                ['label' => 'Đi trễ', 'value' => $lateCount, 'meta' => '', 'tone' => 'yellow'],
                ['label' => 'Nghỉ phép', 'value' => $leaveCount, 'meta' => '', 'tone' => 'indigo'],
                ['label' => 'Thiếu check-out', 'value' => $missingCheckoutCount, 'meta' => '', 'tone' => 'orange'],
            ],
        ];
    }

    private function adminRatingDashboardData(Carbon $start, Carbon $end): array
    {
        $ratingQuery = CustomerRating::with(['employee.branch', 'branch', 'reward'])
            ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        $latestRatings = (clone $ratingQuery)
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRating $rating) => $this->adminRatingPayload($rating));

        $badFeedback = (clone $ratingQuery)
            ->where('rating', CustomerRating::RATING_BAD)
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerRating $rating) => $this->adminRatingPayload($rating));

        $branchCounts = (clone $ratingQuery)
            ->selectRaw('branch_id, rating, count(*) as aggregate')
            ->groupBy('branch_id', 'rating')
            ->get()
            ->groupBy('branch_id');

        $branchPerformance = Branch::active()
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($branchCounts) {
                $counts = $branchCounts->get($branch->id, collect())->pluck('aggregate', 'rating');

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'good' => (int) ($counts[CustomerRating::RATING_GOOD] ?? 0),
                    'average' => (int) ($counts[CustomerRating::RATING_AVERAGE] ?? 0),
                    'bad' => (int) ($counts[CustomerRating::RATING_BAD] ?? 0),
                ];
            })
            ->filter(fn (array $branch) => $branch['good'] + $branch['average'] + $branch['bad'] > 0)
            ->values()
            ->take(12);

        $employeePerformance = User::query()
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->with('branch')
            ->withCount([
                'customerRatings as good_ratings_count' => function ($query) use ($start, $end) {
                    $query->where('rating', CustomerRating::RATING_GOOD)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
                'customerRatings as average_ratings_count' => function ($query) use ($start, $end) {
                    $query->where('rating', CustomerRating::RATING_AVERAGE)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
                'customerRatings as bad_ratings_count' => function ($query) use ($start, $end) {
                    $query->where('rating', CustomerRating::RATING_BAD)
                        ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                },
            ])
            ->orderByDesc('good_ratings_count')
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'branch_name' => $employee->branch?->name ?? 'Chua co chi nhanh',
                'role' => $employee->role,
                'good' => (int) $employee->good_ratings_count,
                'average' => (int) $employee->average_ratings_count,
                'bad' => (int) $employee->bad_ratings_count,
                'total' => (int) $employee->good_ratings_count + (int) $employee->average_ratings_count + (int) $employee->bad_ratings_count,
            ])
            ->filter(fn (array $employee) => $employee['total'] > 0)
            ->values();

        $rewardStatusCounts = CustomerRatingReward::query()
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('status, count(*) as aggregate, coalesce(sum(amount), 0) as total_amount')
            ->groupBy('status')
            ->get();

        $rewardTotalAmount = (int) $rewardStatusCounts->sum('total_amount');
        $rewardStatuses = $rewardStatusCounts
            ->map(fn ($reward) => [
                'status' => $reward->status,
                'status_label' => CustomerRatingReceivedNotification::rewardStatusLabel($reward->status, null),
                'count' => (int) $reward->aggregate,
                'amount_label' => number_format((int) $reward->total_amount, 0, ',', '.') . 'đ',
            ])
            ->values();

        $qrUsers = User::query()
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->with('branch')
            ->orderBy('name')
            ->limit(15)
            ->get();

        return [
            'latest' => $latestRatings,
            'bad_feedback' => $badFeedback,
            'branch_performance' => $branchPerformance,
            'employee_performance' => $employeePerformance,
            'reward_total_amount_label' => number_format($rewardTotalAmount, 0, ',', '.') . 'đ',
            'reward_statuses' => $rewardStatuses,
            'qr_users' => $qrUsers,
            'month_label' => $start->format('m/Y'),
        ];
    }

    private function adminRatingPayload(CustomerRating $rating): array
    {
        return [
            'id' => $rating->id,
            'employee_name' => $rating->employee?->name ?? 'Nhan su',
            'branch_name' => $rating->branch?->name ?? $rating->employee?->branch?->name ?? 'Chua co chi nhanh',
            'rating' => $rating->rating,
            'rating_label' => $this->ratingLabel($rating->rating),
            'rating_tone' => $this->ratingTone($rating->rating),
            'comment' => $rating->comment,
            'submitted_at' => $rating->submitted_at?->format('H:i d/m/Y'),
            'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                $rating->reward?->status,
                $rating->reward?->reason_code
            ),
        ];
    }

    private function employeeDayCell(
        Carbon $day,
        ?Attendance $attendance,
        ?ShiftAssignment $assignment,
        ?LeaveRequest $leave,
        ?AttendanceSupplementRequest $supplement = null
    ): array {
        /*
         * Phân ca hiện tại của đúng ngày là nguồn ca ưu tiên.
         * attendance.shift chỉ là snapshot fallback.
         */
        $shift = $assignment?->shift
            ?? $attendance?->shift;

        $display = app(
            AttendanceStatusService::class
        )->resolveDailyStatus(
            user: auth()->user(),
            workDate: $day,
            attendance: $attendance,
            assignment: $assignment,
            leave: $leave,
            supplement: $supplement
        );

        /*
         * Dashboard dùng cùng AttendanceDailyMetricsService
         * với thống kê/lương để không tự tính công, trễ hoặc OT
         * theo một rule riêng.
         */
        $metrics = app(
            AttendanceDailyMetricsService::class
        )->calculate(
            $attendance,
            $assignment,
            $day,
            $display['key']
        );

        $overtimeMinutes = (int) (
            $metrics['overtime_minutes']
            ?? 0
        );

        return [
            'status' => $display['key'],
            'status_key' => $display['key'],
            'label' => $display['label'],
            'subtitle' => $display['subtitle'],
            'classes' => $display['classes'],
            'color' => $display['color'],
            'icon' => $display['icon'],
            'badges' => $display['badges'],
            'shift' => $shift?->name,
            'time' => $shift
                ? $this->shiftTimeText($shift, $day)
                : '--',

            /*
             * Giữ key này để Blade/JS cũ không lỗi,
             * nhưng luồng ca gãy đã bị loại bỏ.
             */
            'segments' => [],

            'checkin' => $attendance?->checkin_at?->format('H:i'),
            'checkout' => $attendance?->checkout_at?->format('H:i'),
            'late_minutes' => (int) (
                $metrics['late_minutes']
                ?? 0
            ),
            'work_status' => $display['work_status'],
            'work_label' => $display['work_label'],
            'overtime_minutes' => $overtimeMinutes,
            'overtime_hours' => round(
                $overtimeMinutes / 60,
                1
            ),
            'note' => $display['subtitle'],
        ];
    }

    private function effectiveAssignmentForDay($assignments, Carbon $day): ?ShiftAssignment
    {
        $dateAssignment = $assignments
            ->filter(fn (ShiftAssignment $assignment) => $assignment->work_date?->toDateString() === $day->toDateString())
            ->sortByDesc('updated_at')
            ->first();

        if ($dateAssignment) {
            return $dateAssignment;
        }

        return $assignments
            ->filter(fn (ShiftAssignment $assignment) => !$assignment->work_date)
            ->filter(fn (ShiftAssignment $assignment) => $assignment->created_at->lessThanOrEqualTo($day->copy()->endOfDay()))
            ->sortByDesc('created_at')
            ->first();
    }

    private function shiftTimeText(
        Shift $shift,
        Carbon $day
    ): string {
        return sprintf(
            '%s - %s',
            $this->shiftDateTimeFor(
                $shift,
                $day,
                'start_at'
            )->format('H:i'),
            $this->shiftDateTimeFor(
                $shift,
                $day,
                'end_at'
            )->format('H:i')
        );
    }

    private function shiftDateTimeFor(Shift $shift, Carbon $base, string $field): Carbon
    {
        $time = Carbon::parse($shift->{$field});

        $dateTime = $base->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at') {
            $start = Carbon::parse($shift->start_at);
            $end = Carbon::parse($shift->end_at);

            if ($end->format('H:i:s') <= $start->format('H:i:s')) {
                $dateTime->addDay();
            }
        }

        return $dateTime;
    }


}