<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSupplementRequest;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceLateCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceSupplementRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $filters = [
            'search' => trim((string) request('search', '')),
            'branch_id' => (string) request('branch_id', ''),
            'role' => (string) request('role', ''),
            'status' => (string) request('status', ''),
            'date_from' => (string) request('date_from', ''),
            'date_to' => (string) request('date_to', ''),
            'shift_id' => (string) request('shift_id', ''),
            'reviewed_by' => (string) request('reviewed_by', ''),
            'created_from' => (string) request('created_from', ''),
            'created_to' => (string) request('created_to', ''),
        ];

        $requests = AttendanceSupplementRequest::with(['user.branch', 'shift', 'reviewer', 'creator', 'updater'])
            ->when(in_array($user->role, ['staff', 'cashier'], true), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereHas('user', function ($userQuery) use ($user) {
                            $userQuery
                                ->whereIn('role', ['staff', 'cashier'])
                                ->where('branch_id', $user->branch_id);
                    });
                });
            })
            ->when($filters['search'] !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")))
            ->when($filters['branch_id'] !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('branch_id', $filters['branch_id'])))
            ->when(in_array($filters['role'], ['admin', 'manager', 'staff', 'cashier'], true), fn ($query) => $query->whereHas('user', fn ($q) => $q->where('role', $filters['role'])))
            ->when(in_array($filters['status'], ['pending', 'approved', 'rejected'], true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('work_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('work_date', '<=', $filters['date_to']))
            ->when($filters['shift_id'] !== '', fn ($query) => $query->where('shift_id', $filters['shift_id']))
            ->when($filters['reviewed_by'] !== '', fn ($query) => $query->where('reviewed_by', $filters['reviewed_by']))
            ->when($filters['created_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when($filters['created_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $branches = \App\Models\Branch::active()
            ->when($user->role === 'manager', fn ($query) => $query->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();
        $shifts = Shift::orderBy('start_at')->get();
        $reviewers = User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get();

        return view('attendance_supplements.index', compact('branches', 'filters', 'requests', 'reviewers', 'shifts'));
    }

    public function create()
    {
        $employees = $this->selectableUsersFor(auth()->user())->get();

        $supplementLimitMode = $this->supplementLimitMode();
        $supplementDateRestricted = auth()->user()->role !== 'admin'
            && $supplementLimitMode === 'last_3_days';

        return view('attendance_supplements.create', [
            'employees' => $employees,
            'supplementLimitMode' => $supplementLimitMode,
            'supplementDateRestricted' => $supplementDateRestricted,
            'supplementDateMin' => today()->subDays(2)->toDateString(),
            'supplementDateMax' => today()->toDateString(),
        ]);
    }

    public function dayInfo(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
        ]);

        $selectedUser = User::findOrFail($data['user_id']);
        $this->authorizeSelectedUser($selectedUser);

        $workDate = Carbon::parse($data['work_date'])->startOfDay();
        $this->ensureSupplementDateAllowed($workDate);

        [$attendance, $assignment, $shift] = $this->dayContext($selectedUser->id, $workDate);

        return response()->json([
            'date' => $workDate->toDateString(),
            'user_id' => $selectedUser->id,
            'user_name' => $selectedUser->name,
            'shift_id' => $shift?->id,
            'shift_name' => $shift?->name,
            'shift_time' => $shift ? $this->shiftTimeText($shift, $workDate) : null,
            'segments' => [],
            'has_attendance' => (bool) $attendance,
            'checkin_time' => $attendance?->checkin_at?->format('H:i'),
            'checkout_time' => $attendance?->checkout_at?->format('H:i'),
            'attendance_status' => $attendance?->status,
            'assignment_id' => $assignment?->id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'requested_checkin_time' => ['nullable', 'date_format:H:i'],
            'requested_checkout_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do bổ sung công.',
            'reason.min' => 'Lý do bổ sung công phải có ít nhất 5 ký tự.',
            'reason.max' => 'Lý do bổ sung công không được vượt quá 2000 ký tự.',
            'requested_checkin_time.required' => 'Vui lòng nhập giờ checkin cần bổ sung.',
            'requested_checkin_time.date_format' => 'Giờ checkin không đúng định dạng.',
            'requested_checkout_time.date_format' => 'Giờ checkout không đúng định dạng.',
            'work_date.required' => 'Vui lòng chọn ngày cần bổ sung.',
            'work_date.date' => 'Ngày cần bổ sung không hợp lệ.',
            'user_id.required' => 'Vui lòng chọn nhân viên.',
            'user_id.exists' => 'Nhân viên không tồn tại.',
        ]);

        $selectedUser = User::findOrFail($data['user_id']);
        $this->authorizeSelectedUser($selectedUser);

        $workDate = Carbon::parse($data['work_date'])->startOfDay();
        $this->ensureSupplementDateAllowed($workDate);

        [$attendance, , $shift] = $this->dayContext(
            $selectedUser->id,
            $workDate
        );

        /*
         * Luồng bổ sung công chỉ còn một cặp checkin -> checkout.
         * Không còn xử lý ca gãy / segment.
         */
        if (
            empty($data['requested_checkin_time'])
            && !$attendance?->checkin_at
        ) {
            throw ValidationException::withMessages([
                'requested_checkin_time' =>
                    'Vui lòng nhập giờ checkin cần bổ sung.',
            ]);
        }

        if (
            empty($data['requested_checkin_time'])
            && empty($data['requested_checkout_time'])
        ) {
            throw ValidationException::withMessages([
                'requested_checkin_time' =>
                    'Không có khung giờ chấm công bị thiếu để bổ sung.',
            ]);
        }

        $checkin = !empty($data['requested_checkin_time'])
            ? $this->combineDateAndTime(
                $workDate,
                $data['requested_checkin_time']
            )
            : Carbon::parse($attendance->checkin_at);

        $checkout = !empty($data['requested_checkout_time'])
            ? $this->combineDateAndTime(
                $workDate,
                $data['requested_checkout_time']
            )
            : (
                $attendance?->checkout_at
                    ? Carbon::parse($attendance->checkout_at)
                    : null
            );

        if (
            $checkin
            && $checkout
            && $checkout->lessThanOrEqualTo($checkin)
        ) {
            $checkout->addDay();
        }

        $viewer = auth()->user();
        // A reviewer must not approve their own request. Their request remains
        // pending for another authorized reviewer.
        $autoApprove = in_array($viewer->role, ['manager', 'admin'], true)
            && $viewer->id !== $selectedUser->id;

        $result = DB::transaction(function () use (
            $selectedUser,
            $shift,
            $workDate,
            $checkin,
            $checkout,
            $data,
            $autoApprove
        ) {
            $pendingRequest = AttendanceSupplementRequest::query()
                ->where('user_id', $selectedUser->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest('updated_at')
                ->first();
            $hasApprovedRequest = !$pendingRequest && AttendanceSupplementRequest::query()
                ->where('user_id', $selectedUser->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->where('status', 'approved')
                ->exists();
            $payload = [
                'updated_by' => auth()->id(),
                'shift_id' => $shift?->id,
                'work_date' => $workDate->toDateString(),
                'requested_checkin_at' => $checkin,
                'requested_checkout_at' => $checkout,
                'reason' => $data['reason'],
                'status' => 'pending',
                'attendance_id' => null,
                'reviewed_by' => null,
                'review_note' => null,
                'reviewed_at' => null,
            ];
            if ($pendingRequest) {
                $pendingRequest->update($payload);
                $supplementRequest = $pendingRequest->fresh(['user', 'shift']);
            } else {
                $supplementRequest = AttendanceSupplementRequest::create($payload + [
                    'created_by' => auth()->id(),
                    'user_id' => $selectedUser->id,
                ]);
                $supplementRequest->load(['user', 'shift']);
            }

            if (!$autoApprove) {
                return [
                    'updated_pending' => (bool) $pendingRequest,
                    'has_approved_request' => $hasApprovedRequest,
                    'auto_approved' => false,
                ];
            }

            $attendance = $this->applyToAttendance(
                $supplementRequest,
                'Tự động duyệt khi quản lý/admin tạo đơn.'
            );

            $supplementRequest->update([
                'attendance_id' => $attendance->id,
                'reviewed_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'status' => 'approved',
                'review_note' => 'Tự động duyệt khi quản lý/admin tạo đơn.',
                'reviewed_at' => now(),
            ]);

            return [
                'updated_pending' => (bool) $pendingRequest,
                'has_approved_request' => $hasApprovedRequest,
                'auto_approved' => true,
            ];
        });

        $message = $result['updated_pending']
            ? 'Ngày này đã có đơn chờ duyệt, hệ thống đã cập nhật đơn cũ.'
            : ($result['has_approved_request']
                ? 'Ngày này đã có đơn được duyệt, bạn đang tạo yêu cầu bổ sung mới.'
                : 'Đã gửi đơn bổ sung công.');

        if ($result['auto_approved']) {
            $message .= ' Đơn đã được tự động duyệt theo quyền người tạo.';
        }

        return redirect()
            ->route('attendance-supplements.index')
            ->with('success', $message);
    }

    public function approve(Request $request, AttendanceSupplementRequest $attendanceSupplement)
    {
        $this->authorizeReview($attendanceSupplement);

        if ($attendanceSupplement->status !== 'pending') {
            return back()->withErrors(['request' => 'Đơn này đã được xử lý.']);
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendance = $this->applyToAttendance($attendanceSupplement, $data['review_note'] ?? null);

        $attendanceSupplement->update([
            'attendance_id' => $attendance->id,
            'reviewed_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'status' => 'approved',
            'review_note' => $data['review_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('attendance-supplements.index')
            ->with('success', 'Đã duyệt và cập nhật công.');
    }

    public function reject(Request $request, AttendanceSupplementRequest $attendanceSupplement)
    {
        $this->authorizeReview($attendanceSupplement);

        if ($attendanceSupplement->status !== 'pending') {
            return back()->withErrors(['request' => 'Đơn này đã được xử lý.']);
        }

        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $attendanceSupplement->update([
            'reviewed_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'status' => 'rejected',
            'review_note' => $data['review_note'],
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('attendance-supplements.index')
            ->with('success', 'Đã từ chối đơn bổ sung công.');
    }

    public function bulkReview(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', 'distinct', 'exists:attendance_supplement_requests,id'],
            'review_note' => [Rule::requiredIf($request->input('action') === 'reject'), 'nullable', 'string', 'min:5', 'max:2000'],
        ], [
            'request_ids.required' => 'Vui lòng chọn ít nhất một đơn bổ sung công.',
            'review_note.required' => 'Vui lòng nhập lý do từ chối chung.',
            'review_note.min' => 'Lý do từ chối phải có ít nhất 5 ký tự.',
        ]);

        $result = [
            'success_count' => 0,
            'fail_count' => 0,
            'success_items' => [],
            'failed_items' => [],
        ];

        foreach ($data['request_ids'] as $requestId) {
            $supplementRequest = AttendanceSupplementRequest::with(['user.branch', 'shift'])->find($requestId);

            if (!$supplementRequest) {
                $this->addBulkFailure($result, null, 'Không tìm thấy đơn bổ sung công.');
                continue;
            }

            if ($supplementRequest->status !== 'pending') {
                $this->addBulkFailure($result, $supplementRequest, 'Đơn này đã được xử lý.');
                continue;
            }

            if ($reason = $this->reviewAuthorizationError($supplementRequest)) {
                $this->addBulkFailure($result, $supplementRequest, $reason);
                continue;
            }

            try {
                DB::transaction(function () use ($supplementRequest, $data) {
                    $lockedRequest = AttendanceSupplementRequest::with(['user.branch', 'shift'])
                        ->lockForUpdate()
                        ->findOrFail($supplementRequest->id);

                    if ($lockedRequest->status !== 'pending') {
                        throw ValidationException::withMessages([
                            'request' => 'Đơn này đã được xử lý bởi thao tác khác.',
                        ]);
                    }

                    if ($data['action'] === 'approve') {
                        $attendance = $this->applyToAttendance($lockedRequest, $data['review_note'] ?? null);
                        $lockedRequest->update([
                            'attendance_id' => $attendance->id,
                            'reviewed_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                            'status' => 'approved',
                            'review_note' => $data['review_note'] ?? null,
                            'reviewed_at' => now(),
                        ]);

                        return;
                    }

                    $lockedRequest->update([
                        'reviewed_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                        'status' => 'rejected',
                        'review_note' => $data['review_note'],
                        'reviewed_at' => now(),
                    ]);
                });

                $result['success_count']++;
                $result['success_items'][] = $this->bulkItem($supplementRequest);
            } catch (ValidationException $exception) {
                $this->addBulkFailure($result, $supplementRequest, $exception->validator->errors()->first() ?: 'Không thể cập nhật đơn.');
            } catch (\Throwable $exception) {
                report($exception);
                $this->addBulkFailure($result, $supplementRequest, 'Lỗi hệ thống, vui lòng thử lại.');
            }
        }

        $message = $result['fail_count'] > 0
            ? "Đã xử lý {$result['success_count']} đơn, có {$result['fail_count']} trường hợp không thể xử lý."
            : "Đã xử lý thành công {$result['success_count']} đơn bổ sung công.";

        return redirect()
            ->route('attendance-supplements.index')
            ->with($result['fail_count'] > 0 ? 'warning' : 'success', $message)
            ->with('supplement_bulk_result', $result);
    }

    private function authorizeReview(AttendanceSupplementRequest $request): void
    {
        if (!$this->reviewAuthorizationError($request)) {
            return;
        }

        abort(403);
    }

    private function selectableUsersFor(User $viewer)
    {
        return User::with('branch')
            ->whereIn('role', ['staff', 'manager', 'cashier'])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->where(function ($query) {
                $query->whereNull('branch_id')
                    ->orWhereHas('branch', fn ($q) => $q->where('is_active', true));
            })
            ->when(in_array($viewer->role, ['staff', 'cashier'], true), fn ($query) => $query->where('id', $viewer->id))
            ->when($viewer->role === 'manager', function ($query) use ($viewer) {
                $query->where(function ($q) use ($viewer) {
                    $q->where('id', $viewer->id)
                        ->orWhere(function ($employeeQuery) use ($viewer) {
                            $employeeQuery
                                ->whereIn('role', ['staff', 'cashier'])
                                ->where('branch_id', $viewer->branch_id);
                        });
                });
            })
            ->orderBy('name');
    }

    private function authorizeSelectedUser(User $selectedUser): void
    {
        $viewer = auth()->user();

        if ($selectedUser->status === 'da_nghi') {
            abort(422, 'Không thể tạo đơn bổ sung công cho nhân sự đã nghỉ.');
        }

        if ($selectedUser->branch && !$selectedUser->branch->is_active) {
            abort(422, 'Không thể tạo đơn bổ sung công cho nhân sự thuộc cơ sở đã ngưng hoạt động.');
        }

        if ($viewer->role === 'admin') {
            return;
        }

        if (in_array($viewer->role, ['staff', 'cashier'], true) && $selectedUser->id === $viewer->id) {
            return;
        }

        if (
            $viewer->role === 'manager' &&
            (
                $selectedUser->id === $viewer->id ||
                (in_array($selectedUser->role, ['staff', 'cashier'], true) && $selectedUser->branch_id === $viewer->branch_id)
            )
        ) {
            return;
        }

        abort(403);
    }

    private function supplementLimitMode(): string
    {
        $mode = (string) Setting::getValue('attendance_supplement_limit_mode', 'last_3_days');

        return in_array($mode, ['free', 'last_3_days'], true) ? $mode : 'last_3_days';
    }

    private function ensureSupplementDateAllowed(Carbon $workDate): void
    {
        // Admin luôn được bổ sung công tự do, không bị giới hạn bởi cấu hình 3 ngày.
        if (auth()->user()?->role === 'admin') {
            return;
        }

        if ($this->supplementLimitMode() === 'free') {
            return;
        }

        // "3 ngày gần nhất" tính cả hôm nay.
        // Ví dụ hôm nay là ngày 07 => được chọn 05, 06, 07.
        $start = today()->subDays(2)->startOfDay();
        $end = today()->endOfDay();

        if (!$workDate->betweenIncluded($start, $end)) {
            throw ValidationException::withMessages([
                'work_date' => 'Chỉ được bổ sung công trong hôm nay và 2 ngày trước đó.',
            ]);
        }
    }

    private function applyToAttendance(
        AttendanceSupplementRequest $request,
        ?string $reviewNote
    ): Attendance {
        $request->loadMissing('shift');

        $workDate = Carbon::parse(
            $request->work_date
        )->startOfDay();

        [$attendance, $assignment] = $this->dayContext(
            $request->user_id,
            $workDate
        );

        if ($attendance?->is_locked) {
            throw ValidationException::withMessages([
                'attendance' =>
                    'Bản ghi công đã khóa. Không thể duyệt bổ sung công để cập nhật dữ liệu này.',
            ]);
        }

        /*
         * Assignment của đúng ngày là nguồn ca ưu tiên.
         * Nếu không có assignment mới fallback về ca lưu trong
         * đơn bổ sung hoặc attendance cũ.
         */
        $shift = $assignment?->shift
            ?? $request->shift
            ?? $attendance?->shift;

        $checkin = $request->requested_checkin_at
            ? Carbon::parse($request->requested_checkin_at)
            : (
                $attendance?->checkin_at
                    ? Carbon::parse($attendance->checkin_at)
                    : null
            );

        $checkout = $request->requested_checkout_at
            ? Carbon::parse($request->requested_checkout_at)
            : (
                $attendance?->checkout_at
                    ? Carbon::parse($attendance->checkout_at)
                    : null
            );

        if (!$checkin) {
            throw ValidationException::withMessages([
                'requested_checkin_time' =>
                    'Không thể duyệt vì chưa có giờ checkin.',
            ]);
        }

        if (
            $checkout
            && $checkout->lessThanOrEqualTo($checkin)
        ) {
            $checkout = $checkout->copy()->addDay();
        }

        $workedMinutes = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;
        $overtimeHours = 0.0;
        $workDay = 0.0;
        $penaltyWorkday = 0.0;

        if ($shift && $checkout) {
            /*
             * Dùng chung AttendanceCalculationService với luồng
             * chấm công thật và báo cáo công thủ công.
             */
            $temporaryAttendance = new Attendance();

            $temporaryAttendance->forceFill([
                'shift_id' => $shift->id,
                'work_date' => $workDate->toDateString(),
                'checkin_at' => $checkin,
                'checkout_at' => $checkout,
                'status' => 'completed',
            ]);

            $calculated = app(
                AttendanceCalculationService::class
            )->recalculateForShift(
                $temporaryAttendance,
                $shift
            );

            $workedMinutes = (int) (
                $calculated['worked_minutes'] ?? 0
            );
            $lateMinutes = (int) (
                $calculated['late_minutes'] ?? 0
            );
            $overtimeMinutes = (int) (
                $calculated['overtime_minutes'] ?? 0
            );
            $overtimeHours = (float) (
                $calculated['overtime_hours']
                ?? round($overtimeMinutes / 60, 2)
            );
            $workDay = (float) (
                $calculated['work_day'] ?? 0
            );
            $penaltyWorkday = (float) (
                $calculated['penalty_workday'] ?? 0
            );
        } elseif ($shift) {
            /*
             * Chỉ có checkin thì chưa tính công/OT,
             * nhưng vẫn xác định được phút đi trễ.
             */
            $lateMinutes = AttendanceLateCalculator::minutes(
                $shift,
                $workDate,
                $checkin
            );
        } else {
            /*
             * Không có ca: giữ thời gian thực tế để đối chiếu,
             * nhưng công, trễ và OT đều bằng 0.
             */
            $workedMinutes = $checkout
                ? (int) $checkin->diffInMinutes($checkout)
                : 0;
        }

        $data = [
            'user_id' => $request->user_id,
            'branch_id' => $attendance?->branch_id ?? $request->user?->branch_id,
            'shift_id' => $shift?->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $checkout
                ? 'completed'
                : 'checked_in',
            'note' => $this->supplementNote(
                $request,
                $reviewNote
            ),
        ];

        if (
            Schema::hasColumn(
                'attendances',
                'overtime_hours'
            )
        ) {
            $data['overtime_hours'] = $overtimeHours;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'work_day'
            )
        ) {
            $data['work_day'] = $workDay;
        }

        if (
            Schema::hasColumn(
                'attendances',
                'penalty_workday'
            )
        ) {
            $data['penalty_workday'] =
                $penaltyWorkday;
        }

        /*
         * Đồng bộ snapshot ca trên đơn với ca được gán hiện tại.
         */
        if (
            (int) ($request->shift_id ?? 0)
            !== (int) ($shift?->id ?? 0)
        ) {
            $request->forceFill([
                'shift_id' => $shift?->id,
            ])->save();
        }

        if ($attendance) {
            $attendance->update($data);

            return $attendance->fresh();
        }

        return Attendance::create($data);
    }

    private function dayContext(
        int $userId,
        Carbon $workDate
    ): array {
        $attendance = Attendance::with('shift')
            ->where('user_id', $userId)
            ->forWorkDate($workDate)
            ->latest()
            ->first();

        $assignments = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->where(function ($query) use ($workDate) {
                $query->whereNull('work_date')
                    ->orWhereDate(
                        'work_date',
                        $workDate->toDateString()
                    );
            })
            ->get();

        $assignment = $this->effectiveAssignmentForDay(
            $assignments,
            $workDate
        );

        $shift = $assignment?->shift
            ?? $attendance?->shift;

        return [
            $attendance,
            $assignment,
            $shift,
        ];
    }

    private function supplementNote(
        AttendanceSupplementRequest $request,
        ?string $reviewNote
    ): string {
        return trim(sprintf(
            "Bổ sung công đã duyệt.\nLý do: %s%s",
            $request->reason,
            $reviewNote
                ? "\nGhi chú duyệt: {$reviewNote}"
                : ''
        ));
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

    private function combineDateAndTime(Carbon $date, string $time): Carbon
    {
        $parsed = Carbon::createFromFormat('H:i', $time);

        return $date->copy()->setTime(
            (int) $parsed->format('H'),
            (int) $parsed->format('i')
        );
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

    private function reviewAuthorizationError(AttendanceSupplementRequest $request): ?string
    {
        $viewer = auth()->user();
        $owner = $request->user;

        if (!$owner) {
            return 'Không tìm thấy nhân viên của đơn này.';
        }

        if ($viewer->id === $owner->id) {
            return 'Bạn không thể tự duyệt đơn bổ sung công của chính mình.';
        }

        if ($viewer->role === 'admin') {
            return null;
        }

        if (
            $viewer->role === 'manager'
            && in_array($owner->role, ['staff', 'cashier'], true)
            && $owner->branch_id === $viewer->branch_id
        ) {
            return null;
        }

        return 'Bạn không có quyền duyệt đơn của nhân viên này.';
    }

    private function addBulkFailure(array &$result, ?AttendanceSupplementRequest $request, string $reason): void
    {
        $result['fail_count']++;
        $result['failed_items'][] = $this->bulkItem($request) + ['reason' => $reason];
    }

    private function bulkItem(?AttendanceSupplementRequest $request): array
    {
        return [
            'user_name' => $request?->user?->name ?? 'Không tìm thấy nhân viên',
            'work_date' => $request?->work_date?->format('d/m/Y') ?? '-',
            'request_id' => $request?->id,
        ];
    }
}