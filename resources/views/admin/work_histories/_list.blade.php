@php
    $roleLabels = [
        'admin' => 'Quản trị',
        'manager' => 'Quản lý',
        'staff' => 'Nhân viên',
        'cashier' => 'Thu ngân',
    ];

    $statusLabels = [
        'thu_viec' => 'Thử việc',
        'chinh_thuc' => 'Chính thức',
        'da_nghi' => 'Đã nghỉ',
    ];

    $changeBadgeClasses = [
        'branch' => 'bg-blue-100 text-blue-700',
        'role' => 'bg-violet-100 text-violet-700',
        'department' => 'bg-amber-100 text-amber-700',
        'status' => 'bg-emerald-100 text-emerald-700',
    ];

    $changeLabels = [
        'branch' => 'Đổi chi nhánh',
        'role' => 'Đổi role',
        'department' => 'Đổi bộ phận',
        'status' => 'Đổi trạng thái',
    ];

    $changeTypesFor = function ($history) {
        $types = [];

        if ((string) ($history->old_branch_id ?? '') !== (string) ($history->new_branch_id ?? '')) {
            $types[] = 'branch';
        }

        if ((string) ($history->old_role ?? '') !== (string) ($history->new_role ?? '')) {
            $types[] = 'role';
        }

        if ((string) ($history->old_status ?? '') !== (string) ($history->new_status ?? '')) {
            $types[] = 'status';
        }

        if (
            ($history->old_department_id !== null || $history->new_department_id !== null) &&
            (string) ($history->old_department_id ?? '') !== (string) ($history->new_department_id ?? '')
        ) {
            $types[] = 'department';
        }

        return $types ?: ['role'];
    };
@endphp

<div class="space-y-3">
    @forelse ($histories as $history)
        @php($types = $changeTypesFor($history))

        <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    @if ($showEmployee ?? true)
                        <div class="text-base font-black text-slate-950">{{ $history->user?->name ?? 'Nhân sự đã xóa' }}</div>
                        <div class="mt-0.5 text-sm text-slate-500">{{ $history->user?->email ?? '-' }}</div>
                    @else
                        <div class="text-base font-black text-slate-950">{{ $titleUser?->name ?? 'Nhân sự' }}</div>
                        <div class="mt-0.5 text-sm text-slate-500">{{ $titleUser?->email ?? '-' }}</div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type)
                        <span class="rounded-full px-3 py-1 text-xs font-black {{ $changeBadgeClasses[$type] ?? 'bg-slate-100 text-slate-700' }}">
                            {{ $changeLabels[$type] ?? $type }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Chi nhánh cũ</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->oldBranch?->name ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Chi nhánh mới</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->newBranch?->name ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Role cũ</div>
                    <div class="mt-1 font-black text-slate-900">{{ $roleLabels[$history->old_role] ?? ($history->old_role ?: '-') }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Role mới</div>
                    <div class="mt-1 font-black text-slate-900">{{ $roleLabels[$history->new_role] ?? ($history->new_role ?: '-') }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Trạng thái cũ</div>
                    <div class="mt-1 font-black text-slate-900">{{ $statusLabels[$history->old_status] ?? ($history->old_status ?: '-') }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Trạng thái mới</div>
                    <div class="mt-1 font-black text-slate-900">{{ $statusLabels[$history->new_status] ?? ($history->new_status ?: '-') }}</div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Bộ phận cũ</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->old_department_id ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Bộ phận mới</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->new_department_id ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Ngày hiệu lực</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->effective_date?->format('d/m/Y') ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Thời gian tạo</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Người thực hiện</div>
                    <div class="mt-1 font-black text-slate-900">{{ $history->changedBy?->name ?? '-' }}</div>
                </div>

                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="font-bold text-slate-500">Ghi chú</div>
                    <div class="mt-1 font-medium text-slate-800">{{ $history->note ?: '-' }}</div>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center font-bold text-slate-500 shadow-sm ring-1 ring-slate-200">
            Chưa có lịch sử công tác phù hợp.
        </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $histories->links() }}
</div>
