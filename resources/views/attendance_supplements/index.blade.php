<x-app-layout>
    @php
        $statusLabels = [
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
        ];

        $roleLabels = [
            'staff' => 'Nhân viên',
            'cashier' => 'Thu ngân',
            'manager' => 'Quản lý',
            'admin' => 'Quản trị',
        ];
    @endphp

    <div class="supplement-page">
        <div class="supplement-shell">
            <section class="supplement-hero">
                <div>
                    <div class="supplement-eyebrow">Attendance request</div>
                    <h1 class="supplement-title">Bổ sung công</h1>
                    <p class="supplement-subtitle">Theo dõi, tạo và duyệt các đơn bổ sung checkin/checkout.</p>
                </div>

                @if (in_array(auth()->user()->role, ['staff', 'cashier', 'manager', 'admin'], true))
                    <a href="{{ route('attendance-supplements.create') }}" class="supplement-primary-action">
                        Tạo đơn
                    </a>
                @endif
            </section>

            @if (session('success'))
                <div class="attendance-alert attendance-alert--success mt-4">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="attendance-alert mt-4 border border-amber-200 bg-amber-50 text-amber-700" role="status">
                    {{ session('warning') }}
                </div>
            @endif

            @if (session('supplement_bulk_result'))
                @php
                    $bulkResult = session('supplement_bulk_result');
                @endphp
                <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap gap-3 text-sm font-bold">
                        <span class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">Thành công: {{ $bulkResult['success_count'] }}</span>
                        <span class="rounded-lg bg-red-50 px-3 py-2 text-red-700">Thất bại: {{ $bulkResult['fail_count'] }}</span>
                    </div>
                    @if (!empty($bulkResult['failed_items']))
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-slate-500"><tr><th class="px-2 py-1">Nhân viên</th><th class="px-2 py-1">Ngày</th><th class="px-2 py-1">Lý do</th></tr></thead>
                                <tbody>@foreach ($bulkResult['failed_items'] as $item)<tr class="border-t border-slate-100"><td class="px-2 py-2">{{ $item['user_name'] }}</td><td class="px-2 py-2">{{ $item['work_date'] }}</td><td class="px-2 py-2 text-red-700">{{ $item['reason'] }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            @if ($errors->any())
                <div class="attendance-alert attendance-alert--error mt-4" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <details class="mt-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-100 md:open" open>
                <summary class="cursor-pointer text-sm font-black text-slate-800">Mở bộ lọc</summary>
                <form method="GET" action="{{ route('attendance-supplements.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    <input name="search" value="{{ $filters['search'] }}" class="rounded-2xl border-slate-300" placeholder="Tên nhân viên">
                    <select name="branch_id" class="rounded-2xl border-slate-300">
                        <option value="">Tất cả chi nhánh</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="role" class="rounded-2xl border-slate-300">
                        <option value="">Tất cả role</option>
                        @foreach ($roleLabels as $key => $label)
                            <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-2xl border-slate-300">
                        <option value="">Tất cả trạng thái</option>
                        @foreach ($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Ngày công</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-2xl border-slate-300" title="Từ ngày làm">
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-2xl border-slate-300" title="Đến ngày làm">
                        </div>
                    </div>
                    <select name="shift_id" class="rounded-2xl border-slate-300">
                        <option value="">Tất cả ca</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((string) $filters['shift_id'] === (string) $shift->id)>{{ $shift->name }}</option>
                        @endforeach
                    </select>
                    <select name="reviewed_by" class="rounded-2xl border-slate-300">
                        <option value="">Tất cả người duyệt</option>
                        @foreach ($reviewers as $reviewer)
                            <option value="{{ $reviewer->id }}" @selected((string) $filters['reviewed_by'] === (string) $reviewer->id)>{{ $reviewer->name }}</option>
                        @endforeach
                    </select>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Ngày tạo đơn</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <input type="date" name="created_from" value="{{ $filters['created_from'] }}" class="rounded-2xl border-slate-300" title="Tạo từ ngày">
                            <input type="date" name="created_to" value="{{ $filters['created_to'] }}" class="rounded-2xl border-slate-300" title="Tạo đến ngày">
                        </div>
                    </div>
                    <div class="flex gap-2 md:col-span-4">
                        <button class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white">Tìm kiếm</button>
                        <a href="{{ route('attendance-supplements.index') }}" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">Xóa lọc</a>
                    </div>
                </form>
            </details>

            @if (in_array(auth()->user()->role, ['admin', 'manager'], true))
                <form id="supplement-bulk-review-form" method="POST" action="{{ route('attendance-supplements.bulk-review') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <label class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700"><input data-select-all type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600"> Chọn tất cả đơn chờ duyệt trong trang này</label>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">
                        <div><label for="bulk-review-note" class="text-xs font-bold text-slate-500">Lý do từ chối chung (bắt buộc khi từ chối)</label><input id="bulk-review-note" type="text" name="review_note" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Nhập lý do nếu từ chối các đơn đã chọn"></div>
                        <button type="submit" name="action" value="approve" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Duyệt đã chọn</button>
                        <button type="submit" name="action" value="reject" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white">Từ chối đã chọn</button>
                    </div>
                </form>
            @endif

            <section class="supplement-list">
                @forelse ($requests as $request)
                    @php
                        $canReview = $request->user_id !== auth()->id()
                            && (auth()->user()->role === 'admin'
                            || (
                                auth()->user()->role === 'manager'
                                && in_array($request->user?->role, ['staff', 'cashier'], true)
                                && $request->user?->branch_id === auth()->user()->branch_id
                            ));

                        $statusModifier = match ($request->status) {
                            'approved' => 'supplement-status--approved',
                            'rejected' => 'supplement-status--rejected',
                            default => 'supplement-status--pending',
                        };

                        $segmentPayload = collect($request->segment_payload ?: []);
                        $segmentLabel = $segmentPayload->isNotEmpty()
                            ? $segmentPayload->count() . ' đoạn'
                            : ($request->segment_order ? 'Đoạn ' . $request->segment_order : 'Ca thường');
                        $expectedTime = ($request->requested_checkin_at?->format('H:i') ?? '--:--')
                            . ' -> '
                            . ($request->requested_checkout_at?->format('H:i') ?? '--:--');
                    @endphp

                    <article class="supplement-card">
                        <div class="supplement-card__top">
                            @if ($request->status === 'pending' && $canReview)
                                <label class="mr-2 flex shrink-0 items-center" title="Chọn đơn này để duyệt hoặc từ chối hàng loạt">
                                    <input type="checkbox" class="supplement-bulk-checkbox h-4 w-4 rounded border-slate-300 text-blue-600" form="supplement-bulk-review-form" name="request_ids[]" value="{{ $request->id }}">
                                </label>
                            @endif
                            <div class="supplement-user">
                                <div class="supplement-avatar">
                                    {{ strtoupper(substr($request->user?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <strong>{{ $request->user?->name }}</strong>
                                    <span>
                                        {{ $roleLabels[$request->user?->role] ?? $request->user?->role }}
                                        - Ma NV {{ $request->user?->employee_code }}
                                        - {{ $request->user?->branch?->name ?? 'Không có chi nhánh' }}
                                    </span>
                                </div>
                            </div>

                            <span class="supplement-status {{ $statusModifier }}">
                                {{ $statusLabels[$request->status] ?? $request->status }}
                            </span>
                        </div>

                        <div class="supplement-metrics">
                            <div class="supplement-metric">
                                <span>Tạo đơn</span>
                                <strong>{{ $request->created_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                                <small>{{ $request->creator?->name ?? '-' }}</small>
                            </div>

                            <div class="supplement-metric">
                                <span>Cập nhật</span>
                                <strong>{{ $request->updated_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                                <small>{{ $request->updater?->name ?? '-' }}</small>
                            </div>

                            <div class="supplement-metric">
                                <span>Ngày công</span>
                                <strong>{{ $request->work_date?->format('d/m/Y') }}</strong>
                            </div>

                            <div class="supplement-metric">
                                <span>Segment</span>
                                <strong>{{ $segmentLabel }}</strong>
                            </div>

                            <div class="supplement-metric">
                                <span>Giờ dự kiến</span>
                                <strong>{{ $expectedTime }}</strong>
                            </div>

                            <div class="supplement-metric">
                                <span>Trạng thái</span>
                                <strong>{{ $statusLabels[$request->status] ?? $request->status }}</strong>
                            </div>
                        </div>

                        <div class="supplement-section">
                            <div class="supplement-section__title">Ly do</div>
                            <div class="whitespace-pre-line">{{ $request->reason }}</div>
                        </div>

                        @if ($segmentPayload->isNotEmpty())
                            <div class="supplement-section">
                                <div class="supplement-section__title">Chi tiết ca gãy</div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    @foreach ($segmentPayload as $segment)
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                            <div class="font-black">Đoạn {{ $segment['order'] ?? $loop->iteration }}</div>
                                            <div class="mt-1 text-slate-600">
                                                Dự kiến:
                                                {{ $segment['scheduled_start'] ?? '--:--' }}
                                                ->
                                                {{ $segment['scheduled_end'] ?? '--:--' }}
                                            </div>
                                            <div class="mt-1 font-bold text-slate-800">
                                                Bổ sung:
                                                {{ $segment['checkin_time'] ?? '--:--' }}
                                                ->
                                                {{ $segment['checkout_time'] ?? '--:--' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($request->review_note)
                            <div class="supplement-review">
                                <div class="supplement-section__title">Ghi chú duyệt</div>
                                <div class="whitespace-pre-line">{{ $request->review_note }}</div>
                            </div>
                        @endif

                        @if ($request->reviewed_by || $request->reviewed_at)
                            <div class="supplement-review">
                                <div class="supplement-section__title">
                                    {{ $request->status === 'rejected' ? 'Người từ chối' : 'Người duyệt' }}
                                </div>
                                <div class="text-sm text-slate-700">
                                    {{ $request->reviewer?->name ?? '-' }}
                                    - {{ $request->reviewed_at?->format('d/m/Y H:i') ?? '-' }}
                                </div>
                            </div>
                        @endif

                        @if ($request->status === 'pending' && $canReview)
                            <div class="supplement-actions">
                                <form method="POST" action="{{ route('attendance-supplements.approve', $request) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input
                                        type="text"
                                        name="review_note"
                                        class="supplement-input"
                                        placeholder="Ghi chú duyệt (không bắt buộc)"
                                    >
                                    <button class="supplement-approve">
                                        Duyệt
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('attendance-supplements.reject', $request) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input
                                        type="text"
                                        name="review_note"
                                        class="supplement-input"
                                        placeholder="Lý do từ chối"
                                        required
                                    >
                                    <button class="supplement-reject">
                                        Từ chối
                                    </button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="supplement-empty">
                        Chưa có đơn bổ sung công.
                    </div>
                @endforelse
            </section>

            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        </div>
    </div>

    @if (in_array(auth()->user()->role, ['admin', 'manager'], true))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('supplement-bulk-review-form');
                const selectAll = form?.querySelector('[data-select-all]');
                if (!form || !selectAll) return;
                const checkboxes = () => Array.from(document.querySelectorAll('.supplement-bulk-checkbox'));
                selectAll.addEventListener('change', () => checkboxes().forEach((checkbox) => checkbox.checked = selectAll.checked));
                document.addEventListener('change', (event) => {
                    if (!event.target.matches('.supplement-bulk-checkbox')) return;
                    const items = checkboxes();
                    selectAll.checked = items.length > 0 && items.every((checkbox) => checkbox.checked);
                    selectAll.indeterminate = items.some((checkbox) => checkbox.checked) && !selectAll.checked;
                });
            });
        </script>
    @endif
</x-app-layout>
