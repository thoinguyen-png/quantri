<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSupplementRequest;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceDailyMetricsService;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AttendanceStatisticController extends Controller
{
    public function index(
        Request $request,
        AttendanceStatusService $attendanceStatusService,
        AttendanceDailyMetricsService $dailyMetricsService
    ) {
        $viewer = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $branchId = $request->input('branch_id');
        $search = trim((string) $request->input('search', ''));
        $searchId = ctype_digit($search)
            ? (int) ltrim($search, '0')
            : null;

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

        $attendances = Attendance::with('shift')
            ->whereIn('user_id', $userIds)
            ->forWorkDateBetween($start, $end)
            ->get()
            ->groupBy(function (Attendance $attendance) {
                $workDate = $attendance->work_date
                    ?->toDateString()
                    ?? $attendance->checkin_at
                    ?->toDateString()
                    ?? $attendance->checkout_at
                    ?->toDateString();

                $workDate ??= 'unknown-'
                    . $attendance->id;

                return $attendance->user_id
                    . '|'
                    . $workDate;
            });

        $assignments = ShiftAssignment::with('shift')
            ->whereIn('user_id', $userIds)
            ->where(function ($query) use ($start, $end) {
                $query->whereNull('work_date')
                    ->orWhereBetween('work_date', [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]);
            })
            ->get()
            ->groupBy('user_id');

        /*
         * Đơn phép dùng khoảng start_date → end_date.
         *
         * Chỉ lấy các đơn có khoảng ngày giao với tháng
         * đang xem, sau đó nhóm theo nhân viên để tìm
         * đơn áp dụng cho từng ngày.
         */
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

        /*
         * Đơn bổ sung công đã được duyệt để service có thể
         * thêm badge "Đã duyệt đơn" khi cần.
         */
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

        $rows = $users->map(function (User $user) use (
            $days,
            $attendances,
            $assignments,
            $leaves,
            $supplements,
            $branchId,
            $attendanceStatusService,
            $dailyMetricsService
        ) {
            $cells = $days->mapWithKeys(function (Carbon $day) use (
                $user,
                $attendances,
                $assignments,
                $leaves,
                $supplements,
                $branchId,
                $attendanceStatusService,
                $dailyMetricsService
            ) {
                $dateString = $day->toDateString();
                $cellKey = $user->id . '|' . $dateString;

                $attendance = $attendances
                    ->get($cellKey)
                    ?->sortByDesc('created_at')
                    ->first();

                if (!empty($branchId)) {
                    $attendanceBranchId = $attendance ? ($attendance->branch_id ?? $attendance->user?->branch_id) : null;
                    if ($attendance && (int) $attendanceBranchId !== (int) $branchId) {
                        return [
                            $dateString => $this->makeOffCell($day, 'Thuộc cơ sở khác'),
                        ];
                    }

                    if (!$attendance) {
                        $userBranchOnDay = $this->userBranchForDate($user, $day);
                        if ($userBranchOnDay !== null && (int) $userBranchOnDay !== (int) $branchId) {
                            return [
                                $dateString => $this->makeOffCell($day, 'Thuộc cơ sở khác'),
                            ];
                        }
                    }
                }

                $assignment = $this->effectiveAssignmentForDay(
                    $assignments->get($user->id, collect()),
                    $day
                );

                $leave = $this->approvedLeaveForDay(
                    $leaves->get(
                        $user->id,
                        collect()
                    ),
                    $day
                );

                $supplement = $supplements->get($cellKey);

                return [
                    $dateString => $this->makeCell(
                        $user,
                        $attendance,
                        $assignment,
                        $leave,
                        $supplement,
                        $day,
                        $attendanceStatusService,
                        $dailyMetricsService
                    ),
                ];
            });

            $totalOvertimeMinutes = (int) $cells->sum(
                fn (array $cell): int =>
                    (int) (
                        $cell['overtime_minutes']
                        ?? 0
                    )
            );

            $totalWorkUnits = (float) $cells->sum(
                fn (array $cell): float =>
                    (float) (
                        $cell['work_units']
                        ?? 0
                    )
            );

            $totalWorkWithOvertime = (float) $cells->sum(
                fn (array $cell): float =>
                    (float) (
                        $cell['work_with_overtime']
                        ?? 0
                    )
            );

            return [
                'user' => $user,
                'cells' => $cells,
                'total_overtime_minutes' =>
                    $totalOvertimeMinutes,
                'total_overtime_hours' => round(
                    $totalOvertimeMinutes / 60,
                    1
                ),
                'total_work_units' => round(
                    $totalWorkUnits,
                    2
                ),
                'total_work_with_overtime' => round(
                    $totalWorkWithOvertime,
                    2
                ),
            ];
        });

        $totalOvertimeMinutes = (int) $rows->sum(
            fn (array $row): int =>
                (int) (
                    $row['total_overtime_minutes']
                    ?? 0
                )
        );

        $summary = [
            'totalEmployees' => $users->count(),
            'totalOvertimeMinutes' =>
                $totalOvertimeMinutes,

            'totalOvertimeHours' => round(
                $totalOvertimeMinutes / 60,
                1
            ),

            /*
             * Một ngày có thể vừa đi trễ vừa mang trạng thái chính khác
             * (ví dụ thiếu checkout), nên đếm theo late_minutes.
             * Đây cũng là nguồn mà bảng lương sử dụng.
             */
            'lateCount' => $rows->sum(
                fn ($row) =>
                    $row['cells']
                        ->filter(
                            fn (array $cell): bool =>
                                (int) (
                                    $cell['late_minutes']
                                    ?? 0
                                ) > 0
                        )
                        ->count()
            ),

            'absentCount' => $rows->sum(
                fn ($row) =>
                $row['cells']
                    ->where('status', 'absent_without_leave')
                    ->count()
            ),
        ];

        return view('attendance_statistics.index', compact(
            'branches',
            'branchId',
            'days',
            'month',
            'rows',
            'search',
            'summary'
        ));
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

            $leaveStart = Carbon::parse(
                $leave->start_date
            )->startOfDay();

            $leaveEnd = Carbon::parse(
                $leave->end_date
            )->startOfDay();

            return $targetDay->betweenIncluded(
                $leaveStart,
                $leaveEnd
            );
        });
    }

    private function effectiveAssignmentForDay(
        $assignments,
        Carbon $day
    ): ?ShiftAssignment {
        $dateAssignment = $assignments
            ->filter(
                fn (ShiftAssignment $assignment) =>
                $assignment->work_date?->toDateString()
                    === $day->toDateString()
            )
            ->sortByDesc('updated_at')
            ->first();

        if ($dateAssignment) {
            return $dateAssignment;
        }

        return $assignments
            ->filter(
                fn (ShiftAssignment $assignment) =>
                !$assignment->work_date
            )
            ->filter(
                fn (ShiftAssignment $assignment) =>
                $assignment->created_at->lessThanOrEqualTo(
                    $day->copy()->endOfDay()
                )
            )
            ->sortByDesc('created_at')
            ->first();
    }

    private function makeCell(
        User $user,
        ?Attendance $attendance,
        ?ShiftAssignment $assignment,
        ?LeaveRequest $leave,
        ?AttendanceSupplementRequest $supplement,
        Carbon $day,
        AttendanceStatusService $attendanceStatusService,
        AttendanceDailyMetricsService $dailyMetricsService
    ): array {
        $display = $attendanceStatusService->resolveDailyStatus(
            $user,
            $day,
            $attendance,
            $assignment,
            $leave,
            $supplement
        );

        /*
         * Phân ca của đúng ngày là nguồn ca hiện hành.
         * attendance.shift_id chỉ là snapshot lúc chấm công và có thể
         * còn giữ ca cũ nếu quản lý đã đổi phân ca sau đó.
         */
        $shift = $assignment?->shift
            ?? $attendance?->shift;

        $metrics = $dailyMetricsService->calculate(
            $attendance,
            $assignment,
            $day,
            $display['key']
        );

        return [
            /*
             * Trạng thái chuẩn từ AttendanceStatusService.
             */
            'status' => $display['key'],
            'status_key' => $display['key'],
            'label' => $display['label'],
            'subtitle' => $display['subtitle'],
            'work_label' => $display['work_label'],
            'work_status' => $display['work_status'],
            'status_classes' => $display['classes'],
            'status_color' => $display['color'],
            'status_icon' => $display['icon'],
            'badges' => $display['badges'],

            'shift' => $shift?->name,
            'time' => $this->timeText(
                $attendance,
                $shift,
                $day,
                $display['subtitle']
            ),
            'work_units' =>
                $metrics['work_units'],
            'work_with_overtime' =>
                $metrics['work_with_overtime'],

            /*
             * overtime_minutes là dữ liệu gốc.
             * Tổng tháng không cộng overtime_hours
             * đã làm tròn theo từng ngày.
             */
            'overtime_minutes' =>
                $metrics['overtime_minutes'],
            'overtime_hours' =>
                $metrics['overtime_hours'],
            'late_minutes' =>
                $metrics['late_minutes'],
            'note' => $attendance?->note
                ?: $display['subtitle'],
        ];
    }




    private function timeText(
        ?Attendance $attendance,
        $shift,
        Carbon $day,
        string $fallback
    ): string {
        if ($attendance) {
            return sprintf(
                '%s - %s',
                $attendance->checkin_at?->format('H:i')
                    ?? '--:--',
                $attendance->checkout_at?->format('H:i')
                    ?? '--:--'
            );
        }

        if ($shift) {
            return $this->shiftTimeText($shift, $day);
        }

        return $fallback;
    }

    private function shiftTimeText(
        $shift,
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


    private function shiftDateTimeFor(
        $shift,
        Carbon $day,
        string $field
    ): Carbon {
        $time = Carbon::parse($shift->{$field});

        $dateTime = $day->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at') {
            $start = Carbon::parse($shift->start_at);
            $end = Carbon::parse($shift->end_at);

            if (
                $end->format('H:i:s')
                <= $start->format('H:i:s')
            ) {
                $dateTime->addDay();
            }
        }

        return $dateTime;
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

    private function makeOffCell(Carbon $day, string $note = 'Nghỉ'): array
    {
        return [
            'status' => 'no_shift',
            'status_key' => 'no_shift',
            'label' => 'Nghỉ',
            'subtitle' => $note,
            'work_label' => 'Nghỉ',
            'work_status' => 'off',
            'status_classes' => 'status-badge status-badge--off',
            'status_color' => '#6b7280',
            'status_icon' => 'off',
            'badges' => [],
            'shift' => null,
            'time' => '--:--',
            'work_units' => 0.0,
            'work_with_overtime' => 0.0,
            'overtime_minutes' => 0,
            'overtime_hours' => 0.0,
            'late_minutes' => 0,
            'note' => $note,
        ];
    }
}