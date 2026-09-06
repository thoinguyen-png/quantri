<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8" x-data="{ viewMode: 'table' }">
        <!-- 1. Hero Header & Quick Actions -->
        <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3.5 py-1 text-xs font-black uppercase tracking-wide text-indigo-700 ring-1 ring-indigo-200">
                        <span class="h-2 w-2 rounded-full bg-indigo-600 animate-pulse"></span>
                        Hệ Thống Cơ Sở & Chi Nhánh
                    </div>
                    <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                        Quản Lý Cơ Sở (Chi Nhánh)
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium leading-relaxed text-slate-600">
                        Quản trị thông tin chi nhánh, số hotline, định vị GPS chấm công, cấu hình logo in thẻ nhân sự chuyên nghiệp và trạng thái hoạt động.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="{{ route('users.index') }}"
                        class="inline-flex min-h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:shadow-md"
                    >
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Danh sách nhân sự
                    </a>

                    <a
                        href="{{ route('branches.create') }}"
                        class="inline-flex min-h-12 items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700 hover:-translate-y-0.5"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Thêm Cơ Sở Mới
                    </a>
                </div>
            </div>

            <!-- Thống kê chỉ số KPI -->
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-500">Tổng cơ sở</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] }}</p>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-800">Đang hoạt động</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-black text-emerald-700">{{ $stats['active'] }}</p>
                </div>

                <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-rose-800">Ngưng hoạt động</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-rose-700 shadow-sm ring-1 ring-rose-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-black text-rose-700">{{ $stats['inactive'] }}</p>
                </div>

                <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-indigo-800">Tổng nhân sự</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-indigo-700 shadow-sm ring-1 ring-indigo-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-black text-indigo-700">
                        {{ $branches->sum('active_users_count') }} <span class="text-sm font-black text-indigo-500">NV</span>
                    </p>
                </div>
            </div>
        </section>

        <!-- 2. Thông báo Flash Alerts -->
        @if (session('success'))
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm font-bold text-emerald-900 shadow-sm backdrop-blur-sm">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="flex-1">{{ session('success') }}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm font-bold text-rose-900 shadow-sm backdrop-blur-sm">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <div class="flex-1">{{ session('error') }}</div>
            </div>
        @endif

        <!-- 3. Thanh tìm kiếm & Bộ lọc -->
        <section class="rounded-[2rem] border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <!-- Form tìm kiếm -->
                <form method="GET" action="{{ route('branches.index') }}" class="flex flex-1 flex-wrap items-center gap-2">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <div class="relative min-w-[280px] flex-1 max-w-lg">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Tìm kiếm theo tên cơ sở, địa chỉ, số hotline..."
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-2.5 pl-10 pr-4 text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-black text-white hover:bg-slate-800 transition"
                    >
                        Tìm kiếm
                    </button>

                    @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                        <a
                            href="{{ route('branches.index') }}"
                            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition"
                        >
                            Xóa lọc
                        </a>
                    @endif
                </form>

                <!-- Bộ lọc trạng thái & Switcher chế độ xem -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-xl bg-slate-100 p-1">
                        <a
                            href="{{ route('branches.index', ['search' => $filters['search'], 'status' => 'all']) }}"
                            class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $filters['status'] === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            Tất cả ({{ $stats['total'] }})
                        </a>
                        <a
                            href="{{ route('branches.index', ['search' => $filters['search'], 'status' => 'active']) }}"
                            class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $filters['status'] === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:text-emerald-700' }}"
                        >
                            Hoạt động ({{ $stats['active'] }})
                        </a>
                        <a
                            href="{{ route('branches.index', ['search' => $filters['search'], 'status' => 'inactive']) }}"
                            class="rounded-lg px-3 py-1.5 text-xs font-black transition {{ $filters['status'] === 'inactive' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            Ngưng ({{ $stats['inactive'] }})
                        </a>
                    </div>

                    <!-- Switcher Table vs Grid -->
                    <div class="hidden sm:inline-flex rounded-xl bg-slate-100 p-1">
                        <button
                            type="button"
                            @click="viewMode = 'table'"
                            :class="viewMode === 'table' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            class="rounded-lg p-1.5 transition"
                            title="Chế độ bảng"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'grid'"
                            :class="viewMode === 'grid' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            class="rounded-lg p-1.5 transition"
                            title="Chế độ thẻ"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4A. Hiển thị dạng Bảng (Table View) -->
        <section
            x-show="viewMode === 'table'"
            class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-200/60"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Logo Thẻ</th>
                            <th class="px-5 py-4">Cơ Sở & Hotline</th>
                            <th class="px-5 py-4">Địa Chỉ</th>
                            <th class="px-5 py-4">Định Vị GPS</th>
                            <th class="px-5 py-4 text-center">Nhân Sự</th>
                            <th class="px-5 py-4 text-center">Trạng Thái</th>
                            <th class="px-5 py-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse ($branches as $branch)
                            <tr class="transition hover:bg-slate-50/80 {{ ! $branch->is_active ? 'opacity-65 bg-slate-50/40' : '' }}">
                                <!-- Logo thẻ in -->
                                <td class="px-5 py-4">
                                    <div class="relative flex h-14 w-24 items-center justify-center overflow-hidden rounded-2xl bg-slate-950 p-2 shadow-inner ring-1 ring-slate-800">
                                        <img
                                            src="{{ $branch->staff_card_logo_url }}"
                                            alt="Logo {{ $branch->name }}"
                                            class="max-h-full max-w-full object-contain"
                                        >
                                    </div>
                                    <span class="mt-1.5 inline-block text-[10px] font-extrabold uppercase tracking-wider {{ $branch->hasStaffCardLogo() ? 'text-indigo-600' : 'text-slate-400' }}">
                                        {{ $branch->hasStaffCardLogo() ? 'Logo riêng' : 'Logo mặc định' }}
                                    </span>
                                </td>

                                <!-- Tên & Hotline -->
                                <td class="px-5 py-4">
                                    <a href="{{ route('branches.edit', $branch) }}" class="font-black text-slate-950 text-base hover:text-indigo-600 transition">
                                        {{ $branch->name }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-slate-400">#{{ $branch->id }}</span>

                                        @if ($branch->hotline)
                                            <a
                                                href="tel:{{ preg_replace('/\s+/', '', $branch->hotline) }}"
                                                class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-black text-amber-800 ring-1 ring-amber-200/80 hover:bg-amber-100 transition"
                                            >
                                                <svg class="h-3 w-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                {{ $branch->hotline }}
                                            </a>
                                        @else
                                            <span class="text-xs text-slate-400 italic">Chưa có hotline</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Địa chỉ -->
                                <td class="px-5 py-4 max-w-xs">
                                    @if ($branch->address)
                                        <div class="flex items-start gap-1.5">
                                            <svg class="h-4 w-4 text-slate-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="line-clamp-2 text-xs text-slate-700 font-medium leading-relaxed">
                                                {{ $branch->address }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Chưa cập nhật địa chỉ</span>
                                    @endif
                                </td>

                                <!-- GPS Coordinates & Radius -->
                                <td class="px-5 py-4">
                                    @if ($branch->latitude && $branch->longitude)
                                        <div class="space-y-1">
                                            <a
                                                href="https://www.google.com/maps?q={{ $branch->latitude }},{{ $branch->longitude }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 font-mono text-xs font-bold text-indigo-600 hover:underline"
                                            >
                                                <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                {{ number_format($branch->latitude, 5) }}, {{ number_format($branch->longitude, 5) }}
                                            </a>
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600">
                                                    R: {{ $branch->gps_radius ?? 100 }}m
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">
                                            Chưa cài GPS
                                        </span>
                                    @endif
                                </td>

                                <!-- Số nhân sự -->
                                <td class="px-5 py-4 text-center">
                                    <a
                                        href="{{ route('users.index', ['branch_id' => $branch->id]) }}"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 hover:bg-indigo-50 hover:text-indigo-700 transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        {{ $branch->active_users_count ?? 0 }} NV
                                    </a>
                                </td>

                                <!-- Trạng thái & Toggle nhanh -->
                                <td class="px-5 py-4 text-center">
                                    <form method="POST" action="{{ route('branches.toggle-status', $branch) }}" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        @if ($branch->is_active)
                                            <button
                                                type="submit"
                                                title="Bấm để chuyển sang ngưng hoạt động"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200/80 hover:bg-emerald-100 transition shadow-sm"
                                            >
                                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Hoạt động
                                            </button>
                                        @else
                                            <button
                                                type="submit"
                                                title="Bấm để kích hoạt lại cơ sở"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-300 hover:bg-slate-200 transition shadow-sm"
                                            >
                                                <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                                Ngưng hoạt động
                                            </button>
                                        @endif
                                    </form>
                                </td>

                                <!-- Thao tác -->
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('branches.edit', $branch) }}"
                                            class="inline-flex h-9 items-center gap-1 rounded-xl bg-slate-100 px-3 text-xs font-bold text-slate-800 hover:bg-indigo-600 hover:text-white transition shadow-sm"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Sửa & Logo
                                        </a>

                                        @if (($branch->total_users_count ?? 0) === 0)
                                            <form method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa cơ sở này?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex h-9 items-center justify-center rounded-xl bg-rose-50 px-2.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition"
                                                    title="Xóa cơ sở"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-bold text-slate-700">Không tìm thấy cơ sở nào phù hợp.</p>
                                        @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                                            <a href="{{ route('branches.index') }}" class="mt-2 inline-block text-xs font-black text-indigo-600 underline">
                                                Xem lại tất cả cơ sở
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 4B. Hiển thị dạng Thẻ Lưới (Grid Cards View) -->
        <section x-show="viewMode === 'grid'" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($branches as $branch)
                <div class="group relative flex flex-col justify-between overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl {{ ! $branch->is_active ? 'opacity-70 bg-slate-50/50' : '' }}">
                    <!-- Logo Header -->
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex h-16 w-28 items-center justify-center overflow-hidden rounded-2xl bg-slate-950 p-2 shadow-inner ring-1 ring-slate-800">
                                <img
                                    src="{{ $branch->staff_card_logo_url }}"
                                    alt="Logo {{ $branch->name }}"
                                    class="max-h-full max-w-full object-contain"
                                >
                            </div>

                            <form method="POST" action="{{ route('branches.toggle-status', $branch) }}">
                                @csrf
                                @method('PATCH')
                                @if ($branch->is_active)
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 transition"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Hoạt động
                                    </button>
                                @else
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-300 hover:bg-slate-200 transition"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                        Ngưng
                                    </button>
                                @endif
                            </form>
                        </div>

                        <!-- Tên & ID -->
                        <div class="mt-4">
                            <h3 class="text-lg font-black text-slate-950 group-hover:text-indigo-600 transition">
                                {{ $branch->name }}
                            </h3>
                            <p class="text-xs font-mono font-bold text-slate-400">Mã cơ sở: #{{ $branch->id }}</p>
                        </div>

                        <!-- Chi tiết thông tin -->
                        <div class="mt-4 space-y-2 text-xs">
                            <!-- Hotline -->
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-400 w-16">Hotline:</span>
                                @if ($branch->hotline)
                                    <a href="tel:{{ preg_replace('/\s+/', '', $branch->hotline) }}" class="inline-flex items-center gap-1 font-black text-amber-700 hover:underline">
                                        <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        {{ $branch->hotline }}
                                    </a>
                                @else
                                    <span class="text-slate-400 italic">Chưa có hotline</span>
                                @endif
                            </div>

                            <!-- Địa chỉ -->
                            <div class="flex items-start gap-2">
                                <span class="font-bold text-slate-400 w-16 flex-shrink-0">Địa chỉ:</span>
                                <span class="text-slate-700 font-medium line-clamp-2">
                                    {{ $branch->address ?: 'Chưa cập nhật' }}
                                </span>
                            </div>

                            <!-- GPS -->
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-400 w-16">Định vị:</span>
                                @if ($branch->latitude && $branch->longitude)
                                    <span class="font-mono text-slate-700 font-semibold">
                                        {{ number_format($branch->latitude, 4) }}, {{ number_format($branch->longitude, 4) }} ({{ $branch->gps_radius ?? 100 }}m)
                                    </span>
                                @else
                                    <span class="text-amber-700 font-bold">Chưa cài GPS</span>
                                @endif
                            </div>

                            <!-- Nhân sự -->
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-400 w-16">Nhân sự:</span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-800">
                                    {{ $branch->active_users_count ?? 0 }} người
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                        <a
                            href="{{ route('branches.edit', $branch) }}"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-indigo-600 transition"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Chỉnh Sửa & Logo
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[2rem] border border-slate-200 bg-white p-12 text-center text-slate-400">
                    <p class="font-bold">Không tìm thấy cơ sở nào phù hợp.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
