<x-app-layout>
    @php
        $currentUser = auth()->user();
        $fieldClass = 'mt-1 w-full min-h-12 rounded-2xl border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-indigo-200';
        $selectClass = $fieldClass;
        $cardClass = 'rounded-[1.75rem] border border-white/80 bg-white/95 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)]';
    @endphp

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-indigo-50/40 to-white px-3 py-4 sm:px-5 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <div class="flex flex-col gap-3 rounded-[2rem] bg-slate-950 p-5 text-white shadow-[0_20px_55px_rgba(15,23,42,0.22)] sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-200">Quan ly nhan su</p>
                    <h1 class="mt-2 text-2xl font-black sm:text-3xl">Them nhan su</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-300">
                        Tao tai khoan moi voi thong tin co ban, cong viec va che do nhan dien phu hop.
                    </p>
                </div>

                <a href="{{ route('users.index') }}"
                   class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white/10 px-4 text-sm font-black text-white ring-1 ring-white/20">
                    Quay lai danh sach
                </a>
            </div>

            @if ($errors->any())
                <div class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf

                <section class="{{ $cardClass }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="grid h-24 w-24 shrink-0 place-items-center rounded-[1.5rem] bg-gradient-to-br from-indigo-500 to-sky-500 text-3xl font-black text-white shadow-lg">
                            ?
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-black text-slate-950">Anh dai dien</h2>
                            <p class="mt-1 text-sm font-semibold leading-6 text-slate-500">
                                Anh nay dung cho nhan dien va hien thi trong he thong. Khi tao moi, hay cap nhat anh o man hinh sua nhan su sau khi tai khoan duoc tao.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin co ban</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Thong tin de lien he va dang nhap trong he thong.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-black text-slate-800">Ho ten</label>
                            <input name="name" value="{{ old('name') }}" class="{{ $fieldClass }}" required>
                            @error('name') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">So dien thoai</label>
                            <input name="phone" value="{{ old('phone') }}" inputmode="tel" class="{{ $fieldClass }}">
                            @error('phone') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-black text-slate-800">Email</label>
                            <input name="email" type="email" value="{{ old('email') }}" class="{{ $fieldClass }}" required>
                            @error('email') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin CCCD</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Database hien tai chi co cot so CCCD, nen chi hien thi truong co the luu an toan.</p>
                    </div>

                    <div>
                        <label class="text-sm font-black text-slate-800">So CCCD</label>
                        <input name="citizen_id" value="{{ old('citizen_id') }}" inputmode="numeric" class="{{ $fieldClass }}">
                        @error('citizen_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Thong tin cong viec</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Chi admin/manager duoc tao nhan su theo pham vi quyen hien co.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-black text-slate-800">Ma nhan su</label>
                            <input name="employee_code" value="{{ old('employee_code') }}" inputmode="numeric" maxlength="4" class="{{ $fieldClass }}" placeholder="De trong de tu sinh">
                            <p class="mt-1 text-xs font-bold text-slate-500">Ma 4 chu so va khong trung trong cung chi nhanh.</p>
                            @error('employee_code') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-black text-slate-800">Ngay bat dau lam</label>
                            <input type="date" name="start_work_date" value="{{ old('start_work_date', now()->toDateString()) }}" class="{{ $fieldClass }}" required>
                            @error('start_work_date') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($autoPromoteEnabled)
                            <input type="hidden" name="status" value="thu_viec">
                            <div class="rounded-2xl bg-indigo-50 p-4 text-sm font-bold text-indigo-700 md:col-span-2">
                                Trang thai duoc he thong tu dong quan ly. Nhan su moi bat dau o trang thai thu viec.
                            </div>
                        @else
                            <div>
                                <label class="text-sm font-black text-slate-800">Trang thai nhan su</label>
                                <select name="status" class="{{ $selectClass }}" required>
                                    <option value="thu_viec" @selected(old('status', 'thu_viec') === 'thu_viec')>Thu viec</option>
                                    <option value="chinh_thuc" @selected(old('status') === 'chinh_thuc')>Chinh thuc</option>
                                    <option value="da_nghi" @selected(old('status') === 'da_nghi')>Da nghi</option>
                                </select>
                                @error('status') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($currentUser->role === 'admin')
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
                                                    <option value="{{ $pos->id }}" @selected((string) old('position_id') === (string) $pos->id)>
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
                                <select name="branch_id" class="{{ $selectClass }}" required>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <input type="hidden" name="branch_id" value="{{ $currentUser->branch_id }}">

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
                                                    <option value="{{ $pos->id }}" @selected((string) old('position_id') === (string) $pos->id)>
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
                                Quản lý chỉ được tạo nhân sự thuộc phạm vi chi nhánh của mình.
                            </div>
                        @endif
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Nhan dien / QR danh gia</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Thiet lap che do xac minh khuon mat. QR danh gia se dung cau hinh mac dinh cua he thong.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex min-h-24 items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <input type="radio" name="face_verification_mode" value="normal" class="mt-1 text-indigo-600" @checked(old('face_verification_mode', 'normal') === 'normal')>
                            <span>
                                <span class="block font-black text-slate-900">Binh thuong</span>
                                <span class="mt-1 block font-semibold text-slate-500">Nhin thang, quay trai, quay phai khi dang ky.</span>
                            </span>
                        </label>

                        <label class="flex min-h-24 items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <input type="radio" name="face_verification_mode" value="priority" class="mt-1 text-indigo-600" @checked(old('face_verification_mode') === 'priority')>
                            <span>
                                <span class="block font-black text-slate-900">Uu tien</span>
                                <span class="mt-1 block font-semibold text-slate-500">Giu mat trong khung 3 giay, khong can quay co.</span>
                            </span>
                        </label>
                    </div>
                    @error('face_verification_mode') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Mat khau dang nhap</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Dung de nhan su dang nhap lan dau. Toi thieu 6 ky tu theo rule hien tai.</p>
                    </div>

                    <div>
                        <label class="text-sm font-black text-slate-800">Mat khau</label>
                        <input name="password" type="password" class="{{ $fieldClass }}" required>
                        @error('password') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <button class="min-h-14 rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-4 text-sm font-black text-white shadow-lg">
                        Luu thong tin
                    </button>

                    <a href="{{ route('users.index') }}" class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-700 shadow">
                        Quay lai danh sach
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
