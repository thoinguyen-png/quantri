<x-app-layout>
    @php
        $actionClasses = [
            'change_shift' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'recalculate_attendance' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            'update_attendance' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'delete_attendance' => 'bg-red-50 text-red-700 ring-red-200',
            'lock_attendance' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'unlock_attendance' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        ];

        $formatJson = function ($values) {
            return $values ? json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-';
        };
    @endphp

    <div class="min-h-screen bg-white px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Audit log</p>
                    <h1 class="text-2xl font-bold text-slate-950">Lịch sử chỉnh sửa công</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Theo dõi các thao tác đổi ca, tính lại công, sửa/xóa và khóa/mở khóa công.
                    </p>
                </div>

                <a href="{{ route('attendance-reports.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Quay lại báo cáo
                </a>
            </div>

            <form method="GET" action="{{ route('attendance-adjustment-logs.index') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-slate-500">Nhân viên</span>
                    <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Tất cả</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>
                                {{ $user->name }}{{ $user->branch ? ' - ' . $user->branch->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-slate-500">Loại thao tác</span>
                    <select name="action" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Tất cả</option>
                        @foreach ($actions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-slate-500">Từ ngày công</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-slate-500">Đến ngày công</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Lọc
                    </button>
                    <a href="{{ route('attendance-adjustment-logs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-white">
                        Xóa
                    </a>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden grid-cols-[1.4fr_1fr_1fr_1fr] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold uppercase text-slate-500 lg:grid">
                    <span>Nhân viên / thao tác</span>
                    <span>Ngày công</span>
                    <span>Người thực hiện</span>
                    <span>Thời gian</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        @php
                            $badgeClass = $actionClasses[$log->action] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                        @endphp

                        <article class="grid gap-4 px-4 py-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="text-sm text-slate-950">{{ $log->user?->name ?? 'Không rõ nhân viên' }}</strong>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badgeClass }}">
                                        {{ $actions[$log->action] ?? $log->action }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    {{ $log->user?->branch?->name ?? 'Chưa có chi nhánh' }}
                                    @if ($log->attendance_id)
                                        <span class="mx-1">·</span> Attendance #{{ $log->attendance_id }}
                                    @endif
                                </p>
                                @if ($log->note)
                                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $log->note }}</p>
                                @endif
                            </div>

                            <div>
                                <span class="block text-xs font-semibold uppercase text-slate-400 lg:hidden">Ngày công</span>
                                <span class="text-sm text-slate-700">{{ $log->work_date?->format('d/m/Y') ?? '-' }}</span>
                            </div>

                            <div>
                                <span class="block text-xs font-semibold uppercase text-slate-400 lg:hidden">Người thực hiện</span>
                                <span class="text-sm text-slate-700">{{ $log->changedBy?->name ?? '-' }}</span>
                            </div>

                            <div>
                                <span class="block text-xs font-semibold uppercase text-slate-400 lg:hidden">Thời gian</span>
                                <span class="text-sm text-slate-700">{{ $log->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                            </div>

                            <details class="lg:col-span-4">
                                <summary class="cursor-pointer text-sm font-semibold text-blue-600">Xem dữ liệu trước/sau</summary>
                                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                    <div class="rounded-lg bg-slate-950 p-3">
                                        <p class="mb-2 text-xs font-semibold uppercase text-slate-400">Trước</p>
                                        <pre class="max-h-72 overflow-auto whitespace-pre-wrap text-xs text-slate-100">{{ $formatJson($log->old_values) }}</pre>
                                    </div>
                                    <div class="rounded-lg bg-slate-950 p-3">
                                        <p class="mb-2 text-xs font-semibold uppercase text-slate-400">Sau</p>
                                        <pre class="max-h-72 overflow-auto whitespace-pre-wrap text-xs text-slate-100">{{ $formatJson($log->new_values) }}</pre>
                                    </div>
                                </div>
                            </details>
                        </article>
                    @empty
                        <div class="px-4 py-10 text-center text-sm text-slate-500">
                            Chưa có lịch sử chỉnh sửa công.
                        </div>
                    @endforelse
                </div>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
