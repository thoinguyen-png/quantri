<x-app-layout>
    @php
        $fieldClass = 'mt-1 w-full min-h-12 rounded-2xl border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-indigo-200';
        $cardClass = 'rounded-[1.75rem] border border-white/80 bg-white/95 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)]';
    @endphp

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-indigo-50/40 to-white px-3 py-4 sm:px-5 lg:px-8">
        <div class="mx-auto max-w-4xl space-y-5">
            <header class="flex flex-col gap-3 rounded-[2rem] bg-slate-950 p-5 text-white shadow-[0_20px_55px_rgba(15,23,42,0.22)] sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-200">
                        Quản trị nghỉ phép
                    </p>

                    <h1 class="mt-2 text-2xl font-black sm:text-3xl">
                        Bổ sung phép cho nhân sự
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-300">
                        Admin tạo phép trực tiếp cho nhân sự. Bản ghi sẽ được duyệt ngay sau khi lưu.
                    </p>
                </div>

                <a
                    href="{{ route('leave-requests.index') }}"
                    class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-black text-slate-950">
                    Quay lại
                </a>
            </header>

            @if ($errors->any())
                <div class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('leave-requests.admin-store') }}"
                class="space-y-5"
                data-admin-leave-form>
                @csrf

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">
                            Nhân sự
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            Chọn đúng nhân sự cần bổ sung phép.
                        </p>
                    </div>

                    <div>
                        <label for="employee_search" class="text-sm font-black text-slate-800">
                            Tìm nhanh nhân sự
                        </label>
                        <input
                            id="employee_search"
                            type="search"
                            autocomplete="off"
                            class="{{ $fieldClass }}"
                            placeholder="Nhập tên, mã nhân sự hoặc chi nhánh..."
                            data-user-search>
                    </div>

                    <div>
                        <label for="user_id" class="text-sm font-black text-slate-800">
                            Nhân sự
                        </label>

                        <select
                            id="user_id"
                            name="user_id"
                            class="{{ $fieldClass }}"
                            required
                            data-user-select>
                            <option value="">-- Chọn nhân sự --</option>

                            @foreach ($users as $employee)
                                <option
                                    value="{{ $employee->id }}"
                                    data-search="{{ mb_strtolower(trim(($employee->name ?? '').' '.($employee->employee_code ?? '').' '.($employee->branch?->name ?? '').' '.($employee->phone ?? '').' '.($employee->email ?? ''))) }}"
                                    @selected((string) old('user_id') === (string) $employee->id)>
                                    {{ $employee->name }}
                                    @if ($employee->employee_code)
                                        · {{ $employee->employee_code }}
                                    @endif
                                    @if ($employee->branch)
                                        · {{ $employee->branch->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        <p class="mt-2 text-xs font-bold text-slate-500" data-user-search-count>
                            {{ $users->count() }} nhân sự khả dụng.
                        </p>

                        @error('user_id')
                            <p class="mt-1 text-xs font-bold text-rose-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">
                            Thời gian nghỉ
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            Admin có thể bổ sung cả ngày trong quá khứ.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="start_date" class="text-sm font-black text-slate-800">
                                Từ ngày
                            </label>
                            <input
                                id="start_date"
                                type="date"
                                name="start_date"
                                value="{{ old('start_date') }}"
                                class="{{ $fieldClass }}"
                                required
                                data-start-date>

                            @error('start_date')
                                <p class="mt-1 text-xs font-bold text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label for="end_date" class="text-sm font-black text-slate-800">
                                Đến ngày
                            </label>
                            <input
                                id="end_date"
                                type="date"
                                name="end_date"
                                value="{{ old('end_date') }}"
                                class="{{ $fieldClass }}"
                                required
                                data-end-date>

                            @error('end_date')
                                <p class="mt-1 text-xs font-bold text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-3xl border border-indigo-100 bg-indigo-50 p-4">
                        <div class="text-xs font-black uppercase tracking-[0.15em] text-indigo-500">
                            Tổng số ngày
                        </div>
                        <div class="mt-1 text-2xl font-black text-indigo-700" data-total-days>
                            --
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }} space-y-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">
                            Lý do bổ sung
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            Ghi ngắn gọn để sau này dễ kiểm tra lịch sử.
                        </p>
                    </div>

                    <div>
                        <label for="reason" class="text-sm font-black text-slate-800">
                            Lý do
                        </label>
                        <textarea
                            id="reason"
                            name="reason"
                            rows="4"
                            maxlength="2000"
                            class="{{ $fieldClass }} min-h-32 py-3"
                            placeholder="Ví dụ: Bổ sung phép theo xác nhận của quản lý."
                            required>{{ old('reason') }}</textarea>

                        @error('reason')
                            <p class="mt-1 text-xs font-bold text-rose-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">
                        Sau khi bấm lưu, phép sẽ được ghi nhận ở trạng thái <strong>Đã duyệt</strong> ngay.
                    </div>
                </section>

                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <button
                        type="submit"
                        class="min-h-14 rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-4 text-sm font-black text-white shadow-lg">
                        Bổ sung phép
                    </button>

                    <a
                        href="{{ route('leave-requests.index') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-700 shadow">
                        Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-admin-leave-form]');

            if (!form) {
                return;
            }

            const searchInput = form.querySelector('[data-user-search]');
            const userSelect = form.querySelector('[data-user-select]');
            const searchCount = form.querySelector('[data-user-search-count]');
            const startInput = form.querySelector('[data-start-date]');
            const endInput = form.querySelector('[data-end-date]');
            const totalDays = form.querySelector('[data-total-days]');

            function normalize(value) {
                return (value || '')
                    .toLocaleLowerCase('vi-VN')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            }

            function filterUsers() {
                if (!searchInput || !userSelect) {
                    return;
                }

                const keyword = normalize(searchInput.value.trim());
                let visible = 0;

                Array.from(userSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        return;
                    }

                    const haystack = normalize(option.dataset.search || option.textContent);
                    const match = keyword === '' || haystack.includes(keyword);

                    option.hidden = !match;

                    if (match) {
                        visible += 1;
                    }
                });

                if (searchCount) {
                    searchCount.textContent = visible + ' nhân sự phù hợp.';
                }

                const selected = userSelect.selectedOptions[0];

                if (selected && selected.hidden) {
                    userSelect.value = '';
                }
            }

            function updateTotalDays() {
                if (!startInput || !endInput || !totalDays) {
                    return;
                }

                if (!startInput.value || !endInput.value) {
                    totalDays.textContent = '--';
                    return;
                }

                const start = new Date(startInput.value + 'T00:00:00');
                const end = new Date(endInput.value + 'T00:00:00');

                if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
                    totalDays.textContent = '--';
                    return;
                }

                const days = Math.floor((end - start) / 86400000) + 1;
                totalDays.textContent = days + ' ngày';
            }

            searchInput?.addEventListener('input', filterUsers);
            startInput?.addEventListener('change', updateTotalDays);
            endInput?.addEventListener('change', updateTotalDays);

            filterUsers();
            updateTotalDays();
        })();
    </script>
</x-app-layout>