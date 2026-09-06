<x-app-layout>
    @php
    $roleLabels = [
    'staff' => 'Nhan vien',
    'cashier' => 'Thu ngan',
    'manager' => 'Quan ly',
    'admin' => 'Quan tri',
    ];

    $statusLabels = [
    'thu_viec' => 'Thu viec',
    'chinh_thuc' => 'Chinh thuc',
    'da_nghi' => 'Da nghi',
    ];

    $employmentLabels = [
    'probation' => 'Thu viec',
    'official' => 'Chinh thuc',
    'resigned' => 'Da nghi',
    ];

    $avatarUrl = $user->avatar_url;
    $initial = mb_strtoupper(mb_substr(trim($user->name), 0, 1));
    $fieldClass = 'mt-1 w-full min-h-12 rounded-2xl border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-indigo-200';
    $cardClass = 'rounded-[1.75rem] border border-white/80 bg-white/95 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)]';
    @endphp

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-indigo-50/40 to-white px-3 py-4 sm:px-5 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <div class="rounded-[2rem] bg-slate-950 p-5 text-white shadow-[0_20px_55px_rgba(15,23,42,0.22)]">
                <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-200">Ho so cua toi</p>
                <h1 class="mt-2 text-2xl font-black sm:text-3xl">{{ $user->name }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">
                    Ma NV {{ $user->employee_code }} - {{ $roleLabels[$user->role] ?? $user->role }}
                </p>
            </div>

            @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">
                {{ session('success') }}
            </div>
            @endif

            @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                {{ $errors->first() }}
            </div>
            @endif

            <form
                method="POST"
                action="{{ route('users.me.update') }}"
                enctype="multipart/form-data"
                class="space-y-5"
                data-avatar-cropper>
                @csrf
                @method('PUT')

                <section class="{{ $cardClass }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div
                            class="relative grid h-28 w-28 shrink-0 place-items-center overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-indigo-500 to-sky-500 text-4xl font-black text-white shadow-lg">
                            @if ($avatarUrl)
                            <img
                                src="{{ $avatarUrl }}"
                                alt="Ảnh hiện tại của {{ $user->name }}"
                                data-avatar-current
                                class="absolute inset-0 h-full w-full object-cover">
                            @endif

                            <img
                                src=""
                                alt="Ảnh sau khi cắt"
                                data-avatar-preview
                                class="absolute inset-0 hidden h-full w-full object-cover">

                            <span
                                data-avatar-fallback
                                class="{{ $avatarUrl ? 'hidden' : '' }}">
                                {{ $initial ?: '?' }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-black text-slate-950">
                                Ảnh đại diện
                            </h2>

                            <p class="mt-1 text-sm font-semibold leading-6 text-slate-500">
                                Chọn ảnh, sau đó kéo, phóng to hoặc xoay để căn chỉnh.
                            </p>

                            <input
                                id="avatar"
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                data-avatar-input
                                class="mt-3 block w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-semibold">

                            <p class="mt-2 text-xs font-bold text-slate-500">
                                Ảnh xác nhận sẽ được lưu thành khung vuông 720 × 720 px.
                            </p>

                            @error('avatar')
                            <p class="mt-1 text-xs font-bold text-rose-600">
                                {{ $message }}
                            </p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin co ban</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Nhan su co the cap nhat thong tin lien he cua minh.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-black text-slate-800">Ho ten</label>
                            <input name="name" value="{{ old('name', $user->name) }}" class="{{ $fieldClass }}" required>
                            @error('name') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">So dien thoai</label>
                            <input name="phone" value="{{ old('phone', $user->phone) }}" inputmode="tel" class="{{ $fieldClass }}">
                            @error('phone') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-black text-slate-800">Email</label>
                            <input name="email" type="email" value="{{ old('email', $user->email) }}" class="{{ $fieldClass }}">
                            @error('email') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin CCCD</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Hien tai he thong chi luu so CCCD tren ho so nhan su.</p>
                    </div>

                    <div>
                        <label class="text-sm font-black text-slate-800">So CCCD</label>
                        <input name="citizen_id" value="{{ old('citizen_id', $user->citizen_id) }}" inputmode="numeric" class="{{ $fieldClass }}">
                        @error('citizen_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin cong viec</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Thong tin nay do quan ly/admin cap nhat.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Ma nhan su</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->employee_code }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Co so</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->branch?->name ?? 'Chua co' }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Chuc vu</div>
                            <div class="mt-2 font-black text-slate-950">{{ $roleLabels[$user->role] ?? $user->role }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Trang thai tai khoan</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->is_active ? 'Dang hoat dong' : 'Da khoa' }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Trang thai nhan su</div>
                            <div class="mt-2 font-black text-slate-950">{{ $statusLabels[$user->status] ?? $user->status ?? 'Chua co' }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Tinh trang lam viec</div>
                            <div class="mt-2 font-black text-slate-950">{{ $employmentLabels[$user->employment_status] ?? $user->employment_status ?? 'Chua co' }}</div>
                        </div>
                    </div>
                </section>

                <button class="min-h-14 w-full rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-4 text-sm font-black text-white shadow-lg">
                    Luu thong tin
                </button>
                <div
                    data-avatar-modal
                    aria-hidden="true"
                    class="fixed inset-0 z-[100] hidden overflow-y-auto overscroll-contain">
                    <button
                        type="button"
                        data-avatar-modal-close
                        aria-label="Đóng chỉnh ảnh"
                        class="absolute inset-0 h-full w-full bg-slate-950/80 backdrop-blur-sm"></button>

                    <div class="relative flex min-h-[100dvh] items-start justify-center p-2 sm:items-center sm:p-6">
                        <div class="relative z-10 my-2 flex max-h-[calc(100dvh-1rem)] w-full max-w-3xl flex-col overflow-hidden rounded-[1.5rem] bg-white shadow-2xl sm:my-0 sm:max-h-[calc(100dvh-3rem)] sm:rounded-[2rem]">
                            <div class="hidden shrink-0 border-b border-slate-200 px-5 py-4 sm:block">
                                <h3 class="text-lg font-black text-slate-950">
                                    Căn chỉnh ảnh nhân sự
                                </h3>

                                <p class="mt-1 text-sm font-semibold text-slate-500">
                                    Kéo ảnh trong khung vuông để chọn phần muốn sử dụng.
                                </p>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-5">
                                <div
                                    data-avatar-error
                                    class="mb-3 hidden rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700"></div>

                                <div
                                    data-avatar-stage
                                    class="relative h-[48dvh] min-h-[240px] max-h-[460px] w-full touch-none overflow-hidden rounded-2xl bg-slate-950 sm:h-[55vh] sm:min-h-[320px] sm:max-h-[560px] sm:rounded-3xl"></div>
                                <p class="mt-3 hidden text-sm font-semibold text-slate-500 sm:block">
                                    Mẹo: ảnh nên phủ kín khung vuông, tránh để hở lề caro ở các cạnh.
                                </p>

                                <div class="mt-3 grid grid-cols-6 gap-2 sm:mt-4 sm:grid-cols-3 xl:grid-cols-6">
                                    <button
                                        type="button"
                                        data-crop-action="zoom-out"
                                        aria-label="Thu nhỏ"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">−</span>
                                        <span class="hidden sm:inline">Thu nhỏ</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="zoom-in"
                                        aria-label="Phóng to"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">＋</span>
                                        <span class="hidden sm:inline">Phóng to</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="rotate-left"
                                        aria-label="Xoay trái"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">↶</span>
                                        <span class="hidden sm:inline">Xoay trái</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="rotate-right"
                                        aria-label="Xoay phải"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">↷</span>
                                        <span class="hidden sm:inline">Xoay phải</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="reset"
                                        aria-label="Đặt lại"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">⟳</span>
                                        <span class="hidden sm:inline">Đặt lại</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="reselect"
                                        aria-label="Chọn lại ảnh"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-amber-50 px-2 text-sm font-black text-amber-700 sm:col-span-3 sm:block sm:px-3 xl:col-span-1">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">▣</span>
                                        <span class="hidden sm:inline">Chọn lại ảnh</span>
                                    </button>
                                </div>
                            </div>

                            <div
                                class="sticky bottom-0 z-20 shrink-0 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-12px_30px_rgba(15,23,42,0.10)] backdrop-blur sm:static sm:bg-white sm:p-5 sm:shadow-none"
                                style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                                <div class="grid grid-cols-[0.8fr_1.2fr] gap-2 sm:grid-cols-2 sm:gap-3">
                                    <button
                                        type="button"
                                        data-crop-action="cancel"
                                        class="min-h-12 rounded-xl bg-slate-100 px-3 text-sm font-black text-slate-700 sm:rounded-2xl sm:px-5">
                                        Hủy
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="confirm"
                                        class="min-h-12 rounded-xl bg-gradient-to-r from-indigo-600 to-sky-600 px-3 text-sm font-black text-white shadow-lg sm:rounded-2xl sm:px-5">
                                        Lưu ảnh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <section class="{{ $cardClass }} space-y-4">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Nhan dien / QR danh gia</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Dang ky khuon mat va xem ma QR danh gia ca nhan.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <a href="{{ route('face.register') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-slate-950 px-5 py-4 text-sm font-black text-white shadow">
                        Dang ky / cap nhat khuon mat
                    </a>

                    <div class="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-600">
                        Khuon mat: <span class="text-slate-950">{{ $user->face_descriptor ? 'Da dang ky' : 'Chua dang ky' }}</span>
                    </div>
                </div>
            </section>

            @include('rating_qrs.partials.panel', [
            'user' => $user,
            'title' => 'Ma QR danh gia cua toi',
            ])

            <section class="{{ $cardClass }}">
                <div class="mb-4">
                    <h2 class="text-lg font-black text-slate-950">Doi mat khau</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Form doi mat khau duoc giu rieng theo cau truc hien co.</p>
                </div>
                @include('profile.partials.update-password-form')
            </section>

            @if (in_array($user->role, ['manager', 'cashier'], true))
            <section class="{{ $cardClass }} space-y-3">
                <h2 class="text-lg font-black text-slate-950">
                    {{ $user->role === 'cashier' ? 'Phan ca' : 'Quan ly chi nhanh' }}
                </h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($user->role === 'manager')
                    <a href="{{ route('users.index') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-slate-100 px-5 py-4 text-sm font-black text-slate-800">
                        Nhan su
                    </a>
                    @endif

                    <a href="{{ route('shift-assignments.index') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-slate-100 px-5 py-4 text-sm font-black text-slate-800">
                        Gan ca lam
                    </a>
                </div>
            </section>
            @endif

            <form method="POST" action="{{ route('logout', absolute: false) }}">
                @csrf

                <button class="min-h-14 w-full rounded-3xl bg-white py-4 text-sm font-black text-rose-600 shadow">
                    Dang xuat
                </button>
            </form>
        </div>
    </div>

    @include('rating_qrs.partials.confirm-modal')
</x-app-layout>