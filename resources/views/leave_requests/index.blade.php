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

    <div class="p-4 bg-gray-100">
        <div class="w-full space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Đơn xin OFF</h1>
                    <p class="text-sm text-gray-500">Theo dõi đơn nghỉ có phép của nhân sự.</p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    @if (auth()->user()->role === 'admin')
                        <a href="{{ route('leave-requests.admin-create') }}"
                           class="bg-emerald-600 text-white px-4 py-3 rounded-2xl font-bold">
                            Bổ sung phép
                        </a>
                    @elseif (in_array(auth()->user()->role, ['staff', 'cashier', 'manager'], true))
                        <a href="{{ route('leave-requests.create') }}"
                           class="bg-blue-600 text-white px-4 py-3 rounded-2xl font-bold">
                            Tạo đơn
                        </a>
                    @endif
                </div>
            </div>

            @if (session('success'))
                <div class="attendance-alert attendance-alert--success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="attendance-alert attendance-alert--error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <details class="rounded-3xl bg-white p-4 shadow md:open" open>
                <summary class="cursor-pointer text-sm font-black text-slate-800">Mở bộ lọc</summary>
                <form method="GET" action="{{ route('leave-requests.index') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    <input name="search" value="{{ $filters['search'] }}" class="rounded-2xl border-gray-300" placeholder="Tên nhân viên">
                    <select name="branch_id" class="rounded-2xl border-gray-300">
                        <option value="">Tất cả chi nhánh</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="role" class="rounded-2xl border-gray-300">
                        <option value="">Tất cả role</option>
                        @foreach ($roleLabels as $key => $label)
                            <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-2xl border-gray-300">
                        <option value="">Tất cả trạng thái</option>
                        @foreach ($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div>
                        <label class="text-xs font-bold text-gray-500">Ngày nghỉ</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-2xl border-gray-300" title="Từ ngày nghỉ">
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-2xl border-gray-300" title="Đến ngày nghỉ">
                        </div>
                    </div>
                    <select name="reviewed_by" class="rounded-2xl border-gray-300">
                        <option value="">Tất cả người duyệt</option>
                        @foreach ($reviewers as $reviewer)
                            <option value="{{ $reviewer->id }}" @selected((string) $filters['reviewed_by'] === (string) $reviewer->id)>{{ $reviewer->name }}</option>
                        @endforeach
                    </select>
                    <div>
                        <label class="text-xs font-bold text-gray-500">Ngày tạo đơn</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <input type="date" name="created_from" value="{{ $filters['created_from'] }}" class="rounded-2xl border-gray-300" title="Tạo từ ngày">
                            <input type="date" name="created_to" value="{{ $filters['created_to'] }}" class="rounded-2xl border-gray-300" title="Tạo đến ngày">
                        </div>
                    </div>
                    <div class="flex gap-2 md:col-span-3">
                        <button class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white">Tìm kiếm</button>
                        <a href="{{ route('leave-requests.index') }}" class="rounded-2xl bg-gray-100 px-4 py-3 text-sm font-black text-gray-700">Xóa lọc</a>
                    </div>
                </form>
            </details>

            <div class="space-y-3">
                @forelse ($leaveRequests as $request)
                    @php
                        $canReview = auth()->user()->role === 'admin'
                            || (
                                auth()->user()->role === 'manager'
                                && in_array($request->user?->role, ['staff', 'cashier'], true)
                                && $request->user?->branch_id === auth()->user()->branch_id
                            );

                        $statusClass = match ($request->status) {
                            'approved' => 'bg-green-100 text-green-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            default => 'bg-yellow-100 text-yellow-700',
                        };
                    @endphp

                    <div class="bg-white rounded-3xl shadow p-4 space-y-4">
                        <div class="flex justify-between gap-3">
                            <div>
                                <div class="font-bold">{{ $request->user?->name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $roleLabels[$request->user?->role] ?? $request->user?->role }} - {{ $request->user?->branch?->name ?? 'Không có chi nhánh' }}
                                </div>
                            </div>

                            <span class="text-xs px-3 py-1 rounded-full h-fit font-bold {{ $statusClass }}">
                                {{ $statusLabels[$request->status] ?? $request->status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-sm">
                            <div class="bg-gray-100 rounded-2xl p-3">
                                <div class="text-gray-500">Tạo đơn</div>
                                <div class="font-bold">{{ $request->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $request->creator?->name ?? '-' }}</div>
                            </div>

                            <div class="bg-gray-100 rounded-2xl p-3">
                                <div class="text-gray-500">Cập nhật</div>
                                <div class="font-bold">{{ $request->updated_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $request->updater?->name ?? '-' }}</div>
                            </div>

                            <div class="bg-gray-100 rounded-2xl p-3">
                                <div class="text-gray-500">Từ ngày</div>
                                <div class="font-bold">{{ $request->start_date?->format('d/m/Y') }}</div>
                            </div>

                            <div class="bg-gray-100 rounded-2xl p-3">
                                <div class="text-gray-500">Đến ngày</div>
                                <div class="font-bold">{{ $request->end_date?->format('d/m/Y') }}</div>
                            </div>

                            <div class="bg-gray-100 rounded-2xl p-3">
                                <div class="text-gray-500">Số ngày</div>
                                <div class="font-bold">{{ rtrim(rtrim(number_format($request->total_days, 2), '0'), '.') }}</div>
                            </div>

                        </div>

                        <div class="text-sm text-gray-700">
                            <div class="font-bold text-gray-900">Ly do</div>
                            <div class="mt-1 whitespace-pre-line">{{ $request->reason }}</div>
                        </div>

                        @if ($request->review_note)
                            <div class="text-sm bg-gray-100 rounded-2xl p-3">
                                <div class="font-bold">Ghi chú duyệt</div>
                                <div class="mt-1 whitespace-pre-line">{{ $request->review_note }}</div>
                            </div>
                        @endif

                        @if ($request->reviewed_by || $request->reviewed_at)
                            <div class="text-sm bg-gray-100 rounded-2xl p-3">
                                <div class="font-bold">
                                    {{ $request->status === 'rejected' ? 'Người từ chối' : 'Người duyệt' }}
                                </div>
                                <div class="mt-1 text-gray-700">
                                    {{ $request->reviewer?->name ?? '-' }}
                                    - {{ $request->reviewed_at?->format('d/m/Y H:i') ?? '-' }}
                                </div>
                            </div>
                        @endif

                        @if ($request->status === 'pending' && $canReview)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <form method="POST" action="{{ route('leave-requests.approve', $request) }}" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <input
                                        type="text"
                                        name="review_note"
                                        class="w-full rounded-2xl border-gray-300 text-sm"
                                        placeholder="Ghi chú duyệt (không bắt buộc)"
                                    >
                                    <button class="w-full bg-green-600 text-white py-3 rounded-2xl font-bold">
                                        Duyệt
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('leave-requests.reject', $request) }}" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <input
                                        type="text"
                                        name="review_note"
                                        class="w-full rounded-2xl border-gray-300 text-sm"
                                        placeholder="Lý do từ chối"
                                        required
                                    >
                                    <button class="w-full bg-red-600 text-white py-3 rounded-2xl font-bold">
                                        Từ chối
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white rounded-3xl shadow p-5 text-gray-500">
                        Chưa có đơn nghỉ phép.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $leaveRequests->links() }}
            </div>
        </div>
    </div>
</x-app-layout>