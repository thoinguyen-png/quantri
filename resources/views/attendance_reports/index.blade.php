<x-app-layout>
    @php
        $statusLabels = [
            'checked_in' => 'Đã checkin',
            'completed' => 'Đã hoàn tất',
            'missing_checkout' => 'Thiếu checkout',
        ];

        $attendanceBadge = function ($attendance) {
            $displayStatus = $attendance->display_status ?? null;

            if ($displayStatus) {
                return match ($displayStatus['key'] ?? 'unknown') {
                    'full_day' => ['attendance-record-status--completed', 'Đủ công'],
                    'late' => ['attendance-record-status--warning', 'Đi trễ'],
                    'early_leave' => ['attendance-record-status--warning', 'Về sớm'],
                    'missing_checkout' => ['attendance-record-status--danger', 'Thiếu checkout'],
                    'insufficient_work' => ['attendance-record-status--danger', 'Thiếu công'],
                    default => ['attendance-record-status--warning', $displayStatus['label'] ?? 'Cần kiểm tra'],
                };
            }

            if (!$attendance->checkin_at) {
                return ['attendance-record-status--danger', 'Thiếu checkin'];
            }

            if (!$attendance->checkout_at || $attendance->status === 'missing_checkout') {
                return ['attendance-record-status--danger', 'Thiếu checkout'];
            }

            $workDay = $attendance->work_day;
            $workedMinutes = (int) ($attendance->worked_minutes ?? 0);
            $requiredMinutes = null;

            if ($attendance->shift) {
                $shiftStart = \Carbon\Carbon::parse($attendance->shift->start_at);
                $shiftEnd = \Carbon\Carbon::parse($attendance->shift->end_at);

                if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $requiredMinutes = max($shiftStart->diffInMinutes($shiftEnd), 1);
            }

            $isFullWork = $workDay !== null
                ? (float) $workDay >= 1
                : ($requiredMinutes ? $workedMinutes >= $requiredMinutes : $attendance->status === 'completed' && $workedMinutes > 0);

            if ($isFullWork) {
                return ['attendance-record-status--completed', 'Đủ công'];
            }

            $ratio = $requiredMinutes ? $workedMinutes / $requiredMinutes : 0;

            return [
                $ratio >= 0.5 ? 'attendance-record-status--warning' : 'attendance-record-status--danger',
                'Thiếu công',
            ];
        };

        $summary = [
            'total' => $attendances->total(),
            'completed' => $attendances->getCollection()->filter(fn ($attendance) => ($attendance->display_status['key'] ?? null) === 'full_day')->count(),
            'overtimeHours' => round($attendances->getCollection()->sum(fn ($attendance) => (float) ($attendance->overtime_hours ?? ($attendance->overtime_minutes / 60))), 2),
        ];
    @endphp

    <div class="attendance-record-page">
        <div class="attendance-record-shell">
            <section class="attendance-record-hero attendance-record-toolbar">
                <div>
                    <div class="attendance-record-eyebrow">Attendance report</div>
                    <h1 class="attendance-record-title">Báo cáo chấm công</h1>
                    <p class="attendance-record-subtitle">{{ $summary['total'] }} bản ghi chấm công đang được hiển thị.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('attendance-adjustment-logs.index') }}" class="attendance-record-action">
                        Lịch sử chỉnh sửa
                    </a>
                    <a href="{{ route('attendance-supplements.create') }}" class="attendance-record-action">
                        Bổ sung chấm công
                    </a>
                </div>
            </section>

            <section class="attendance-record-summary">
                <div class="attendance-record-summary-card">
                    <span>Tổng bản ghi</span>
                    <strong>{{ $summary['total'] }}</strong>
                </div>
                <div class="attendance-record-summary-card">
                    <span>Hoan tat</span>
                    <strong>{{ $summary['completed'] }}</strong>
                </div>
                <div class="attendance-record-summary-card">
                    <span>Tăng ca</span>
                    <strong>{{ $summary['overtimeHours'] }}h</strong>
                </div>
            </section>

            @if (session('success'))
                <div class="attendance-alert attendance-alert--success mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <details class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100" open>
                <summary class="cursor-pointer text-sm font-black text-slate-800">Bộ lọc công</summary>
                <form method="GET" action="{{ route('attendance-reports.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="text-xs font-bold text-slate-500">Từ ngày</label><input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 w-full rounded-xl border-slate-300"></div>
                    <div><label class="text-xs font-bold text-slate-500">Đến ngày</label><input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 w-full rounded-xl border-slate-300"></div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Chi nhánh</label>
                        <select name="branch_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả chi nhánh</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Nhân sự</label>
                        <select name="user_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả nhân sự</option>@foreach ($users as $reportUser)<option value="{{ $reportUser->id }}" @selected((string) $filters['user_id'] === (string) $reportUser->id)>{{ $reportUser->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Vai trò</label>
                        <select name="role" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả vai trò</option>@foreach (['staff' => 'Nhân viên', 'cashier' => 'Thu ngân', 'manager' => 'Quản lý', 'admin' => 'Quản trị'] as $role => $label)<option value="{{ $role }}" @selected($filters['role'] === $role)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Ca làm</label>
                        <select name="shift_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả ca</option>@foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected((string) $filters['shift_id'] === (string) $shift->id)>{{ $shift->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Trạng thái công</label>
                        <select name="status" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả trạng thái</option>@foreach ($statusOptions as $statusKey => $label)<option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Khóa công</label>
                        <select name="locked" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Tất cả</option><option value="locked" @selected($filters['locked'] === 'locked')>Đã khóa</option><option value="unlocked" @selected($filters['locked'] === 'unlocked')>Chưa khóa</option></select>
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white">Lọc công</button><a href="{{ route('attendance-reports.index') }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700">Xóa lọc</a></div>
                </form>
            </details>

            <section class="attendance-record-list attendance-record-list--dense">
                @forelse ($attendances as $attendance)
                    @php
                        [$statusClass, $statusText] = $attendanceBadge($attendance);
                    @endphp

                    <article class="attendance-record-card attendance-record-row">
                        <div class="attendance-record-top">
                            <div class="attendance-record-user">
                                <div class="attendance-record-avatar">
                                    {{ strtoupper(substr($attendance->user?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <strong>{{ $attendance->user?->name }}</strong>
                                    <span>
                                        Ma NV {{ $attendance->user?->employee_code }}
                                        - {{ $attendance->user?->branch?->name ?? 'Chưa có chi nhánh' }}
                                    </span>
                                </div>
                            </div>

                            <span class="attendance-record-status {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        <div class="attendance-record-shift">
                            <span>Ca làm</span>
                            <b>{{ $attendance->shift?->name ?? 'Không có ca' }}</b>
                            <small>Ngày công: {{ $attendance->work_date?->format('d/m/Y') ?? $attendance->checkin_at?->format('d/m/Y') ?? '-' }}</small>
                        </div>

                        <div class="attendance-record-times">
                            <div class="attendance-record-box">
                                <span>Giờ vào</span>
                                <strong>{{ $attendance->checkin_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                            </div>

                            <div class="attendance-record-box">
                                <span>Giờ ra</span>
                                <strong>{{ $attendance->checkout_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                            </div>
                        </div>

                        <div class="attendance-record-metrics">
                            <div class="attendance-record-box">
                                <span>Làm việc</span>
                                <strong>{{ $attendance->worked_minutes }}p</strong>
                            </div>

                            <div class="attendance-record-box">
                                <span>Tăng ca</span>
                                <strong>{{ number_format((float) ($attendance->overtime_hours ?? ($attendance->overtime_minutes / 60)), 2) }}h</strong>
                                <small>{{ $attendance->overtime_minutes }}p</small>
                            </div>
                        </div>

                        <div class="attendance-record-footer">
                            @if (!$attendance->is_locked || auth()->user()->role === 'admin')
                                <a href="{{ route('attendance-reports.edit', $attendance) }}" class="attendance-record-edit">
                                    Sửa công
                                </a>
                            @endif

                            @if (!$attendance->is_locked || auth()->user()->role === 'admin')
                                <form
                                    method="POST"
                                    action="{{ route('attendance-reports.destroy', $attendance) }}"
                                    onsubmit="return confirm('{{ $attendance->is_locked ? 'Bản ghi đã khóa. Admin xác nhận xóa dữ liệu đã khóa?' : 'Bạn chắc chắn muốn xóa bản ghi chấm công của ' . ($attendance->user?->name ?? '') . ' lúc ' . ($attendance->checkin_at?->format('d/m/Y H:i') ?? '-') . '?' }}')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    @if ($attendance->is_locked && auth()->user()->role === 'admin')
                                        <input type="hidden" name="confirm_locked" value="1">
                                    @endif
                                    <button type="submit" class="attendance-record-edit text-red-600 hover:text-red-700">
                                        Xóa công
                                    </button>
                                </form>
                            @endif

                            @if (auth()->user()->role === 'admin')
                                @if ($attendance->is_locked)
                                    <form method="POST" action="{{ route('attendance-reports.unlock', $attendance) }}" onsubmit="return confirm('Mở khóa bản ghi công này?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="attendance-record-edit text-amber-700 hover:text-amber-800">
                                            Mở khóa công
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('attendance-reports.lock', $attendance) }}" onsubmit="return confirm('Khóa bản ghi công này? Manager sẽ không thể sửa/xóa sau khi khóa.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="attendance-record-edit text-slate-700 hover:text-slate-900">
                                            Khóa công
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="attendance-record-empty">
                        Chưa có dữ liệu chấm công.
                    </div>
                @endforelse
            </section>

            <div class="mt-4">
                {{ $attendances->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
