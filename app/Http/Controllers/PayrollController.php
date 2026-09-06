<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSupplementRequest;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceDailyMetricsService;
use App\Services\AttendanceMonthlyOvertimeService;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    private const MONEY_COLUMNS = [
        'actual_workday_salary' => 'Lương ngày công thực nhận',
        'holiday_salary' => 'Lương ngày lễ',
        'probation_holiday_salary' => 'Lương ngày lễ thử việc',
        'overtime_salary' => 'Lương tăng ca',
        'probation_salary' => 'Lương thử việc',
        'meal_allowance' => 'Phụ cấp tiền ăn',
        'service_charge' => 'Service charge',
        'responsibility_allowance' => 'Tiền trách nhiệm',
        'booking_bonus' => 'Thưởng booking',
        'booking_target' => 'Target booking',
        'recruitment_pr' => 'Tuyển dụng PR',
        'support_overtime' => 'Tăng ca hỗ trợ',
        'uniform_deposit_refund' => 'Hoàn cọc đồng phục',
        'holiday_bonus' => 'Thưởng ngày lễ',
        'salary_compensation' => 'Bù lương',
        'wine_discount' => 'Chiết khấu rượu',
        'income' => 'Thu nhập',
        'salary_advance' => 'Tạm ứng lương',
        'asset_depreciation' => 'Khấu hao tài sản',
        'late_early_penalty' => 'Phạt đi trễ - về sớm',
        'unauthorized_absence_penalty' => 'Phạt nghỉ không phép',
        'bill_debt' => 'Nợ đền bill',
        'uniform_deposit' => 'Cọc đồng phục',
        'record_penalty' => 'Phạt biên bản',
        'asset_compensation' => 'Đền tài sản',
        'deduction' => 'Khấu trừ',
        'unpaid_leave_recovery' => 'Thu hồi nghỉ không chế độ',
        'net_salary' => 'Thực lĩnh',
        'total_net_salary' => 'Tổng thực lãnh',
    ];

    public function index(Request $request)
    {
        $data = $this->buildPayrollData($request);

        return view('payrolls.index', $data);
    }

    public function export(Request $request): Response
    {
        $data = $this->buildPayrollData($request);
        $filename = 'bang-luong-' . $data['month'] . '.xls';

        return response()
            ->view('payrolls.export', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    private function buildPayrollData(Request $request): array
    {
        $viewer = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $branchId = $request->input('branch_id');
        $search = trim((string) $request->input('search', ''));
        $searchId = ctype_digit($search) ? (int) ltrim($search, '0') : null;

        if ($viewer->role === 'manager') {
            $branchId = $viewer->branch_id;
        }

        $branches = Branch::active()->orderBy('name')->get();

        $users = User::with(['branch', 'workHistories'])
            ->whereIn('role', ['staff', 'manager', 'cashier'])
            ->when($branchId, function ($query) use ($branchId, $start, $end) {
                $query->where(function ($q) use ($branchId, $start, $end) {
                    $q->where('branch_id', $branchId)
                        ->orWhereHas('attendances', function ($aq) use ($branchId, $start, $end) {
                            $aq->forBranch($branchId)->forWorkDateBetween($start, $end);
                        })
                        ->orWhereHas('workHistories', function ($wq) use ($branchId, $start, $end) {
                            $wq->where(function ($sq) use ($branchId) {
                                $sq->where('old_branch_id', $branchId)->orWhere('new_branch_id', $branchId);
                            })->whereBetween('effective_date', [$start->toDateString(), $end->toDateString()]);
                        });
                });
            })
            ->when($search !== '', function ($query) use ($search, $searchId) {
                $query->where(function ($q) use ($search, $searchId) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if ($searchId !== null) {
                        $q->orWhere('id', $searchId);
                    }
                });
            })
            ->orderBy('name')
            ->get();

        $userIds = $users->pluck('id');

        $attendanceRecords = Attendance::with('shift')
            ->whereIn('user_id', $userIds)
            ->when($branchId, fn ($q) => $q->forBranch($branchId))
            ->forWorkDateBetween($start, $end)
            ->get();

        $monthlyOvertimeService = app(
            AttendanceMonthlyOvertimeService::class
        );

        $attendances = $monthlyOvertimeService
            ->latestByUserAndWorkDate($attendanceRecords);

        $monthlyOvertimes = $monthlyOvertimeService
            ->totalsByUser($attendances);

        $assignments = ShiftAssignment::with('shift')
            ->whereIn('user_id', $userIds)
            ->where(function ($query) use ($start, $end) {
                $query->whereNull('work_date')
                    ->orWhereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get()
            ->groupBy('user_id');

        $leaves = LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate(
                'start_date',
                '<=',
                $end->toDateString()
            )
            ->whereDate(
                'end_date',
                '>=',
                $start->toDateString()
            )
            ->get()
            ->groupBy('user_id');

        $supplements = AttendanceSupplementRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween('work_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->get()
            ->keyBy(
                fn (AttendanceSupplementRequest $supplement) =>
                    $supplement->user_id
                    . '|'
                    . $supplement->work_date?->toDateString()
            );

        $days = collect(
            CarbonPeriod::create($start, $end)
        )->values();

        $statusService = app(
            AttendanceStatusService::class
        );

        $dailyMetricsService = app(
            AttendanceDailyMetricsService::class
        );

        $rows = $users->values()->map(function (
            User $user,
            int $index
        ) use (
            $days,
            $attendances,
            $assignments,
            $leaves,
            $supplements,
            $statusService,
            $dailyMetricsService,
            $monthlyOvertimes
        ) {
            $statusLabels = [
                'chinh_thuc' => 'Chính thức',
                'thu_viec' => 'Thử việc',
                'da_nghi' => 'Đã nghỉ',
            ];

            $metrics = [
                'work_units' => 0,
                'work_with_overtime' => 0,
                'early_leave_hours' => 0,
                'late_hours' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0,
                'unauthorized_absence_days' => 0,
                'late_days' => 0,
                'early_leave_days' => 0,
            ];

            foreach ($days as $day) {
                $attendanceKey = $user->id . '|' . $day->toDateString();
                $attendance = $attendances->get($attendanceKey);

                if (!empty($branchId)) {
                    $attendanceBranchId = $attendance ? ($attendance->branch_id ?? $attendance->user?->branch_id) : null;
                    if ($attendance && (int) $attendanceBranchId !== (int) $branchId) {
                        continue;
                    }

                    if (!$attendance) {
                        $userBranchOnDay = $this->userBranchForDate($user, $day);
                        if ($userBranchOnDay !== null && (int) $userBranchOnDay !== (int) $branchId) {
                            continue;
                        }
                    }
                }

                $assignment = $this->effectiveAssignmentForDay(
                    $assignments->get(
                        $user->id,
                        collect()
                    ),
                    $day
                );

                $leave = $this->approvedLeaveForDay(
                    $leaves->get(
                        $user->id,
                        collect()
                    ),
                    $day
                );

                $supplement = $supplements->get(
                    $attendanceKey
                );

                $display = $statusService
                    ->resolveDailyStatus(
                        $user,
                        $day,
                        $attendance,
                        $assignment,
                        $leave,
                        $supplement
                    );

                /*
                 * AttendanceDailyMetricsService dùng ShiftAssignment
                 * của đúng ngày làm nguồn ca hiện hành; nhờ vậy bảng
                 * lương và bảng thống kê không thể chọn hai ca khác nhau.
                 */
                $daily = $dailyMetricsService
                    ->calculate(
                        $attendance,
                        $assignment,
                        $day,
                        $display['key']
                    );

                foreach ($metrics as $key => $value) {
                    $metrics[$key] += $daily[$key];
                }
            }

            $metrics['work_units'] = round(
                $metrics['work_units'],
                2
            );

            $monthlyOvertime = $monthlyOvertimes->get(
                $user->id,
                [
                    'overtime_minutes' => 0,
                    'overtime_hours' => 0.0,
                ]
            );

            $metrics['overtime_minutes'] =
                $monthlyOvertime['overtime_minutes'];

            $metrics['overtime_hours'] =
                $monthlyOvertime['overtime_hours'];

            $metrics['work_with_overtime'] = round(
                $metrics['work_with_overtime'],
                2
            );
            $metrics['early_leave_hours'] = round($metrics['early_leave_hours'], 2);
            $metrics['late_hours'] = round($metrics['late_hours'], 2);

            $money = array_fill_keys(array_keys(self::MONEY_COLUMNS), 0);

            return [
                'stt' => $index + 1,
                'user' => $user,
                'user_id' => $user->id,
                'employee_code' => $user->employee_code,
                'status' => $statusLabels[$user->status ?? 'thu_viec'] ?? $user->status,
                'metrics' => $metrics,
                'money' => $money,
            ];
        });

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'columns' => self::MONEY_COLUMNS,
            'month' => $month,
            'rows' => $rows,
            'search' => $search,
        ];
    }

    private function approvedLeaveForDay(
        $leaves,
        Carbon $day
    ): ?LeaveRequest {
        $targetDay = $day
            ->copy()
            ->startOfDay();

        return $leaves->first(function (
            LeaveRequest $leave
        ) use ($targetDay) {
            if (
                !$leave->start_date
                || !$leave->end_date
            ) {
                return false;
            }

            return $targetDay->betweenIncluded(
                Carbon::parse(
                    $leave->start_date
                )->startOfDay(),
                Carbon::parse(
                    $leave->end_date
                )->startOfDay()
            );
        });
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

    private function userBranchForDate(User $user, Carbon $day): ?int
    {
        $targetDate = $day->toDateString();
        $histories = $user->workHistories
            ? $user->workHistories
                ->filter(fn ($h) => $h->effective_date && ($h->old_branch_id || $h->new_branch_id))
                ->sortBy('effective_date')
            : collect();

        if ($histories->isEmpty()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        foreach ($histories as $history) {
            $effectiveDate = Carbon::parse($history->effective_date)->toDateString();
            if ($targetDate < $effectiveDate) {
                return $history->old_branch_id ? (int) $history->old_branch_id : (int) $user->branch_id;
            }
        }

        $last = $histories->last();
        return $last->new_branch_id ? (int) $last->new_branch_id : (int) $user->branch_id;
    }
}