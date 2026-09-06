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
    $currentRole = auth()->user()->role;
    $fieldClass = 'mt-1 w-full min-h-12 rounded-2xl border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-indigo-200';
    $selectClass = $fieldClass;
    $cardClass = 'rounded-[1.75rem] border border-white/80 bg-white/95 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)]';
    @endphp

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-indigo-50/40 to-white px-3 py-4 sm:px-5 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="flex flex-col gap-3 rounded-[2rem] bg-slate-950 p-5 text-white shadow-[0_20px_55px_rgba(15,23,42,0.22)] sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-200">Ho so nhan su</p>
                    <h1 class="mt-2 text-2xl font-black sm:text-3xl">Cap nhat nhan su</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-300">
                        Chinh sua thong tin ca nhan, cong viec, nhan dien va QR danh gia cua {{ $user->name }}.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($currentRole === 'admin')
                    <a href="{{ route('admin.users.work-histories.index', $user) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white/10 px-4 text-sm font-black text-white ring-1 ring-white/20">
                        Lich su cong tac
                    </a>
                    @endif

                    <a href="{{ route('users.index') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-black text-slate-950">
                        Quay lai
                    </a>
                </div>
            </div>

            @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                {{ $errors->first() }}
            </div>
            @endif

            <form
                method="POST"
                action="{{ route('users.update', $user) }}"
                enctype="multipart/form-data"
                class="space-y-5"
                data-avatar-cropper
                x-data="{
        initialBranch: '{{ $user->branch_id }}',
        branchId: '{{ old('branch_id', $user->branch_id) }}'
    }">
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
                                Chọn ảnh, sau đó kéo, phóng to hoặc xoay để căn đúng khuôn mặt.
                            </p>

                            <input
                                id="avatar"
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                data-avatar-input
                                class="mt-3 block w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-semibold">

                            <p class="mt-2 text-xs font-bold text-slate-500">
                                Ảnh sau khi xác nhận sẽ được lưu thành khung vuông 720 × 720 px.
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
                        <p class="mt-1 text-sm font-semibold text-slate-500">Thong tin nhan dien va lien he hang ngay.</p>
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

                @if ($currentRole === 'admin')
                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Dat lai mat khau</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            Chi admin duoc thay doi. De trong neu khong muon doi mat khau hien tai.
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-black text-slate-800">Mat khau moi</label>
                        <input
                            type="password"
                            name="password"
                            minlength="6"
                            autocomplete="new-password"
                            class="{{ $fieldClass }}"
                            placeholder="Nhap mat khau moi, toi thieu 6 ky tu">
                        @error('password')
                        <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
                @endif

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin CCCD</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Hien source chi co cot so CCCD trong bang users.</p>
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
                        <p class="mt-1 text-sm font-semibold text-slate-500">Cac truong quan tri chi hien theo dung quyen hien co.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-black text-slate-800">Ma nhan su</label>
                            <input name="employee_code" value="{{ old('employee_code', $user->employee_code !== '----' ? $user->employee_code : '') }}" inputmode="numeric" maxlength="4" class="{{ $fieldClass }}" placeholder="Vi du 0001">
                            <p class="mt-1 text-xs font-bold text-slate-500">Ma phai khong trung trong cung chi nhanh.</p>
                            @error('employee_code') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">Ngay bat dau lam</label>
                            <input type="date" name="start_work_date" value="{{ old('start_work_date', $user->start_work_date?->format('Y-m-d')) }}" class="{{ $fieldClass }}" required>
                            @error('start_work_date') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">Ngay len chinh thuc</label>
                            <input type="date" name="official_at" value="{{ old('official_at', $user->official_at?->format('Y-m-d')) }}" class="{{ $fieldClass }}">
                            @error('official_at') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">Tinh trang lam viec</label>
                            <div class="mt-1 flex min-h-12 items-center rounded-2xl bg-slate-100 px-4 text-sm font-black text-slate-700">
                                {{ $employmentLabels[$user->employment_status] ?? $user->employment_status ?? 'Chua co' }}
                            </div>
                        </div>
                    </div>

                    @if ($currentRole === 'admin')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-black text-slate-800">Chức vụ (Position)</label>
                            <select name="position_id" class="{{ $selectClass }}">
                                <option value="">-- Chọn chức vụ --</option>
                                @php
                                    $groupedPositions = $positions->groupBy('system_role');
                                    $groupLabels = [
                                        'admin' => 'Ban Quản trị (Admin)',
                                        'manager' => 'Quản lý chi nhánh (Manager)',
                                        'cashier' => 'Thu ngân (Cashier)',
                                        'staff' => 'Nhân viên vận hành (Staff)',
                                    ];
                                @endphp
                                @foreach (['admin', 'manager', 'cashier', 'staff'] as $roleKey)
                                    @if (!empty($groupedPositions[$roleKey]) && $groupedPositions[$roleKey]->isNotEmpty())
                                        <optgroup label="{{ $groupLabels[$roleKey] }}">
                                            @foreach ($groupedPositions[$roleKey] as $pos)
                                                <option value="{{ $pos->id }}" @selected((string) old('position_id', $user->position_id) === (string) $pos->id)>
                                                    {{ $pos->name }} @if($pos->name_en)({{ $pos->name_en }})@endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            @error('position_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">Cơ sở / chi nhánh</label>
                            <select name="branch_id" x-model="branchId" class="{{ $selectClass }}" required>
                                @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('branch_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="flex items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <span>
                            <span class="block font-black text-slate-950">Trạng thái tài khoản</span>
                            <span class="text-sm font-semibold text-slate-500">Tắt để khóa đăng nhập của nhân sự này.</span>
                        </span>
                        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded border-slate-300 text-indigo-600" @checked(old('is_active', $user->is_active))>
                    </label>
                    @error('is_active') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror

                    <section x-show="branchId !== initialBranch" x-cloak class="space-y-3 rounded-3xl border border-indigo-200 bg-indigo-50 p-4">
                        <div>
                            <h3 class="font-black text-slate-950">Thông tin chuyển công tác</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Dùng để lưu lịch sử khi nhân sự chuyển sang cơ sở mới.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-black text-slate-800">Ngày hiệu lực</label>
                                <input type="date" name="branch_transfer_effective_date" value="{{ old('branch_transfer_effective_date', today()->toDateString()) }}" class="{{ $fieldClass }}">
                                @error('branch_transfer_effective_date') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-sm font-black text-slate-800">Ghi chú</label>
                                <textarea name="branch_transfer_note" rows="2" maxlength="500" class="{{ $fieldClass }} min-h-24" placeholder="Ví dụ: Điều động hỗ trợ cơ sở mới.">{{ old('branch_transfer_note') }}</textarea>
                                @error('branch_transfer_note') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                    @else
                    <div>
                        <label class="text-sm font-black text-slate-800">Chức vụ (Position)</label>
                        <select name="position_id" class="{{ $selectClass }}">
                            <option value="">-- Chọn chức vụ --</option>
                            @php
                                $groupedPositions = $positions->groupBy('system_role');
                                $groupLabels = [
                                    'cashier' => 'Thu ngân (Cashier)',
                                    'staff' => 'Nhân viên vận hành (Staff)',
                                ];
                            @endphp
                            @foreach (['cashier', 'staff'] as $roleKey)
                                @if (!empty($groupedPositions[$roleKey]) && $groupedPositions[$roleKey]->isNotEmpty())
                                    <optgroup label="{{ $groupLabels[$roleKey] }}">
                                        @foreach ($groupedPositions[$roleKey] as $pos)
                                            <option value="{{ $pos->id }}" @selected((string) old('position_id', $user->position_id) === (string) $pos->id)>
                                                {{ $pos->name }} @if($pos->name_en)({{ $pos->name_en }})@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                        @error('position_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-2xl bg-slate-100 p-4 text-sm font-bold text-slate-600">
                        Quản lý chỉ được cập nhật nhân sự trong phạm vi chi nhánh của mình.
                    </div>
                    @endif

                    @if ($currentRole === 'admin' || ! $autoPromoteEnabled)
                    <div>
                        <label class="text-sm font-black text-slate-800">Trang thai nhan su</label>
                        <select name="status" class="{{ $selectClass }}" required>
                            <option value="thu_viec" @selected(old('status', $user->status) === 'thu_viec')>Thu viec</option>
                            <option value="chinh_thuc" @selected(old('status', $user->status) === 'chinh_thuc')>Chinh thuc</option>
                            <option value="da_nghi" @selected(old('status', $user->status) === 'da_nghi')>Da nghi</option>
                        </select>
                        @error('status') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    @else
                    <input type="hidden" name="status" value="{{ $user->status }}">
                    <div class="rounded-2xl bg-indigo-50 p-4 text-sm font-bold text-indigo-700">
                        Trang thai duoc he thong tu dong quan ly.
                    </div>
                    @endif
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Nhan dien / QR danh gia</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Khong sua truc tiep face_descriptor hoac token QR.</p>
                    </div>

                    <div>
                        <label class="text-sm font-black text-slate-800">Kieu xac minh guong mat</label>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <label class="flex min-h-24 items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                <input type="radio" name="face_verification_mode" value="normal" class="mt-1 text-indigo-600" @checked(old('face_verification_mode', $user->face_verification_mode ?? 'normal') === 'normal')>
                                <span>
                                    <span class="block font-black text-slate-900">Binh thuong</span>
                                    <span class="mt-1 block font-semibold text-slate-500">Nhin thang, quay trai, quay phai.</span>
                                </span>
                            </label>

                            <label class="flex min-h-24 items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                <input type="radio" name="face_verification_mode" value="priority" class="mt-1 text-indigo-600" @checked(old('face_verification_mode', $user->face_verification_mode ?? 'normal') === 'priority')>
                                <span>
                                    <span class="block font-black text-slate-900">Uu tien</span>
                                    <span class="mt-1 block font-semibold text-slate-500">Giu mat trong khung 3 giay.</span>
                                </span>
                            </label>
                        </div>
                        @error('face_verification_mode') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Ma QR danh gia</div>
                            <div class="mt-2 text-lg font-black text-slate-950">{{ $user->public_rating_code ?: 'Chua co' }}</div>
                            <p class="mt-1 text-xs font-bold text-slate-500">Chi hien thi, khong sua truc tiep.</p>
                        </div>

                        @if ($currentRole === 'admin')
                        <label class="flex items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <span>
                                <span class="block font-black text-slate-950">Cho phep nhan danh gia QR</span>
                                <span class="text-sm font-semibold text-slate-500">Chi admin duoc thay doi cai dat nay.</span>
                            </span>
                            <input type="checkbox" name="rating_qr_enabled" value="1" class="h-5 w-5 rounded border-slate-300 text-indigo-600" @checked(old('rating_qr_enabled', $user->rating_qr_enabled))>
                        </label>
                        @endif
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Moc trang thai</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Thong tin audit cua qua trinh vao lam, chinh thuc va nghi viec.</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Ngay vao lam</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->hired_at?->format('d/m/Y') ?? '-' }}</div>
                            <div class="mt-1 text-xs font-bold text-slate-500">Nguoi chuyen: {{ $user->hiredBy?->name ?? '-' }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Chinh thuc</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->official_at?->format('d/m/Y') ?? '-' }}</div>
                            <div class="mt-1 text-xs font-bold text-slate-500">Nguoi chuyen: {{ $user->officialBy?->name ?? '-' }}</div>
                        </div>

                        <div class="rounded-3xl bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-[0.15em] text-slate-500">Da nghi</div>
                            <div class="mt-2 font-black text-slate-950">{{ $user->resigned_at?->format('d/m/Y') ?? '-' }}</div>
                            <div class="mt-1 text-xs font-bold text-slate-500">Nguoi chuyen: {{ $user->resignedBy?->name ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <button class="min-h-14 rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-4 text-sm font-black text-white shadow-lg">
                        Cap nhat nhan su
                    </button>

                    <a href="{{ route('users.index') }}" class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-700 shadow">
                        Quay lai danh sach
                    </a>
                </div>
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
                                        data-crop-action="zoom-out" aria-label="Thu nhỏ"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">−</span>
                                        <span class="hidden sm:inline">Thu nhỏ</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="zoom-in" aria-label="Phóng to"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">＋</span>
                                        <span class="hidden sm:inline">Phóng to</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="rotate-left" aria-label="Xoay trái"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">↶</span>
                                        <span class="hidden sm:inline">Xoay trái</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="rotate-right" aria-label="Xoay phải"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">↷</span>
                                        <span class="hidden sm:inline">Xoay phải</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="reset" aria-label="Đặt lại"
                                        class="grid min-h-11 place-items-center rounded-2xl bg-slate-100 px-2 text-sm font-black text-slate-700 sm:block sm:px-3">
                                        <span class="text-xl leading-none sm:hidden" aria-hidden="true">⟳</span>
                                        <span class="hidden sm:inline">Đặt lại</span>
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="reselect" aria-label="Chọn lại ảnh"
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
        </div>
    </div>
</x-app-layout>