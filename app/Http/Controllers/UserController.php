<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Position;
use App\Services\EmployeeCodeService;
use App\Services\EmploymentStatusService;
use App\Models\User;
use App\Models\WorkHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private EmploymentStatusService $employmentStatus,
        private EmployeeCodeService $employeeCodes
    )
    {
    }

    public function index(Request $request)
    {
        $currentUser = auth()->user();

        if (!in_array($currentUser->role, ['admin', 'manager'], true)) {
            abort(403);
        }

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'branch_id' => (string) $request->input('branch_id', ''),
            'role' => (string) $request->input('role', ''),
            'status' => (string) $request->input('status', ''),
            'employment_tab' => in_array($request->input('employment_tab'), ['active', 'resigned'], true)
                ? $request->input('employment_tab')
                : ($request->input('status') === 'da_nghi' ? 'resigned' : 'active'),
            'face_verification_mode' => (string) $request->input('face_verification_mode', ''),
            'start_from' => (string) $request->input('start_from', ''),
            'start_to' => (string) $request->input('start_to', ''),
        ];

        $users = User::with(['branch', 'position', 'hiredBy', 'officialBy', 'resignedBy', 'statusChangedBy'])
            ->when($currentUser->role === 'manager', function ($query) use ($currentUser) {
                $query->where('branch_id', $currentUser->branch_id)
                    ->whereIn('role', ['staff', 'cashier']);
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['phone'] !== '', fn ($query) => $query->where('phone', 'like', "%{$filters['phone']}%"))
            ->when($filters['email'] !== '', fn ($query) => $query->where('email', 'like', "%{$filters['email']}%"))
            ->when($filters['branch_id'] !== '', fn ($query) => $query->where('branch_id', $filters['branch_id']))
            ->when(in_array($filters['role'], ['admin', 'manager', 'staff', 'cashier'], true), fn ($query) => $query->where('role', $filters['role']))
            ->when(in_array($filters['status'], ['thu_viec', 'chinh_thuc', 'da_nghi'], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(in_array($filters['face_verification_mode'], ['normal', 'priority'], true), fn ($query) => $query->where('face_verification_mode', $filters['face_verification_mode']))
            ->when($filters['start_from'] !== '', fn ($query) => $query->whereDate('start_work_date', '>=', $filters['start_from']))
            ->when($filters['start_to'] !== '', fn ($query) => $query->whereDate('start_work_date', '<=', $filters['start_to']))
            ->when($filters['employment_tab'] === 'resigned', fn ($query) => $query->where('status', 'da_nghi'))
            ->when($filters['employment_tab'] === 'active', function ($query) {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('status')->orWhere('status', '!=', 'da_nghi');
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::query()
            ->when($currentUser->role === 'manager', fn ($query) => $query->where('id', $currentUser->branch_id))
            ->orderBy('name')
            ->get();

        return view('users.index', compact('branches', 'filters', 'users'));
    }

    public function create()
    {
        $currentUser = auth()->user();
        abort_unless(in_array($currentUser->role, ['admin', 'manager'], true), 403);

        $branches = Branch::active()->orderBy('name')->get();
        $autoPromoteEnabled = $this->employmentStatus->autoPromoteEnabled();
        $positions = Position::active()
            ->when($currentUser->role === 'manager', fn ($q) => $q->whereIn('system_role', ['staff', 'cashier']))
            ->ordered()
            ->get();

        return view('users.create', compact('autoPromoteEnabled', 'branches', 'positions'));
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();

        abort_unless(in_array($currentUser->role, ['admin', 'manager'], true), 403);

        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255', 'unique:users,phone'],
            'citizen_id' => ['nullable', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:4'],
            'start_work_date' => ['required', 'date_format:Y-m-d'],
            'password' => ['required', 'min:6'],
            'status' => ['nullable', 'in:chinh_thuc,thu_viec,da_nghi'],
            'face_verification_mode' => ['required', 'in:normal,priority'],
            'position_id' => ['nullable', 'exists:positions,id'],
        ];

        if ($currentUser->role === 'admin') {
            $rules['role'] = ['nullable', 'in:manager,staff,cashier'];
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        } elseif ($currentUser->role === 'manager') {
            $rules['role'] = ['nullable', 'in:staff,cashier'];
        }

        $data = $request->validate($rules);

        $autoPromoteEnabled = $this->employmentStatus->autoPromoteEnabled();
        $status = $autoPromoteEnabled ? 'thu_viec' : ($data['status'] ?? 'thu_viec');
        $startWorkDate = Carbon::createFromFormat('Y-m-d', $data['start_work_date'])->toDateString();
        $milestones = $this->initialMilestones($status, $startWorkDate, $currentUser->id);
        $phone = $data['phone'] ?? null;
        $branchId = $currentUser->role === 'admin' ? (int) $data['branch_id'] : (int) $currentUser->branch_id;

        $chosenPosition = !empty($data['position_id']) ? Position::find($data['position_id']) : null;
        $finalRole = $chosenPosition?->system_role ?? ($data['role'] ?? 'staff');

        DB::transaction(function () use ($data, $phone, $startWorkDate, $status, $milestones, $currentUser, $branchId, $finalRole) {
            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $phone,
                'citizen_id' => $data['citizen_id'] ?? null,
                'employee_code' => $this->employeeCodes->resolveForBranch($branchId, $data['employee_code'] ?? null),
                'start_work_date' => $startWorkDate,
                'password' => Hash::make($data['password']),
                'position_id' => $data['position_id'] ?? null,
                'role' => $finalRole,
                'status' => $status,
                'employment_status' => $this->employmentStatusFor($status),
                'face_verification_mode' => $data['face_verification_mode'],
                ...$milestones,
                'status_changed_by' => $currentUser->id,
                'status_changed_at' => now(),
                'branch_id' => $branchId,
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Đã tạo nhân sự.');
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user();
        $this->authorizeUserManagement($user);

        $user->loadMissing(['hiredBy', 'officialBy', 'resignedBy', 'statusChangedBy', 'position']);
        $branches = Branch::active()
            ->when($user->branch_id, fn ($q) => $q->orWhere('id', $user->branch_id))
            ->orderBy('name')
            ->get();
        $autoPromoteEnabled = $this->employmentStatus->autoPromoteEnabled();
        $positions = Position::active()
            ->when($currentUser->role === 'manager', fn ($q) => $q->whereIn('system_role', ['staff', 'cashier']))
            ->ordered()
            ->get();

        return view('users.edit', compact('autoPromoteEnabled', 'branches', 'positions', 'user'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $this->authorizeUserManagement($user);

        $rules = [
            'name' => ['required'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($user->id)],
            'citizen_id' => ['nullable', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:4'],
            'start_work_date' => ['required', 'date_format:Y-m-d'],
            'official_at' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:chinh_thuc,thu_viec,da_nghi'],
            'face_verification_mode' => ['required', 'in:normal,priority'],
            'rating_qr_enabled' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'branch_transfer_effective_date' => ['nullable', 'date_format:Y-m-d'],
            'branch_transfer_note' => ['nullable', 'string', 'max:500'],
            'position_id' => ['nullable', 'exists:positions,id'],
        ];

        if ($currentUser->role === 'admin') {
            $rules['role'] = ['nullable', 'in:manager,staff,cashier'];
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['is_active'] = ['nullable', 'boolean'];
            $rules['password'] = ['nullable', 'string', 'min:6'];
        } elseif ($currentUser->role === 'manager') {
            $rules['role'] = ['nullable', 'in:staff,cashier'];
        }

        $data = $request->validate($rules);
        $autoPromoteEnabled = $this->employmentStatus->autoPromoteEnabled();

        $requestedBranchId = $currentUser->role === 'admin'
            ? (int) $data['branch_id']
            : (int) $user->branch_id;
        $branchChanged = (int) $user->branch_id !== $requestedBranchId;

        if ($branchChanged && empty($data['branch_transfer_effective_date'])) {
            throw ValidationException::withMessages([
                'branch_transfer_effective_date' => 'Vui lòng nhập ngày hiệu lực chuyển công tác.',
            ]);
        }

        if (
            $currentUser->role === 'manager' &&
            $autoPromoteEnabled &&
            $request->has('status') &&
            (string) $request->input('status') !== (string) $user->status
        ) {
            return back()
                ->withErrors(['status' => 'Trạng thái đang được hệ thống tự động quản lý.'])
                ->withInput();
        }

        $status = $this->editableStatusFor($currentUser, $user, $data['status'] ?? null, $autoPromoteEnabled);
        $startWorkDate = Carbon::createFromFormat('Y-m-d', $data['start_work_date'])->toDateString();
        $phone = $data['phone'] ?? null;

        $chosenPosition = array_key_exists('position_id', $data)
            ? (!empty($data['position_id']) ? Position::find($data['position_id']) : null)
            : $user->position;
        $finalRole = $chosenPosition?->system_role ?? ($data['role'] ?? $user->role);

        $updateData = [
            'name' => $data['name'],
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => $phone,
            'citizen_id' => $currentUser->role === 'admin' ? ($data['citizen_id'] ?? null) : ($data['citizen_id'] ?? $user->citizen_id),
            'employee_code' => $this->employeeCodes->resolveForBranch(
                $requestedBranchId,
                $data['employee_code'] ?? $user->employee_code,
                $user->id
            ),
            'start_work_date' => $startWorkDate,
            'status' => $status,
            'employment_status' => $this->employmentStatusFor($status),
            'face_verification_mode' => $data['face_verification_mode'],
            'position_id' => array_key_exists('position_id', $data) ? ($data['position_id'] ?: null) : $user->position_id,
            'role' => in_array($currentUser->role, ['admin', 'manager'], true) ? $finalRole : $user->role,
            'branch_id' => $currentUser->role === 'admin' ? $data['branch_id'] : $user->branch_id,
        ];

        if ($currentUser->role === 'admin') {
            $updateData['rating_qr_enabled'] = $request->boolean('rating_qr_enabled');
            $updateData['is_active'] = $request->boolean('is_active');

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }
        }

        if ($request->hasFile('avatar')) {
            $updateData['face_image_path'] = $request->file('avatar')->store('faces', 'public');
        }

        if ($user->status !== $updateData['status']) {
            $updateData['status_changed_by'] = $currentUser->id;
            $updateData['status_changed_at'] = now();
            $updateData = [
                ...$updateData,
                ...$this->milestonesForStatusChange($user, $updateData['status'], $currentUser->id),
            ];
        }

        $manualOfficialAt = !empty($data['official_at'])
            ? Carbon::createFromFormat('Y-m-d', $data['official_at'])->toDateString()
            : null;

        if ($updateData['status'] === 'chinh_thuc') {
            $updateData['official_at'] = $manualOfficialAt
                ?: ($updateData['official_at'] ?? $user->official_at?->toDateString() ?? Carbon::createFromFormat('Y-m-d', $startWorkDate)->addDays(3)->toDateString());
            $updateData['official_by'] = $updateData['official_by'] ?? $user->official_by ?? $currentUser->id;
            $updateData['resigned_at'] = null;
            $updateData['resigned_by'] = null;
        }

        if ($updateData['status'] === 'thu_viec') {
            $updateData['official_at'] = null;
            $updateData['official_by'] = null;
            $updateData['resigned_at'] = null;
            $updateData['resigned_by'] = null;
        }

        $effectiveDate = $branchChanged
            ? Carbon::createFromFormat('Y-m-d', $data['branch_transfer_effective_date'])->toDateString()
            : null;
        $workHistoryData = $this->workHistoryDataForChange(
            $user,
            $updateData,
            $currentUser->id,
            $effectiveDate,
            $data['branch_transfer_note'] ?? null
        );

        DB::transaction(function () use ($user, $updateData, $workHistoryData) {
            $user->update($updateData);

            if ($workHistoryData !== null) {
                WorkHistory::create($workHistoryData);
            }
        });

        return redirect()->route('users.index')->with('success', 'Đã cập nhật nhân sự.');
    }

    public function me()
    {
        return view('users.me', [
            'user' => auth()->user()->loadMissing(['branch', 'hiredBy', 'officialBy', 'resignedBy', 'statusChangedBy']),
        ]);
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($user->id)],
            'citizen_id' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ]);

        $updateData = [
            'name' => $data['name'],
        ];

        foreach (['email', 'phone', 'citizen_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = ($data[$field] ?? null) ?: null;
            }
        }

        if ($request->hasFile('avatar')) {
            $updateData['face_image_path'] = $request->file('avatar')->store('faces', 'public');
        }

        $user->update($updateData);

        return redirect()->route('users.me')->with('success', 'Da cap nhat thong tin ca nhan.');
    }

    private function authorizeUserManagement(User $user): void
    {
        $currentUser = auth()->user();

        if ($currentUser->role === 'admin') {
            return;
        }

        if (
            $currentUser->role === 'manager' &&
            in_array($user->role, ['staff', 'cashier'], true) &&
            $user->branch_id === $currentUser->branch_id
        ) {
            return;
        }

        abort(403);
    }

    private function initialMilestones(string $status, string $startWorkDate, int $actorId): array
    {
        $hiredAt = Carbon::createFromFormat('Y-m-d', $startWorkDate)->toDateString();

        $milestones = [
            'hired_at' => $hiredAt,
            'hired_by' => $actorId,
            'official_at' => null,
            'official_by' => null,
            'resigned_at' => null,
            'resigned_by' => null,
        ];

        if ($status === 'chinh_thuc') {
            $milestones['official_at'] = Carbon::createFromFormat('Y-m-d', $startWorkDate)
                ->addDays(3)
                ->toDateString();
            $milestones['official_by'] = $actorId;
        }

        if ($status === 'da_nghi') {
            $milestones['resigned_at'] = today()->toDateString();
            $milestones['resigned_by'] = $actorId;
        }

        return $milestones;
    }

    private function employmentStatusFor(string $status): string
    {
        return match ($status) {
            'chinh_thuc' => 'official',
            'da_nghi' => 'resigned',
            default => 'probation',
        };
    }

    private function milestonesForStatusChange(User $user, string $status, int $actorId): array
    {
        $today = today()->toDateString();

        return match ($status) {
            'thu_viec' => [
                'hired_at' => $user->hired_at?->toDateString() ?? $today,
                'hired_by' => $user->hired_by ?: $actorId,
                'official_at' => null,
                'official_by' => null,
                'resigned_at' => null,
                'resigned_by' => null,
            ],
            'chinh_thuc' => [
                'official_at' => $today,
                'official_by' => $actorId,
                'resigned_at' => null,
                'resigned_by' => null,
            ],
            'da_nghi' => [
                'resigned_at' => $today,
                'resigned_by' => $actorId,
            ],
            default => [],
        };
    }

    private function editableStatusFor(User $currentUser, User $user, ?string $requestedStatus, bool $autoPromoteEnabled): string
    {
        if ($currentUser->role === 'admin') {
            return $requestedStatus ?: $user->status;
        }

        if ($currentUser->role === 'manager' && ! $autoPromoteEnabled) {
            return $requestedStatus ?: $user->status;
        }

        return $user->status;
    }

    private function workHistoryDataForChange(
        User $user,
        array $updateData,
        int $actorId,
        ?string $branchTransferEffectiveDate = null,
        ?string $branchTransferNote = null
    ): ?array
    {
        $oldBranchId = $user->branch_id;
        $newBranchId = $updateData['branch_id'] ?? null;
        $oldRole = $user->role;
        $newRole = $updateData['role'] ?? null;
        $oldStatus = $user->status;
        $newStatus = $updateData['status'] ?? null;

        $branchChanged = (string) ($oldBranchId ?? '') !== (string) ($newBranchId ?? '');
        $roleChanged = (string) ($oldRole ?? '') !== (string) ($newRole ?? '');
        $statusChanged = (string) ($oldStatus ?? '') !== (string) ($newStatus ?? '');

        if (! $branchChanged && ! $roleChanged && ! $statusChanged) {
            return null;
        }

        return [
            'user_id' => $user->id,
            'old_branch_id' => $oldBranchId,
            'new_branch_id' => $newBranchId,
            'old_department_id' => null,
            'new_department_id' => null,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'effective_date' => $branchChanged
                ? $branchTransferEffectiveDate
                : today()->toDateString(),
            'changed_by' => $actorId,
            'note' => $branchChanged
                ? ($branchTransferNote ?: 'Chuyen cong tac sang chi nhanh moi.')
                : ($statusChanged ? 'Cap nhat trang thai nhan su.' : 'Cap nhat vai tro nhan su.'),
        ];
    }
}