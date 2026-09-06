<x-app-layout>
    @php
        $modeLabels = [
            'normal' => 'Bình thường',
            'priority' => 'Ưu tiên',
        ];
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
    @endphp

    <div class="min-h-screen bg-slate-50 px-4 py-5 pb-28">
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Quản lý nhân sự</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Chế độ xác thực khuôn mặt</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Bình thường: nhìn thẳng + quay trái/phải. Ưu tiên: chỉ nhìn thẳng 3 giây.
                </p>
            </div>

            @if (session('success'))
                <div class="rounded-2xl bg-green-100 p-4 text-sm font-bold text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl bg-red-100 p-4 text-sm font-bold text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <div class="text-sm font-bold text-slate-500">Tổng user</div>
                    <div class="mt-2 text-3xl font-black text-slate-950">{{ $stats['total'] }}</div>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-green-100">
                    <div class="text-sm font-bold text-green-700">Bình thường</div>
                    <div class="mt-2 text-3xl font-black text-green-700">{{ $stats['normal'] }}</div>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-amber-100">
                    <div class="text-sm font-bold text-amber-700">Ưu tiên</div>
                    <div class="mt-2 text-3xl font-black text-amber-700">{{ $stats['priority'] }}</div>
                </div>
            </div>

            <form method="GET" action="{{ route('face-verification-modes.index') }}" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <div class="grid gap-3 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <label class="text-sm font-bold text-slate-700">Tìm kiếm</label>
                        <input name="search" value="{{ $filters['search'] }}" class="mt-1 w-full rounded-2xl border-slate-300" placeholder="Tên, điện thoại, email">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700">Chi nhánh</label>
                        <select name="branch_id" class="mt-1 w-full rounded-2xl border-slate-300">
                            <option value="">Tất cả</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700">Role</label>
                        <select name="role" class="mt-1 w-full rounded-2xl border-slate-300">
                            <option value="">Tất cả</option>
                            @foreach ($roleLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700">Trạng thái</label>
                        <select name="status" class="mt-1 w-full rounded-2xl border-slate-300">
                            <option value="">Tất cả</option>
                            @foreach ($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700">Chế độ</label>
                        <select name="face_verification_mode" class="mt-1 w-full rounded-2xl border-slate-300">
                            <option value="">Tất cả</option>
                            @foreach ($modeLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['face_verification_mode'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <button class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">Lọc danh sách</button>
                    <a href="{{ route('face-verification-modes.index') }}" class="rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-black text-slate-700">Xóa lọc</a>
                </div>
            </form>

            <form method="POST" action="{{ route('face-verification-modes.update') }}" id="bulk-face-mode-form" class="space-y-4">
                @csrf
                @foreach ($filters as $key => $value)
                    <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="update_type" id="bulk-update-type" value="selected">

                <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <div class="grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="text-sm font-bold text-slate-700">Chọn chế độ muốn cập nhật</label>
                            <select name="mode" class="mt-1 w-full rounded-2xl border-slate-300">
                                <option value="normal">Bình thường</option>
                                <option value="priority">Ưu tiên</option>
                            </select>
                        </div>
                        <button type="submit" onclick="document.getElementById('bulk-update-type').value='selected'" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-black text-white">
                            Cập nhật user đã chọn
                        </button>
                        <button type="button" onclick="openFilterConfirm()" class="rounded-2xl bg-amber-500 px-5 py-3 text-sm font-black text-white">
                            Cập nhật toàn bộ theo bộ lọc
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-100">
                    <div class="hidden grid-cols-[48px_1.5fr_0.8fr_1fr_0.8fr_0.9fr_1.4fr] gap-3 border-b border-slate-100 px-4 py-3 text-xs font-black uppercase text-slate-500 md:grid">
                        <div></div>
                        <div>Nhân viên</div>
                        <div>Role</div>
                        <div>Chi nhánh</div>
                        <div>Trạng thái</div>
                        <div>Hiện tại</div>
                        <div>Đổi nhanh</div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            @php($currentMode = $user->face_verification_mode ?: 'normal')
                            <div class="grid gap-3 px-4 py-4 md:grid-cols-[48px_1.5fr_0.8fr_1fr_0.8fr_0.9fr_1.4fr] md:items-center">
                                <div>
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="h-5 w-5 rounded border-slate-300">
                                </div>

                                <div>
                                    <div class="font-black text-slate-950">{{ $user->name }}</div>
                                    <div class="text-sm text-slate-500">{{ $user->email }}</div>
                                    <div class="text-xs text-slate-400">{{ $user->phone ?? $user->zalo_phone ?? '-' }}</div>
                                </div>

                                <div class="text-sm font-bold text-slate-700">{{ $roleLabels[$user->role] ?? $user->role }}</div>
                                <div class="text-sm text-slate-600">{{ $user->branch?->name ?? '-' }}</div>
                                <div class="text-sm text-slate-600">{{ $statusLabels[$user->status] ?? $user->status }}</div>

                                <div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $currentMode === 'priority' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                        {{ $modeLabels[$currentMode] ?? 'Bình thường' }}
                                    </span>
                                </div>

                                <div>
                                    <div class="flex gap-2">
                                        <select name="mode" form="quick-face-mode-{{ $user->id }}" class="min-w-0 flex-1 rounded-2xl border-slate-300 text-sm">
                                            <option value="normal" @selected($currentMode === 'normal')>Bình thường</option>
                                            <option value="priority" @selected($currentMode === 'priority')>Ưu tiên</option>
                                        </select>
                                        <button type="submit" form="quick-face-mode-{{ $user->id }}" class="rounded-2xl bg-slate-900 px-4 text-sm font-black text-white">Lưu</button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center text-sm font-bold text-slate-500">
                                Không có nhân sự phù hợp.
                            </div>
                        @endforelse
                    </div>
                </div>
            </form>

            @foreach ($users as $user)
                <form method="POST" action="{{ route('face-verification-modes.update') }}" id="quick-face-mode-{{ $user->id }}" class="hidden">
                    @csrf
                    <input type="hidden" name="update_type" value="single">
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    @foreach ($filters as $key => $value)
                        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                    @endforeach
                </form>
            @endforeach

            {{ $users->links() }}
        </div>
    </div>

    <div id="filter-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white p-5 shadow-xl">
            <h2 class="text-lg font-black text-slate-950">Xác nhận cập nhật toàn bộ</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Bạn đang cập nhật chế độ xác thực cho toàn bộ user theo bộ lọc hiện tại. Hành động này có thể ảnh hưởng đến quy trình chấm công. Bạn có chắc chắn không?
            </p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <button type="button" onclick="closeFilterConfirm()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">
                    Hủy
                </button>
                <button type="button" onclick="submitFilterUpdate()" class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-black text-white">
                    Xác nhận cập nhật
                </button>
            </div>
        </div>
    </div>

    <script>
        function openFilterConfirm() {
            document.getElementById('filter-confirm-modal').classList.remove('hidden');
            document.getElementById('filter-confirm-modal').classList.add('flex');
        }

        function closeFilterConfirm() {
            document.getElementById('filter-confirm-modal').classList.add('hidden');
            document.getElementById('filter-confirm-modal').classList.remove('flex');
        }

        function submitFilterUpdate() {
            document.getElementById('bulk-update-type').value = 'filter';
            document.getElementById('bulk-face-mode-form').submit();
        }
    </script>
</x-app-layout>
