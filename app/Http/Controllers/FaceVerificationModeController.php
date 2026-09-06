<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\FaceVerificationModeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FaceVerificationModeController extends Controller
{
    private const MODES = ['normal', 'priority'];
    private const FILTER_KEYS = ['search', 'branch_id', 'role', 'status', 'face_verification_mode'];

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $filters = $this->filters($request);
        $baseQuery = $this->filteredUsers($viewer, $filters);

        $statsRows = (clone $baseQuery)
            ->selectRaw("COALESCE(face_verification_mode, 'normal') as mode, COUNT(*) as total")
            ->groupBy('mode')
            ->pluck('total', 'mode');

        $users = (clone $baseQuery)
            ->with('branch')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $branches = Branch::active()
            ->when($viewer->role === 'manager', fn (Builder $query) => $query->where('id', $viewer->branch_id))
            ->orderBy('name')
            ->get();

        return view('admin.face-verification-modes.index', [
            'users' => $users,
            'branches' => $branches,
            'filters' => $filters,
            'stats' => [
                'total' => (int) $statsRows->sum(),
                'normal' => (int) ($statsRows['normal'] ?? 0),
                'priority' => (int) ($statsRows['priority'] ?? 0),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(self::MODES)],
            'update_type' => ['required', Rule::in(['single', 'selected', 'filter'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'filters' => ['nullable', 'array'],
        ]);

        $viewer = $request->user();
        $mode = $data['mode'];
        $filters = $this->filtersFromArray($data['filters'] ?? []);
        $query = $this->filteredUsers($viewer, $filters);

        if ($data['update_type'] === 'single') {
            $ids = array_filter([(int) ($data['user_id'] ?? 0)]);
            $query->whereIn('id', $ids);
        }

        if ($data['update_type'] === 'selected') {
            $ids = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

            if ($ids->isEmpty()) {
                return back()->withErrors(['user_ids' => 'Vui lòng chọn ít nhất một nhân sự.']);
            }

            $query->whereIn('id', $ids);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return back()->withErrors(['users' => 'Không tìm thấy nhân sự phù hợp trong phạm vi quyền.']);
        }

        DB::transaction(function () use ($users, $mode, $viewer, $filters, $data) {
            foreach ($users as $user) {
                $oldMode = $user->face_verification_mode ?: 'normal';

                if ($oldMode === $mode) {
                    continue;
                }

                $user->forceFill([
                    'face_verification_mode' => $mode,
                ])->save();

                FaceVerificationModeLog::create([
                    'actor_id' => $viewer->id,
                    'target_user_id' => $user->id,
                    'bulk_filter_json' => $data['update_type'] === 'filter' ? $filters : null,
                    'old_mode' => $oldMode,
                    'new_mode' => $mode,
                    'action' => 'update_face_verification_mode',
                ]);
            }
        });

        return redirect()
            ->route('face-verification-modes.index', $filters)
            ->with('success', 'Đã cập nhật chế độ xác thực khuôn mặt cho ' . $users->count() . ' nhân sự.');
    }

    private function filteredUsers(User $viewer, array $filters): Builder
    {
        return User::query()
            ->when($viewer->role === 'manager', fn (Builder $query) => $query->where('branch_id', $viewer->branch_id))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('zalo_phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->when($filters['role'] !== '', fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(
                $filters['status'] !== '',
                fn (Builder $query) => $query->where('status', $filters['status']),
                fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'da_nghi');
                })
            )
            ->when($filters['face_verification_mode'] !== '', function (Builder $query) use ($filters) {
                $mode = $filters['face_verification_mode'];
                $query->whereRaw("COALESCE(face_verification_mode, 'normal') = ?", [$mode]);
            });
    }

    private function filters(Request $request): array
    {
        return $this->filtersFromArray($request->only(self::FILTER_KEYS));
    }

    private function filtersFromArray(array $source): array
    {
        return [
            'search' => trim((string) ($source['search'] ?? '')),
            'branch_id' => (string) ($source['branch_id'] ?? ''),
            'role' => in_array(($source['role'] ?? ''), ['admin', 'manager', 'staff', 'cashier'], true) ? $source['role'] : '',
            'status' => in_array(($source['status'] ?? ''), ['thu_viec', 'chinh_thuc', 'da_nghi'], true) ? $source['status'] : '',
            'face_verification_mode' => in_array(($source['face_verification_mode'] ?? ''), self::MODES, true)
                ? $source['face_verification_mode']
                : '',
        ];
    }
}
