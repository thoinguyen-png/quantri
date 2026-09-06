<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceAdjustmentLogger;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceStatusService $attendanceStatusService)
    {
        $user = auth()->user();
        $filters = [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'branch_id' => (string) $request->query('branch_id', ''),
            'user_id' => (string) $request->query('user_id', ''),
            'role' => (string) $request->query('role', ''),
            'shift_id' => (string) $request->query('shift_id', ''),
            'status' => (string) $request->query('status', ''),
            'locked' => (string) $request->query('locked', ''),
        ];

        $query = Attendance::with(['user.branch', 'branch', 'shift'])
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->forBranch($user->branch_id);
            })
            ->when($filters['date_from'] !== '' && $filters['date_to'] !== '', fn ($query) => $query->forWorkDateBetween($filters['date_from'], $filters['date_to']))
            ->when($filters['date_from'] !== '' && $filters['date_to'] === '', function ($query) use ($filters) {
                $query->where(function ($dateQuery) use ($filters) {
                    $dateQuery->whereDate('work_date', '>=', $filters['date_from'])
                        ->orWhere(function ($fallback) use ($filters) {
                            $fallback->whereNull('work_date')->whereDate('checkin_at', '>=', $filters['date_from']);
                        });
                });
            })
            ->when($filters['date_from'] === '' && $filters['date_to'] !== '', function ($query) use ($filters) {
                $query->where(function ($dateQuery) use ($filters) {
                    $dateQuery->whereDate('work_date', '<=', $filters['date_to'])
                        ->orWhere(function ($fallback) use ($filters) {
                            $fallback->whereNull('work_date')->whereDate('checkin_at', '<=', $filters['date_to']);
                        });
                });
            })
            ->when($filters['branch_id'] !== '', fn ($query) => $query->forBranch($filters['branch_id']))
            ->when($filters['user_id'] !== '', fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(in_array($filters['role'], ['admin', 'manager', 'staff', 'cashier'], true), fn ($query) => $query->whereHas('user', fn ($q) => $q->where('role', $filters['role'])))
            ->when($filters['shift_id'] !== '', fn ($query) => $query->where('shift_id', $filters['shift_id']))
            ->when(in_array($filters['locked'], ['locked', 'unlocked'], true), fn ($query) => $query->where('is_locked', $filters['locked'] === 'locked'))
            ->orderByRaw('COALESCE(work_date, DATE(checkin_at)) DESC')
            ->latest('checkin_at');

        $allAttendances = $query->get()
            ->map(function (Attendance $attendance) use ($attendanceStatusService) {
                $workDate = $attendance->work_date
                    ? Carbon::parse($attendance->work_date)
                    : Carbon::parse($attendance->checkin_at);

                $attendance->setAttribute('display_status', $attendanceStatusService->resolveDailyStatus(
                    $attendance->user,
                    $workDate,
                    $attendance
                ));

                return $attendance;
            })
            ->when($filters['status'] !== '', fn ($items) => $items->filter(
                fn (Attendance $attendance) => ($attendance->display_status['key'] ?? null) === $filters['status']
            ))
            ->values();

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $attendances = new LengthAwarePaginator(
            $allAttendances->forPage($page, $perPage)->values(),
            $allAttendances->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $branches = \App\Models\Branch::active()
            ->when($user->role === 'manager', fn ($branchQuery) => $branchQuery->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();
        $users = $this->manageableUsers();
        $shifts = Shift::orderBy('start_at')->get();
        $statusOptions = [
            'full_day' => 'Đủ công',
            'late' => 'Đi trễ',
            'early_leave' => 'Về sớm',
            'missing_checkout' => 'Thiếu checkout',
            'insufficient_work' => 'Thiếu công',
        ];

        return view('attendance_reports.index', compact('attendances', 'branches', 'filters', 'shifts', 'statusOptions', 'users'));
    }

    public function create()
    {
        $users = $this->manageableUsers();
        $shifts = Shift::orderBy('start_at')->get();

        return view('attendance_reports.create', compact('shifts', 'users'));
    }

    public function store(
        Request $request,
        AttendanceCalculationService $calculator
    ) {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'checkin_at' => ['required', 'date'],
            'checkout_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $targetUser = User::findOrFail($data['user_id']);
        $this->authorizeUserAttendance($targetUser);

        $checkin = Carbon::parse($data['checkin_at']);
        $workDate = $this->workDateFor($targetUser->id, $data['shift_id'] ?? null, $checkin);
        $exists = Attendance::where('user_id', $targetUser->id)
            ->forWorkDate($workDate)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['checkin_at' => 'Nhân sự này đã có dữ liệu chấm công trong ngày đã chọn. Hãy sửa bản ghi hiện có.']);
        }

        $checkout = $this->normalizeManualCheckout(
            $checkin,
            !empty($data['checkout_at']) ? Carbon::parse($data['checkout_at']) : null
        );

        if ($checkout && $checkout->lessThanOrEqualTo($checkin)) {
            return back()
                ->withInput()
                ->withErrors(['checkout_at' => 'Giờ checkout phải sau giờ checkin hoặc là giờ ra của ngày hôm sau.']);
        }

        $metrics = $this->attendanceMetrics(
            $data['shift_id'] ?? null,
            $checkin,
            $checkout,
            Carbon::parse($workDate),
            $calculator
        );

        $creator = auth()->user();
        $targetBranchId = $creator?->role === 'manager' ? $creator->branch_id : ($targetUser->branch_id);

        Attendance::create([
            'user_id' => $targetUser->id,
            'branch_id' => $targetBranchId,
            'shift_id' => $data['shift_id'] ?? null,
            'work_date' => $workDate,
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'worked_minutes' => $metrics['worked_minutes'],
            'late_minutes' => $metrics['late_minutes'],
            'overtime_minutes' => $metrics['overtime_minutes'],
            'overtime_hours' => $metrics['overtime_hours'],
            'work_day' => $metrics['work_day'],
            'status' => $checkout ? 'completed' : 'checked_in',
            'note' => $data['note'] ?? 'Admin/quản lý bổ sung công thủ công.',
        ]);

        return redirect()
            ->route('attendance-reports.index')
            ->with('success', 'Đã tạo chấm công thủ công.');
    }

    public function edit(Attendance $attendance)
    {
        $this->authorizeAttendance($attendance);

        if ($attendance->is_locked && auth()->user()?->role !== 'admin') {
            abort(403, 'Bản ghi công đã khóa. Manager không được sửa.');
        }

        return view('attendance_reports.edit', compact('attendance'));
    }

    public function update(
        Request $request,
        Attendance $attendance,
        AttendanceAdjustmentLogger $logger,
        AttendanceCalculationService $calculator
    ) {
        $this->authorizeAttendance($attendance);
        $this->ensureUnlockedForWrite($attendance);
        $oldValues = $this->attendanceSnapshot($attendance);

        $data = $request->validate([
            'checkin_at' => ['required', 'date'],
            'checkout_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $checkin = Carbon::parse($data['checkin_at']);
        $checkout = $this->normalizeManualCheckout(
            $checkin,
            $data['checkout_at'] ? Carbon::parse($data['checkout_at']) : null
        );

        if ($checkout && $checkout->lessThanOrEqualTo($checkin)) {
            return back()
                ->withInput()
                ->withErrors(['checkout_at' => 'Giờ checkout phải sau giờ checkin hoặc là giờ ra của ngày hôm sau.']);
        }

        $workDate = $this->workDateFor(
            $attendance->user_id,
            $attendance->shift_id,
            $checkin
        );

        $metrics = $this->attendanceMetrics(
            $attendance->shift_id,
            $checkin,
            $checkout,
            Carbon::parse($workDate),
            $calculator
        );

        $attendance->update([
            'work_date' => $workDate,
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'worked_minutes' => $metrics['worked_minutes'],
            'late_minutes' => $metrics['late_minutes'],
            'overtime_minutes' => $metrics['overtime_minutes'],
            'overtime_hours' => $metrics['overtime_hours'],
            'work_day' => $metrics['work_day'],
            'status' => $checkout ? 'completed' : 'checked_in',
            'note' => $data['note'],
        ]);

        $logger->log(
            $attendance->fresh(),
            'update_attendance',
            $oldValues,
            $this->attendanceSnapshot($attendance->fresh()),
            'Sửa công thủ công từ trang báo cáo.'
        );

        return redirect()
            ->route('attendance-reports.index')
            ->with('success', 'Đã cập nhật công.');
    }

    public function destroy(Attendance $attendance, AttendanceAdjustmentLogger $logger)
    {
        $this->authorizeAttendance($attendance);
        $this->ensureUnlockedForWrite($attendance);
        $oldValues = $this->attendanceSnapshot($attendance);

        $logger->log(
            $attendance,
            'delete_attendance',
            $oldValues,
            null,
            'Xóa bản ghi chấm công.'
        );

        $attendance->delete();

        return redirect()
            ->route('attendance-reports.index')
            ->with('success', 'Đã xóa bản ghi chấm công.');
    }

    public function lock(Attendance $attendance, AttendanceAdjustmentLogger $logger)
    {
        $this->authorizeAdmin();
        $oldValues = $this->attendanceSnapshot($attendance);

        $attendance->forceFill([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ])->save();

        $logger->log(
            $attendance->fresh(),
            'lock_attendance',
            $oldValues,
            $this->attendanceSnapshot($attendance->fresh()),
            'Khóa công.'
        );

        return redirect()
            ->route('attendance-reports.index')
            ->with('success', 'Đã khóa công.');
    }

    public function unlock(Attendance $attendance, AttendanceAdjustmentLogger $logger)
    {
        $this->authorizeAdmin();
        $oldValues = $this->attendanceSnapshot($attendance);

        $attendance->forceFill([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        $logger->log(
            $attendance->fresh(),
            'unlock_attendance',
            $oldValues,
            $this->attendanceSnapshot($attendance->fresh()),
            'Mở khóa công.'
        );

        return redirect()
            ->route('attendance-reports.index')
            ->with('success', 'Đã mở khóa công.');
    }

    private function manageableUsers()
    {
        $viewer = auth()->user();

        return User::with('branch')
            ->whereIn('role', ['staff', 'manager', 'cashier'])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->where(function ($query) {
                $query->whereNull('branch_id')
                    ->orWhereHas('branch', fn ($q) => $q->where('is_active', true));
            })
            ->when($viewer->role === 'manager', function ($query) use ($viewer) {
                $query->whereIn('role', ['staff', 'cashier'])
                    ->where('branch_id', $viewer->branch_id);
            })
            ->orderBy('name')
            ->get();
    }

    private function authorizeAttendance(Attendance $attendance): void
    {
        $viewer = auth()->user();
        if ($viewer?->role === 'admin') {
            return;
        }

        $attendance->loadMissing(['user', 'branch']);
        if ($viewer?->role === 'manager') {
            $effectiveBranchId = $attendance->branch_id ?? $attendance->user?->branch_id;
            if ((int) $effectiveBranchId === (int) $viewer->branch_id) {
                return;
            }
        }

        $this->authorizeUserAttendance($attendance->user);
    }

    private function ensureUnlockedForWrite(Attendance $attendance): void
    {
        if (!$attendance->is_locked) {
            return;
        }

        if (auth()->user()?->role === 'admin' && request()->boolean('confirm_locked')) {
            return;
        }

        abort(403, 'Bản ghi công đã khóa. Manager không được sửa/xóa. Admin cần xác nhận khi sửa dữ liệu đã khóa.');
    }

    private function authorizeAdmin(): void
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }
    }

    private function authorizeUserAttendance(User $targetUser): void
    {
        $viewer = auth()->user();

        if ($viewer->role === 'admin') {
            return;
        }

        if (
            $viewer->role === 'manager' &&
            in_array($targetUser->role, ['staff', 'cashier'], true) &&
            $targetUser->branch_id === $viewer->branch_id
        ) {
            return;
        }

        abort(403);
    }

    private function attendanceMetrics(
        ?int $shiftId,
        Carbon $checkin,
        ?Carbon $checkout,
        ?Carbon $workDate,
        AttendanceCalculationService $calculator
    ): array {
        /*
         * Chưa có ca:
         * - vẫn giữ worked_minutes thực tế để đối chiếu lịch sử;
         * - không tính đi trễ;
         * - không tính OT;
         * - không tính công.
         */
        if (!$shiftId) {
            return [
                'worked_minutes' => $checkout
                    ? (int) $checkin->diffInMinutes($checkout)
                    : 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
                'work_day' => 0.0,
            ];
        }

        $shift = Shift::find($shiftId);

        if (!$shift) {
            return [
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
                'work_day' => 0.0,
            ];
        }

        /*
         * Chỉ có checkin, chưa checkout:
         * lưu giờ vào nhưng chưa được tính công/OT.
         */
        if (!$checkout) {
            return [
                'worked_minutes' => 0,
                'late_minutes' => \App\Services\AttendanceLateCalculator::minutes(
                    $shift,
                    $workDate ?? $checkin,
                    $checkin
                ),
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
                'work_day' => 0.0,
            ];
        }

        /*
         * Dùng đúng AttendanceCalculationService giống luồng chấm công thật.
         *
         * Nhờ đó:
         * - checkin sớm không cộng vào giờ công;
         * - worked_minutes chỉ nằm trong khung giờ ca;
         * - OT chỉ tính sau giờ kết thúc ca;
         * - OT không bù phần công chính bị thiếu.
         */
        $temporaryAttendance = new Attendance();
        $temporaryAttendance->forceFill([
            'shift_id' => $shift->id,
            'work_date' => ($workDate ?? $checkin)->toDateString(),
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'status' => 'completed',
        ]);

        $calculated = $calculator->recalculateForShift(
            $temporaryAttendance,
            $shift
        );

        return [
            'worked_minutes' => (int) (
                $calculated['worked_minutes']
                ?? 0
            ),
            'late_minutes' => (int) (
                $calculated['late_minutes']
                ?? 0
            ),
            'overtime_minutes' => (int) (
                $calculated['overtime_minutes']
                ?? 0
            ),
            'overtime_hours' => (float) (
                $calculated['overtime_hours']
                ?? round(
                    ((int) ($calculated['overtime_minutes'] ?? 0)) / 60,
                    2
                )
            ),
            'work_day' => (float) (
                $calculated['work_day']
                ?? 0
            ),
        ];
    }

    private function normalizeManualCheckout(Carbon $checkin, ?Carbon $checkout): ?Carbon
    {
        if (!$checkout) {
            return null;
        }

        if ($checkout->isSameDay($checkin) && $checkout->lessThanOrEqualTo($checkin)) {
            return $checkout->copy()->addDay();
        }

        return $checkout;
    }

    private function workDateFor(int $userId, ?int $shiftId, Carbon $checkin): string
    {
        if ($shiftId) {
            $assignment = ShiftAssignment::query()
                ->where('user_id', $userId)
                ->where('shift_id', $shiftId)
                ->whereDate('work_date', $checkin->toDateString())
                ->latest('updated_at')
                ->first();

            if ($assignment?->work_date) {
                return $assignment->work_date->toDateString();
            }
        }

        return $checkin->toDateString();
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
            'status' => $attendance->status,
            'is_locked' => (bool) $attendance->is_locked,
            'locked_at' => $attendance->locked_at?->toDateTimeString(),
            'locked_by' => $attendance->locked_by,
            'note' => $attendance->note,
        ];
    }
}