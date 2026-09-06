<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceAdjustmentLog;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class QuickAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $calendarStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);
        $weeks = collect(CarbonPeriod::create($calendarStart, $calendarEnd))->chunk(7);

        return view('admin.quick-attendance.index', [
            'users' => $this->manageableUsers(auth()->user())->get(),
            'month' => $month,
            'weeks' => $weeks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer'],
            'work_dates' => ['required', 'array', 'min:1'],
            'work_dates.*' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $userIds = array_values(array_unique($data['user_ids']));
        $workDates = array_values(array_unique($data['work_dates']));
        $reason = $data['reason'] ?? null;
        $successItems = [];
        $failedItems = [];

        foreach ($userIds as $userId) {
            $user = User::with('branch')->find($userId);

            foreach ($workDates as $date) {
                $displayDate = $this->displayDate($date);

                if (!$user) {
                    $failedItems[] = $this->failedItem('Không tìm thấy nhân viên', $displayDate, 'Không tìm thấy nhân viên.');
                    continue;
                }

                if (!$this->canUpdateTargetUser($user)) {
                    $failedItems[] = $this->failedItem($user->name, $displayDate, 'Bạn không có quyền cập nhật nhân viên này.');
                    continue;
                }

                $workDate = $this->parseWorkDate($date);

                if (!$workDate) {
                    $failedItems[] = $this->failedItem($user->name, $displayDate, 'Dữ liệu ngày công không hợp lệ.');
                    continue;
                }

                $assignment = $this->assignmentFor($user, $workDate);

                if (!$assignment) {
                    $failedItems[] = $this->failedItem($user->name, $workDate->format('d/m/Y'), 'Nhân viên chưa được gán ca trong ngày này.');
                    continue;
                }

                if (!$assignment->shift) {
                    $failedItems[] = $this->failedItem($user->name, $workDate->format('d/m/Y'), 'Không tìm thấy ca làm việc.');
                    continue;
                }

                $attendance = Attendance::where('user_id', $user->id)
                    ->forWorkDate($workDate)
                    ->latest()
                    ->first();

                if ($attendance?->is_locked) {
                    $failedItems[] = $this->failedItem($user->name, $workDate->format('d/m/Y'), 'Bản ghi công đã khóa.');
                    continue;
                }

                try {
                    $successItems[] = DB::transaction(function () use ($user, $workDate, $assignment, $attendance, $reason) {
                        [$checkin, $checkout] = $this->shiftTimes($assignment->shift, $workDate);
                        $workedMinutes = $checkin->diffInMinutes($checkout);
                        $oldValues = $attendance ? $this->attendanceSnapshot($attendance) : null;
                        $payload = $this->fullDayPayload($user, $assignment, $workDate, $checkin, $checkout, $workedMinutes, $reason);

                        if ($attendance) {
                            $attendance->update($payload);
                        } else {
                            $attendance = Attendance::create($payload);
                        }

                        AttendanceAdjustmentLog::create([
                            'attendance_id' => $attendance->id,
                            'user_id' => $user->id,
                            'work_date' => $workDate->toDateString(),
                            'action' => 'quick_mark_full_day',
                            'old_values' => $oldValues,
                            'new_values' => $this->attendanceSnapshot($attendance->fresh()),
                            'changed_by' => auth()->id(),
                            'note' => $reason,
                        ]);

                        return [
                            'employee' => $user->name,
                            'date' => $workDate->format('d/m/Y'),
                            'shift' => $assignment->shift->name,
                            'work_day' => 1,
                            'overtime_hours' => 0,
                        ];
                    });
                } catch (\Throwable $error) {
                    $failedItems[] = $this->failedItem(
                        $user->name,
                        $workDate->format('d/m/Y'),
                        $attendance ? 'Attendance không thể cập nhật.' : 'Attendance không thể tạo mới.'
                    );
                }
            }
        }

        $result = [
            'success_count' => count($successItems),
            'fail_count' => count($failedItems),
            'total_count' => count($successItems) + count($failedItems),
            'success_items' => $successItems,
            'failed_items' => $failedItems,
        ];

        $redirect = redirect()
            ->route('quick-attendance.index', ['month' => $this->redirectMonth($workDates)])
            ->with('quick_attendance_result', $result);

        if ($result['success_count'] > 0) {
            $redirect->with('success', 'Cập nhật công thành công');
        }

        if ($result['fail_count'] > 0) {
            $redirect->with('warning', 'Có ' . $result['fail_count'] . ' trường hợp không thể cập nhật');
        }

        if ($result['success_count'] === 0 && $result['fail_count'] > 0) {
            $redirect->withErrors(['quick_attendance' => 'Không có bản ghi nào được cập nhật.']);
        }

        return $redirect;
    }

    private function manageableUsers(User $viewer)
    {
        return User::with(['branch', 'position'])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->when($viewer->role === 'admin', fn ($query) => $query->whereIn('role', ['staff', 'cashier', 'manager']))
            ->when($viewer->role === 'manager', fn ($query) => $query->whereIn('role', ['staff', 'cashier'])->where('branch_id', $viewer->branch_id))
            ->orderBy('name');
    }

    private function canUpdateTargetUser(User $user): bool
    {
        $viewer = auth()->user();

        if ($viewer->role === 'admin') {
            return in_array($user->role, ['staff', 'cashier', 'manager'], true);
        }

        if ($viewer->role === 'manager') {
            return (int) $user->branch_id === (int) $viewer->branch_id
                && in_array($user->role, ['staff', 'cashier'], true);
        }

        return false;
    }

    private function assignmentFor(User $user, Carbon $workDate): ?ShiftAssignment
    {
        return ShiftAssignment::with('shift')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->latest('updated_at')
            ->first();
    }

    private function shiftTimes(Shift $shift, Carbon $workDate): array
    {
        $start = Carbon::parse($shift->start_at);
        $end = Carbon::parse($shift->end_at);
        $checkin = $workDate->copy()->setTime((int) $start->format('H'), (int) $start->format('i'), (int) $start->format('s'));
        $checkout = $workDate->copy()->setTime((int) $end->format('H'), (int) $end->format('i'), (int) $end->format('s'));

        if ($end->format('H:i:s') <= $start->format('H:i:s')) {
            $checkout->addDay();
        }

        return [$checkin, $checkout];
    }

    private function fullDayPayload(User $user, ShiftAssignment $assignment, Carbon $workDate, Carbon $checkin, Carbon $checkout, int $workedMinutes, ?string $reason): array
    {
        $payload = [
            'user_id' => $user->id,
            'shift_id' => $assignment->shift_id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'late_minutes' => 0,
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' => 0,
            'status' => 'completed',
            'note' => trim('Cập nhật công nhanh: ' . ($reason ?? '')),
        ];

        if (Schema::hasColumn('attendances', 'overtime_hours')) {
            $payload['overtime_hours'] = 0;
        }

        if (Schema::hasColumn('attendances', 'work_day')) {
            $payload['work_day'] = 1;
        }

        if (Schema::hasColumn('attendances', 'work_units')) {
            $payload['work_units'] = 1;
        }

        if (Schema::hasColumn('attendances', 'penalty_workday')) {
            $payload['penalty_workday'] = 0;
        }

        if (Schema::hasColumn('attendances', 'manual_updated_by')) {
            $payload['manual_updated_by'] = auth()->id();
        }

        if (Schema::hasColumn('attendances', 'manual_updated_at')) {
            $payload['manual_updated_at'] = now();
        }

        return $payload;
    }

    private function parseWorkDate(string $date): ?Carbon
    {
        try {
            $workDate = Carbon::createFromFormat('Y-m-d', $date);

            if ($workDate->format('Y-m-d') !== $date) {
                return null;
            }

            return $workDate->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function displayDate(string $date): string
    {
        return $this->parseWorkDate($date)?->format('d/m/Y') ?? $date;
    }

    private function redirectMonth(array $workDates): string
    {
        foreach ($workDates as $date) {
            $workDate = $this->parseWorkDate($date);

            if ($workDate) {
                return $workDate->format('Y-m');
            }
        }

        return now()->format('Y-m');
    }

    private function failedItem(string $employee, string $date, string $reason): array
    {
        return [
            'employee' => $employee,
            'date' => $date,
            'reason' => $reason,
        ];
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
            'work_day' => $attendance->work_day,
            'work_units' => $attendance->work_units ?? null,
            'status' => $attendance->status,
            'note' => $attendance->note,
        ];
    }
}
