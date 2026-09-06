<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
        <!-- Header -->
        <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-indigo-600">Quản trị hệ thống</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Danh Mục Chức Vụ (Positions)</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Quản lý tên chức vụ tiếng Việt, tên tiếng Anh in thẻ tên và nhóm quyền hạn phân cấp tương ứng.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('users.index') }}"
                        class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-200 transition"
                    >
                        Quản lý nhân sự
                    </a>
                    <a
                        href="{{ route('positions.create') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-indigo-700 transition"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Thêm chức vụ mới
                    </a>
                </div>
            </div>
        </section>

        <!-- Thông báo -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <!-- Bảng danh sách chức vụ -->
        <section class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-black uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-4">STT</th>
                            <th class="px-5 py-4">Chức vụ (Tiếng Việt)</th>
                            <th class="px-5 py-4">Tên tiếng Anh (In thẻ)</th>
                            <th class="px-5 py-4">Mã định danh</th>
                            <th class="px-5 py-4">Quyền hạn hệ thống</th>
                            <th class="px-5 py-4 text-center">Số nhân sự</th>
                            <th class="px-5 py-4 text-center">Trạng thái</th>
                            <th class="px-5 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse ($positions as $index => $pos)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-5 py-4 font-bold text-slate-400">
                                    {{ $pos->sort_order ?: ($index + 1) }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-black text-slate-900">{{ $pos->name }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($pos->name_en)
                                        <span class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-black uppercase tracking-wide text-amber-800 ring-1 ring-amber-200">
                                            {{ $pos->name_en }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Chưa đặt</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-500">
                                    {{ $pos->code }}
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $roleStyles = [
                                            'admin' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                            'manager' => 'bg-purple-50 text-purple-700 ring-purple-200',
                                            'cashier' => 'bg-blue-50 text-blue-700 ring-blue-200',
                                            'staff' => 'bg-slate-100 text-slate-700 ring-slate-200',
                                        ];
                                        $roleLabels = [
                                            'admin' => 'Ban Quản trị (Admin)',
                                            'manager' => 'Quản lý chi nhánh',
                                            'cashier' => 'Thu ngân (QR & Gán ca)',
                                            'staff' => 'Nhân viên vận hành',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 {{ $roleStyles[$pos->system_role] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">
                                        {{ $roleLabels[$pos->system_role] ?? $pos->system_role }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center font-bold text-slate-800">
                                    <span class="inline-block min-w-6 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-black text-slate-700">
                                        {{ $pos->users_count }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($pos->is_active)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            Đang dùng
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400">
                                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                            Đã ẩn
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            href="{{ route('positions.edit', $pos) }}"
                                            class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-indigo-600 transition"
                                            title="Sửa chức vụ"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        @if ($pos->users_count === 0)
                                            <form
                                                method="POST"
                                                action="{{ route('positions.destroy', $pos) }}"
                                                onsubmit="return confirm('Bạn có chắc chắn muốn xóa chức vụ \'{{ $pos->name }}\'?')"
                                                class="inline-block"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                                    title="Xóa chức vụ"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400 font-bold">
                                    Chưa có chức vụ nào trong hệ thống.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
