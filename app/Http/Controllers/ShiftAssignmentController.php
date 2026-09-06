<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Setting;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Services\AttendanceAdjustmentLogger;
use App\Services\AttendanceCalculationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $users = $this->assignableUsersQuery($currentUser)->get();
        $showAllShifts = Setting::getBool('shift_assignment_show_all_shifts', true);
        $shifts = Shift::query()
            ->when(!$showAllShifts && in_array($currentUser->role, ['manager', 'cashier'], true), function ($query) use ($currentUser) {
                $query->whereHas('branches', fn ($branchQuery) => $branchQuery->whereKey($currentUser->branch_id));
            })
            ->orderBy('start_at')
            ->get();
        $branchShiftMap = Branch::query()
            ->with(['shifts' => fn ($query) => $query->select('shifts.id')])
            ->whereIn('id', $users->pluck('branch_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn (Branch $branch) => [$branch->id => $branch->shifts->pluck('id')->values()->all()]);

        $assignments = ShiftAssignment::with(['user.branch', 'shift'])
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->latest()
            ->get();

        $calendarStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = collect(CarbonPeriod::create($calendarStart, $calendarEnd))
            ->chunk(7);

        $holidayDates = $this->fixedHolidayDates($start);

        return view('shift_assignments.index', compact(
            'assignments',
            'branchShiftMap',
            'holidayDates',
            'month',
            'showAllShifts',
            'shifts',
            'users',
            'weeks'
        ));
    }

    public function create()
    {
        return redirect()->route('shift-assignments.index');
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'work_dates' => ['required', 'array', 'min:1'],
            'work_dates.*' => ['required', 'date_format:Y-m-d'],
        ]);

        $targetUsers = User::whereIn('id', $data['user_ids'])->get();
        $this->authorizeTargetUsers($targetUsers, $currentUser);
        $this->authorizeShiftForUsers((int) $data['shift_id'], $targetUsers);

        $workDates = array_values(array_unique($data['work_dates']));
        $strictMode = Setting::getBool('attendance_strict_mode', false);

        if ($strictMode) {
            foreach ($targetUsers as $targetUser) {
                if (!$targetUser->start_work_date) {
                    continue;
                }

                $startWorkDate = Carbon::parse($targetUser->start_work_date)->startOfDay();

                foreach ($workDates as $workDate) {
                    if (Carbon::parse($workDate)->lt($startWorkDate)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'work_dates' => 'Ngày này nhân viên chưa vào làm, không thể gán ca.',
                            ]);
                    }
                }
            }
        }

        $blockedAttendanceCount = $this->countExistingAttendancesForAssignments(
            $targetUsers,
            $workDates,
            (int) $data['shift_id']
        );

        if ($blockedAttendanceCount > 0) {
            return back()
                ->withInput()
                ->with('warning', 'Nhân viên đã có dữ liệu chấm công trong ngày này. Vui lòng dùng chức năng Đổi ca và tính lại công.');
        }

        foreach ($targetUsers as $targetUser) {
            foreach ($workDates as $workDate) {
                ShiftAssignment::updateOrCreate(
                    [
                        'user_id' => $targetUser->id,
                        'work_date' => $workDate,
                    ],
                    [
                        'shift_id' => $data['shift_id'],
                    ]
                );
            }
        }

        return redirect()
            ->route('shift-assignments.index', ['month' => Carbon::parse($workDates[0])->format('Y-m')])
            ->with('success', 'Đã gán ca cho ' . $targetUsers->count() . ' nhân viên.');
    }

    public function update(Request $request, ShiftAssignment $shiftAssignment)
    {
        $this->authorizeAssignment($shiftAssignment);

        $data = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
        ]);

        $attendance = $shiftAssignment->work_date
            ? $this->attendanceFor(
                $shiftAssignment->user_id,
                $shiftAssignment->work_date->toDateString()
            )
            : null;

        $assignmentChangesShift =
            (int) $shiftAssignment->shift_id
            !== (int) $data['shift_id'];

        $attendanceNeedsSync =
            $attendance
            && (int) $attendance->shift_id
                !== (int) $data['shift_id'];

        if (
            $attendance
            && ($assignmentChangesShift || $attendanceNeedsSync)
        ) {
            return redirect()
                ->route('shift-assignments.index', [
                    'month' => $shiftAssignment->work_date?->format('Y-m') ?? now()->format('Y-m'),
                ])
                ->with('warning', 'Nhân viên đã có dữ liệu chấm công trong ngày này. Vui lòng dùng chức năng Đổi ca và tính lại công.');
        }

        $this->authorizeShiftForUsers((int) $data['shift_id'], collect([$shiftAssignment->user]));

        $shiftAssignment->update([
            'shift_id' => $data['shift_id'],
        ]);

        return redirect()
            ->route('shift-assignments.index', [
                'month' => $shiftAssignment->work_date?->format('Y-m') ?? now()->format('Y-m'),
            ])
            ->with('success', 'Đã cập nhật phân ca.');
    }

    public function recalculate(Request $request, AttendanceCalculationService $calculator, AttendanceAdjustmentLogger $logger)
    {
        $currentUser = auth()->user();

        if (!in_array($currentUser->role, ['admin', 'manager'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'work_dates' => ['required', 'array', 'min:1'],
            'work_dates.*' => ['required', 'date_format:Y-m-d'],
        ]);

        $targetUsers = User::whereIn('id', $data['user_ids'])->get();
        $this->authorizeTargetUsers($targetUsers, $currentUser);
        $this->authorizeShiftForUsers((int) $data['shift_id'], $targetUsers);

        $shift = Shift::findOrFail($data['shift_id']);
        $workDates = array_values(array_unique($data['work_dates']));
        $updatedCount = 0;

        DB::transaction(function () use ($targetUsers, $workDates, $shift, $calculator, $logger, &$updatedCount) {
            foreach ($targetUsers as $targetUser) {
                foreach ($workDates as $workDate) {
                    $attendance = $this->attendanceFor($targetUser->id, $workDate);

                    if (!$attendance) {
                        continue;
                    }

                    if ($attendance->is_locked) {
                        abort(403, 'Bản ghi công đã khóa. Không thể đổi ca và tính lại công.');
                    }

                    $oldValues = $this->attendanceSnapshot($attendance);
                    $oldShiftId = $attendance->shift_id;

                    ShiftAssignment::updateOrCreate(
                        [
                            'user_id' => $targetUser->id,
                            'work_date' => $workDate,
                        ],
                        [
                            'shift_id' => $shift->id,
                        ]
                    );

                    $attendance->update(array_merge(
                        $calculator->recalculateForShift($attendance, $shift),
                        ['work_date' => $workDate]
                    ));
                    $freshAttendance = $attendance->fresh();
                    $newValues = $this->attendanceSnapshot($freshAttendance);

                    if ((int) $oldShiftId !== (int) $shift->id) {
                        $logger->log(
                            $freshAttendance,
                            'change_shift',
                            ['old_shift_id' => $oldShiftId] + $oldValues,
                            ['new_shift_id' => $shift->id] + $newValues,
                            'Đổi ca sau khi đã có dữ liệu chấm công.'
                        );
                    }

                    $logger->log(
                        $freshAttendance,
                        'recalculate_attendance',
                        $oldValues,
                        $newValues,
                        'Tính lại công theo ca mới.'
                    );

                    $updatedCount++;
                }
            }
        });

        if ($updatedCount === 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'work_dates' => 'Không tìm thấy dữ liệu chấm công để đổi ca và tính lại công.',
                ]);
        }

        return redirect()
            ->route('shift-assignments.index', ['month' => Carbon::parse($workDates[0])->format('Y-m')])
            ->with('success', 'Đã đổi ca và tính lại công cho ' . $updatedCount . ' bản ghi chấm công.');
    }

    public function destroy(ShiftAssignment $shiftAssignment)
    {
        $this->authorizeAssignment($shiftAssignment);

        $attendance = $shiftAssignment->work_date
            ? $this->attendanceFor($shiftAssignment->user_id, $shiftAssignment->work_date->toDateString())
            : null;

        if ($attendance?->is_locked) {
            abort(403, 'Bản ghi công đã khóa. Không thể xóa gán ca liên quan.');
        }

        $month = $shiftAssignment->work_date?->format('Y-m') ?? now()->format('Y-m');
        $shiftAssignment->delete();

        return redirect()
            ->route('shift-assignments.index', ['month' => $month])
            ->with('success', 'Đã xóa gán ca.');
    }

    private function authorizeAssignment(ShiftAssignment $shiftAssignment): void
    {
        $currentUser = auth()->user();
        $shiftAssignment->loadMissing('user');

        $this->authorizeTargetUsers(collect([$shiftAssignment->user]), $currentUser);
    }

    private function fixedHolidayDates(Carbon $month): array
    {
        $year = $month->year;

        return [
            Carbon::create($year, 1, 1)->toDateString(),
            Carbon::create($year, 4, 30)->toDateString(),
            Carbon::create($year, 5, 1)->toDateString(),
            Carbon::create($year, 9, 2)->toDateString(),
        ];
    }

    private function countExistingAttendancesForAssignments($targetUsers, array $workDates, int $newShiftId): int
    {
        if ($targetUsers->isEmpty() || empty($workDates)) {
            return 0;
        }

        $blockedCount = 0;

        foreach ($targetUsers as $targetUser) {
            foreach ($workDates as $workDate) {
                $assignment = ShiftAssignment::query()
                    ->where('user_id', $targetUser->id)
                    ->whereDate('work_date', $workDate)
                    ->first();

                $attendance = $this->attendanceFor(
                    $targetUser->id,
                    $workDate
                );

                if (!$attendance) {
                    continue;
                }

                /*
                 * Nếu phân ca đã đúng ca được chọn nhưng attendance
                 * vẫn giữ shift_id cũ thì đây vẫn là dữ liệu lệch.
                 * Trước đây đoạn code "continue" ở đây làm trường hợp
                 * này không bao giờ hiện nút "Đổi ca và tính lại công".
                 */
                if (
                    $assignment
                    && (int) $assignment->shift_id === $newShiftId
                    && (int) $attendance->shift_id === $newShiftId
                ) {
                    continue;
                }

                $blockedCount++;
            }
        }

        return $blockedCount;
    }

    private function assignmentHasAttendance(ShiftAssignment $shiftAssignment): bool
    {
        if (!$shiftAssignment->work_date) {
            return false;
        }

        $workDate = $shiftAssignment->work_date->toDateString();

        return $this->attendanceExistsFor($shiftAssignment->user_id, $workDate);
    }

    private function attendanceExistsFor(int $userId, string $workDate): bool
    {
        return (bool) $this->attendanceFor($userId, $workDate);
    }

    private function attendanceFor(int $userId, string $workDate): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->forWorkDate($workDate)
            ->latest('checkin_at')
            ->first();
    }

    private function attendanceSnapshot(Attendance $attendance): array
    {
        return [
            'shift_id' => $attendance->shift_id,
            'work_date' => $attendance->work_date?->toDateString(),
            'checkin_at' => $attendance->checkin_at?->toDateTimeString(),
            'checkout_at' => $attendance->checkout_at?->toDateTimeString(),
            'late_minutes' => $attendance->late_minutes,
            'worked_minutes' => $attendance->worked_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'overtime_hours' => $attendance->overtime_hours,
            'work_day' => $attendance->work_day ?? null,
            'penalty_workday' => $attendance->penalty_workday ?? null,
            'status' => $attendance->status,
        ];
    }

    private function assignableUsersQuery(User $currentUser)
    {
        return User::with('branch')
            ->whereIn('role', ['manager', 'staff', 'cashier'])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->where(function ($query) {
                $query->whereNull('branch_id')
                    ->orWhereHas('branch', fn ($q) => $q->where('is_active', true));
            })
            ->when(in_array($currentUser->role, ['manager', 'cashier'], true), fn ($query) => $query->where('branch_id', $currentUser->branch_id))
            ->orderBy('name');
    }

    private function authorizeTargetUsers($targetUsers, User $currentUser): void
    {
        if ($targetUsers->isEmpty() || $targetUsers->contains(fn (?User $user) => !$user)) {
            abort(403, 'Không tìm thấy nhân sự cần gán ca.');
        }

        if ($targetUsers->contains(fn (User $user) => !in_array($user->role, ['manager', 'staff', 'cashier'], true))) {
            abort(403, 'Chỉ được gán ca cho quản lý, nhân viên hoặc thu ngân.');
        }

        if ($targetUsers->contains(fn (User $user) => $user->status === 'da_nghi')) {
            abort(403, 'Không thể gán ca cho nhân sự đã nghỉ.');
        }

        if ($targetUsers->contains(fn (User $user) => $user->branch && !$user->branch->is_active)) {
            abort(403, 'Không thể gán ca cho nhân sự thuộc cơ sở đã ngưng hoạt động.');
        }

        if (in_array($currentUser->role, ['manager', 'cashier'], true) && $targetUsers->contains(fn (User $user) => (int) $user->branch_id !== (int) $currentUser->branch_id)) {
            abort(403, 'Bạn chỉ được gán ca cho nhân sự trong cùng chi nhánh.');
        }
    }

    private function authorizeShiftForUsers(int $shiftId, $targetUsers): void
    {
        if (Setting::getBool('shift_assignment_show_all_shifts', true)) {
            return;
        }

        $branchIds = $targetUsers->pluck('branch_id')->filter()->unique()->values();

        if ($branchIds->count() !== $targetUsers->count()) {
            abort(422, 'Nhân sự chưa được gán chi nhánh nên chưa thể kiểm tra ca được phép.');
        }

        $allowedBranchIds = DB::table('branch_shift')
            ->where('shift_id', $shiftId)
            ->whereIn('branch_id', $branchIds)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($branchIds->contains(fn ($branchId) => !in_array((int) $branchId, $allowedBranchIds, true))) {
            abort(422, 'Ca này chưa được admin cấu hình cho một hoặc nhiều chi nhánh đã chọn.');
        }
    }
}
