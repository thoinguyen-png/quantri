<x-app-layout>
    @php
        $roleLabels = [
            'staff' => 'Nhan vien',
            'cashier' => 'Thu ngan',
            'manager' => 'Quan ly',
            'admin' => 'Quan tri',
        ];

        $statusLabels = [
            'chinh_thuc' => 'Chinh thuc',
            'thu_viec' => 'Thu viec',
            'da_nghi' => 'Da nghi',
        ];

        $statusClasses = [
            'chinh_thuc' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'thu_viec' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'da_nghi' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];

        $employmentLabels = [
            'probation' => 'Thu viec',
            'official' => 'Chinh thuc',
            'resigned' => 'Da nghi',
        ];

        $accountClasses = [
            true => 'bg-sky-50 text-sky-700 ring-sky-200',
            false => 'bg-slate-100 text-slate-600 ring-slate-200',
        ];

        $faceModeLabels = [
            'normal' => 'Mat thuong',
            'priority' => 'Mat uu tien',
        ];

        $faceModeClasses = [
            'normal' => 'bg-teal-50 text-teal-700 ring-teal-200',
            'priority' => 'bg-violet-50 text-violet-700 ring-violet-200',
        ];

        $activeFilterCount = collect([
            $filters['search'],
            $filters['branch_id'],
            $filters['role'],
            $filters['status'],
            $filters['face_verification_mode'],
            $filters['start_from'],
            $filters['start_to'],
        ])->filter(fn ($value) => trim((string) $value) !== '')->count();
    @endphp

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-indigo-50/30 to-white px-3 py-4 sm:px-5 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            <section class="overflow-hidden rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_22px_70px_rgba(15,23,42,0.08)] sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700">
                            Quan ly nhan su
                        </div>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                            Danh sach nhan su
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                            Theo doi ho so, chi nhanh, vai tro va QR danh gia cua nhan su trong pham vi quan ly.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:flex sm:flex-wrap sm:justify-end">
                        @if (auth()->user()->role === 'admin')
                            <a href="{{ route('branches.index') }}"
                               class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                Quản lý cơ sở
                            </a>
                            <a href="{{ route('admin.work-histories.index') }}"
                               class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                Lich su cong tac
                            </a>
                        @endif

                        <a href="{{ route('staff-cards.export.index') }}"
                           class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-black text-indigo-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            Xuất thẻ nhân sự
                        </a>

                        <a href="{{ route('users.create') }}"
                           class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-5 text-sm font-black text-white shadow-lg shadow-indigo-200 transition hover:-translate-y-0.5">
                            Them nhan su
                        </a>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-3xl bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase text-slate-400">Dang hien thi</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $users->total() }}</p>
                    </div>
                    <div class="rounded-3xl bg-indigo-50 p-4">
                        <p class="text-xs font-black uppercase text-indigo-400">Nhom</p>
                        <p class="mt-1 text-lg font-black text-indigo-900">
                            {{ $filters['employment_tab'] === 'resigned' ? 'Nhan su da nghi' : 'Nhan su dang lam' }}
                        </p>
                    </div>
                    <div class="rounded-3xl bg-emerald-50 p-4">
                        <p class="text-xs font-black uppercase text-emerald-500">Bo loc</p>
                        <p class="mt-1 text-lg font-black text-emerald-900">{{ $activeFilterCount }} dang ap dung</p>
                    </div>
                </div>
            </section>

            @if (session('success'))
                <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <section class="rounded-[1.75rem] border border-white/80 bg-white/90 p-3 shadow-[0_16px_50px_rgba(15,23,42,0.06)] sm:p-4">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <a
                        href="{{ route('users.index', array_filter(array_merge(request()->except(['page', 'employment_tab', 'status']), ['employment_tab' => 'active']))) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl px-4 text-sm font-black transition {{ $filters['employment_tab'] === 'active' ? 'bg-slate-950 text-white shadow-lg shadow-slate-200' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                    >
                        Dang lam
                    </a>
                    <a
                        href="{{ route('users.index', array_filter(array_merge(request()->except(['page', 'employment_tab', 'status']), ['employment_tab' => 'resigned']))) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl px-4 text-sm font-black transition {{ $filters['employment_tab'] === 'resigned' ? 'bg-rose-600 text-white shadow-lg shadow-rose-100' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                    >
                        Da nghi
                    </a>
                </div>
            </section>

            <details class="rounded-[1.75rem] border border-white/80 bg-white/95 p-4 shadow-[0_16px_50px_rgba(15,23,42,0.06)] md:open" open>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-black text-slate-800">
                    <span>Bo loc nhan su</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500">{{ $activeFilterCount }} loc</span>
                </summary>

                <form method="GET" action="{{ route('users.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
                    <input type="hidden" name="employment_tab" value="{{ $filters['employment_tab'] }}">

                    <label class="grid gap-1 md:col-span-4">
                        <span class="text-xs font-black uppercase text-slate-400">Tim nhanh</span>
                        <input name="search" value="{{ $filters['search'] }}" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200" placeholder="Ten / Ma NV / SDT / Email">
                    </label>

                    <label class="grid gap-1 md:col-span-2">
                        <span class="text-xs font-black uppercase text-slate-400">Chi nhanh</span>
                        <select name="branch_id" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="">Tat ca</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 md:col-span-2">
                        <span class="text-xs font-black uppercase text-slate-400">Vai tro</span>
                        <select name="role" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="">Tat ca</option>
                            @foreach ($roleLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 md:col-span-2">
                        <span class="text-xs font-black uppercase text-slate-400">Trang thai</span>
                        <select name="status" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="">Tat ca</option>
                            @foreach ($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 md:col-span-2">
                        <span class="text-xs font-black uppercase text-slate-400">Khuon mat</span>
                        <select name="face_verification_mode" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="">Tat ca</option>
                            @foreach ($faceModeLabels as $key => $label)
                                <option value="{{ $key }}" @selected($filters['face_verification_mode'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 md:col-span-3">
                        <span class="text-xs font-black uppercase text-slate-400">Tu ngay vao lam</span>
                        <input type="date" name="start_from" value="{{ $filters['start_from'] }}" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                    </label>

                    <label class="grid gap-1 md:col-span-3">
                        <span class="text-xs font-black uppercase text-slate-400">Den ngay vao lam</span>
                        <input type="date" name="start_to" value="{{ $filters['start_to'] }}" class="min-h-12 rounded-2xl border-slate-200 bg-slate-50 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-200">
                    </label>

                    <div class="grid grid-cols-2 gap-2 md:col-span-6 md:flex md:items-end md:justify-end">
                        <button class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white shadow-lg shadow-slate-200">
                            Tim kiem
                        </button>
                        <a href="{{ route('users.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-100 px-5 text-sm font-black text-slate-700">
                            Xoa loc
                        </a>
                    </div>
                </form>
            </details>

            <section class="grid gap-4 xl:grid-cols-2">
                @forelse ($users as $user)
                    @php
                        $avatarUrl = $user->avatar_url;
                        $initial = mb_strtoupper(mb_substr(trim($user->name), 0, 1));
                        $status = $user->status ?? 'thu_viec';
                        $faceMode = $user->face_verification_mode ?? 'normal';
                        $isActive = (bool) $user->is_active;
                    @endphp

                    <article class="overflow-hidden rounded-[1.75rem] border border-white/80 bg-white shadow-[0_18px_55px_rgba(15,23,42,0.07)] transition hover:-translate-y-0.5 hover:shadow-[0_24px_70px_rgba(15,23,42,0.10)]">
                        <div class="p-4 sm:p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-3 sm:gap-4">
                                    <div
                                        class="relative grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-500 to-sky-500 text-2xl font-black text-white shadow-lg shadow-indigo-100 ring-1 ring-white"
                                        x-data="{ imageOk: {{ $avatarUrl ? 'true' : 'false' }} }"
                                    >
                                        @if ($avatarUrl)
                                            <img
                                                src="{{ $avatarUrl }}"
                                                alt=""
                                                class="absolute inset-0 h-full w-full object-cover"
                                                x-show="imageOk"
                                                x-on:error="imageOk = false"
                                            >
                                        @endif
                                        <span x-show="!imageOk">{{ $initial ?: '?' }}</span>
                                    </div>

                                    <div class="min-w-0 pt-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="max-w-full truncate text-xl font-black leading-tight text-slate-950">
                                                {{ $user->name }}
                                            </h2>
                                            <span class="rounded-full bg-slate-950 px-2.5 py-1 font-mono text-xs font-black text-white">
                                                {{ $user->employee_code }}
                                            </span>
                                        </div>

                                        <div class="mt-2 grid gap-1 text-sm font-semibold text-slate-500">
                                            <span>{{ $user->phone ?: 'Chua co so dien thoai' }}</span>
                                            <span class="truncate">{{ $user->email ?: 'Chua co email' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-black ring-1 {{ $accountClasses[$isActive] }}">
                                        {{ $isActive ? 'Tai khoan bat' : 'Tai khoan tat' }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-black ring-1 {{ $statusClasses[$status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $statusLabels[$status] ?? $status }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-100">
                                    {{ $user->position?->name ?? ($roleLabels[$user->role] ?? $user->role) }}
                                </span>
                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                                    {{ $user->branch?->name ?? 'Chua co chi nhanh' }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-black ring-1 {{ $faceModeClasses[$faceMode] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $faceModeLabels[$faceMode] ?? $faceMode }}
                                </span>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-500 ring-1 ring-slate-200">
                                    {{ $employmentLabels[$user->employment_status] ?? $user->employment_status ?? 'Chua ro' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-xs font-black uppercase text-slate-400">Ngay tao</p>
                                    <p class="mt-1 font-black text-slate-900">{{ $user->created_at?->format('d/m/Y') ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-xs font-black uppercase text-slate-400">Vao lam</p>
                                    <p class="mt-1 font-black text-slate-900">{{ $user->hired_at?->format('d/m/Y') ?? $user->start_work_date?->format('d/m/Y') ?? '-' }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $user->hiredBy?->name ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-xs font-black uppercase text-slate-400">Chinh thuc</p>
                                    <p class="mt-1 font-black text-slate-900">{{ $user->official_at?->format('d/m/Y') ?? '-' }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $user->officialBy?->name ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-xs font-black uppercase text-slate-400">Nghi viec</p>
                                    <p class="mt-1 font-black text-slate-900">{{ $user->resigned_at?->format('d/m/Y') ?? '-' }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $user->resignedBy?->name ?? '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 sm:flex sm:flex-wrap sm:items-center">
                                <a href="{{ route('users.edit', $user) }}"
                                   class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white shadow-lg shadow-slate-200">
                                    Cap nhat
                                </a>
                                @can('viewStaffCard', $user)
                                    <a href="{{ route('users.staff-card.preview', $user) }}"
                                       class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-indigo-600 px-4 text-sm font-black text-white shadow-lg shadow-indigo-100">
                                        Xem truoc the
                                    </a>
                                @endcan

                                @if (\Illuminate\Support\Facades\Gate::allows('viewRatingQr', $user))
                                    <details class="group rounded-2xl border border-slate-200 bg-slate-50 sm:min-w-80 sm:flex-1">
                                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm font-black text-slate-800">
                                            <span>Xem / tai QR danh gia</span>
                                            <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                        </summary>
                                        <div class="border-t border-slate-200 p-3">
                                            @include('rating_qrs.partials.panel', [
                                                'user' => $user,
                                                'title' => 'Ma QR danh gia',
                                                'autoload' => false,
                                            ])
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white/80 p-8 text-center shadow-sm xl:col-span-2">
                        <p class="text-lg font-black text-slate-800">Chua co nhan su phu hop</p>
                        <p class="mt-2 text-sm font-medium text-slate-500">Thu xoa bot bo loc hoac tao nhan su moi.</p>
                    </div>
                @endforelse
            </section>

            <div class="rounded-[1.5rem] bg-white/90 p-3 shadow-sm">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    @include('rating_qrs.partials.confirm-modal')
</x-app-layout>
