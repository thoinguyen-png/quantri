<x-app-layout>
    @php
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month);
        $oldUserIds = array_map('strval', (array) old('user_ids', []));
        $oldWorkDates = array_values((array) old('work_dates', []));
        $result = session('quick_attendance_result');
    @endphp

    <div
        class="shift-planner"
        x-data="{
            search: '',
            selectedUsers: @js($oldUserIds),
            selectedDates: @js($oldWorkDates),
            activeUserId: @js($oldUserIds[0] ?? null),
            activeUserName: '',
            toggleUser(id, name, checked) {
                id = String(id);

                if (checked) {
                    if (!this.selectedUsers.includes(id)) {
                        this.selectedUsers = [...this.selectedUsers, id];
                    }

                    this.activeUserId = id;
                    this.activeUserName = name;
                    return;
                }

                this.selectedUsers = this.selectedUsers.filter((item) => item !== id);

                if (this.activeUserId === id) {
                    this.activeUserId = this.selectedUsers[this.selectedUsers.length - 1] || null;
                    this.activeUserName = '';
                }
            },
            toggleDate(date) {
                this.selectedDates = this.selectedDates.includes(date)
                    ? this.selectedDates.filter((item) => item !== date)
                    : [...this.selectedDates, date];
            },
            prepareSubmit(event) {
                const form = event.target;
                const appendHidden = function (name, value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    input.dataset.generatedQuickAttendanceInput = '1';
                    form.appendChild(input);
                };

                form.querySelectorAll('[data-generated-quick-attendance-input]').forEach(function (input) {
                    input.remove();
                });

                this.selectedUsers.forEach(function (userId) {
                    appendHidden('user_ids[]', userId);
                });

                this.selectedDates.forEach(function (workDate) {
                    appendHidden('work_dates[]', workDate);
                });
            }
        }"
    >
        <form id="quick-attendance-form" method="POST" action="{{ route('quick-attendance.store') }}" x-on:submit="prepareSubmit($event)">
            @csrf
        </form>

        <div class="shift-planner__header">
            <div>
                <span class="shift-planner__eyebrow">Quản lý công</span>
                <h1>Cập nhật công nhanh</h1>
                <p>Chọn nhân viên và ngày giống thao tác gán ca. Hệ thống chỉ cập nhật đủ công nếu ngày đó đã có ca được gán, không nhập giờ thủ công và không tính tăng ca.</p>
            </div>

            <form method="GET" action="{{ route('quick-attendance.index') }}" class="shift-planner__month-form" data-shift-month-form>
                <input type="month" name="month" value="{{ $month }}" data-shift-month-input aria-label="Chọn tháng">
                <span class="shift-planner__month-status" data-shift-month-status></span>
            </form>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-700">
                {{ session('warning') }}
            </div>
        @endif

        @if ($result)
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition
                class="fixed right-4 top-20 z-50 max-w-sm rounded-2xl border p-4 text-sm font-black shadow-lg {{ ($result['fail_count'] ?? 0) > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}"
            >
                {{ ($result['fail_count'] ?? 0) > 0 ? 'Có ' . $result['fail_count'] . ' trường hợp không thể cập nhật' : 'Cập nhật công thành công' }}
            </div>
        @endif

        @if ($result)
            <section class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">
                        <div class="text-xs font-black uppercase tracking-wide">Thành công</div>
                        <div class="mt-1 text-3xl font-black">{{ $result['success_count'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
                        <div class="text-xs font-black uppercase tracking-wide">Thất bại</div>
                        <div class="mt-1 text-3xl font-black">{{ $result['fail_count'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700">
                    @if (($result['fail_count'] ?? 0) === 0)
                        Đã cập nhật công thành công cho {{ $result['success_count'] ?? 0 }} nhân viên.
                    @else
                        Đã cập nhật thành công {{ $result['success_count'] ?? 0 }}/{{ $result['total_count'] ?? 0 }} trường hợp.
                        {{ $result['fail_count'] ?? 0 }} trường hợp chưa được xử lý.
                    @endif
                </div>

                @if (!empty($result['success_items']))
                    <div class="mt-4">
                        <h2 class="text-sm font-black text-emerald-700">Đã cập nhật đủ công thành công</h2>
                        <div class="mt-3 overflow-hidden rounded-2xl border border-emerald-100">
                            <div class="grid grid-cols-2 gap-2 bg-emerald-50 px-4 py-3 text-xs font-black uppercase tracking-wide text-emerald-700 sm:grid-cols-5">
                                <span>Nhân viên</span>
                                <span>Ngày công</span>
                                <span class="hidden sm:block">Ca làm</span>
                                <span class="hidden sm:block">Công</span>
                                <span class="hidden sm:block">Tăng ca</span>
                            </div>
                            @foreach ($result['success_items'] as $item)
                                <div class="grid grid-cols-2 gap-2 border-t border-emerald-100 px-4 py-3 text-sm text-slate-700 sm:grid-cols-5">
                                    <span class="font-bold">{{ $item['employee'] }}</span>
                                    <span>{{ $item['date'] }}</span>
                                    <span class="hidden sm:block">{{ $item['shift'] }}</span>
                                    <span class="hidden sm:block">{{ $item['work_day'] }} công</span>
                                    <span class="hidden sm:block">{{ $item['overtime_hours'] }}h</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($result['failed_items']))
                    <div class="mt-4">
                        <h2 class="text-sm font-black text-red-700">Chi tiết thất bại</h2>
                        <div class="mt-3 overflow-hidden rounded-2xl border border-red-100">
                            <div class="grid grid-cols-1 gap-2 bg-red-50 px-4 py-3 text-xs font-black uppercase tracking-wide text-red-700 sm:grid-cols-3">
                                <span>Nhân viên</span>
                                <span>Ngày</span>
                                <span>Lý do</span>
                            </div>
                            @foreach ($result['failed_items'] as $item)
                                <div class="grid grid-cols-1 gap-1 border-t border-red-100 px-4 py-3 text-sm text-slate-700 sm:grid-cols-3 sm:gap-2">
                                    <span class="font-bold">{{ $item['employee'] }}</span>
                                    <span>{{ $item['date'] }}</span>
                                    <span class="text-red-700">{{ $item['reason'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-700">
            Ngày đã có dữ liệu chấm công sẽ được cập nhật lại thành đủ công theo ca đã gán và xóa tăng ca về 0h.
        </div>

        <div class="shift-planner__layout">
            <aside class="shift-planner__sidebar">
                <div class="shift-planner__panel">
                    <label class="shift-planner__label" for="employee-search">Tìm nhân viên</label>
                    <input
                        id="employee-search"
                        type="search"
                        x-model="search"
                        placeholder="Nhập tên, email hoặc số điện thoại..."
                        class="shift-planner__search"
                    >

                    <div class="shift-planner__employee-list">
                        @forelse ($users as $user)
                            <label
                                class="shift-planner__employee"
                                x-bind:class="activeUserId === '{{ $user->id }}' ? 'is-viewing' : ''"
                                x-show="@js(strtolower($user->name . ' ' . $user->email . ' ' . $user->phone)).includes(search.toLowerCase())"
                            >
                                <input
                                    type="checkbox"
                                    value="{{ $user->id }}"
                                    x-bind:checked="selectedUsers.includes('{{ $user->id }}')"
                                    x-on:change="toggleUser('{{ $user->id }}', @js($user->name), $event.target.checked)"
                                    aria-label="Chọn {{ $user->name }}"
                                >
                                <span>
                                    <strong>{{ $user->name }}</strong>
                                    <small>{{ $user->branch?->name ?? 'Chưa có chi nhánh' }} @if($user->position) · {{ $user->position->name }}@elseif($user->role === 'manager') · Quản lý@endif</small>
                                </span>
                            </label>
                        @empty
                            <div class="shift-planner__empty">Chưa có nhân viên để cập nhật công.</div>
                        @endforelse
                    </div>
                </div>

                <div class="shift-planner__panel">
                    <label class="shift-planner__label" for="quick-reason">Lý do cập nhật công</label>
                    <textarea
                        id="quick-reason"
                        name="reason"
                        form="quick-attendance-form"
                        maxlength="255"
                        rows="4"
                        class="shift-planner__search min-h-28 resize-none"
                        placeholder="Ví dụ: Quản lý xác nhận nhân viên làm đủ ca"
                    >{{ old('reason') }}</textarea>

                    <div class="shift-planner__summary">
                        <span><b x-text="selectedUsers.length">0</b> nhân viên</span>
                        <span><b x-text="selectedDates.length">0</b> ngày</span>
                    </div>

                    <button
                        type="submit"
                        form="quick-attendance-form"
                        class="shift-planner__submit"
                        onclick="return confirm('Hệ thống sẽ cập nhật các ngày đã chọn thành đủ công theo ca đã gán và đặt tăng ca về 0h. Bạn chắc chắn muốn tiếp tục?')"
                    >
                        Cập nhật đủ công
                    </button>
                </div>
            </aside>

            <section class="shift-planner__calendar">
                <div class="shift-planner__calendar-head">
                    <div>
                        <h2>Tháng {{ $monthDate->format('m/Y') }}</h2>
                        <p>Click một hoặc nhiều ngày cần cập nhật. Nếu nhân viên chưa được gán ca trong ngày đã chọn, hệ thống sẽ bỏ qua và báo rõ sau khi lưu.</p>
                    </div>
                    <span class="shift-planner__active-user" x-show="activeUserName" x-text="'Đang chọn: ' + activeUserName"></span>
                </div>

                <div class="shift-planner__weekdays">
                    <span>Thứ 2</span>
                    <span>Thứ 3</span>
                    <span>Thứ 4</span>
                    <span>Thứ 5</span>
                    <span>Thứ 6</span>
                    <span class="is-red">Thứ 7</span>
                    <span class="is-red">CN</span>
                </div>

                <div class="shift-planner__grid">
                    @foreach ($weeks as $week)
                        @foreach ($week as $day)
                            @php
                                $date = $day->toDateString();
                                $isOutsideMonth = $day->format('Y-m') !== $month;
                                $isWeekend = in_array($day->dayOfWeekIso, [6, 7], true);
                            @endphp

                            <div
                                class="shift-day {{ $isOutsideMonth ? 'is-muted' : '' }} {{ $isWeekend ? 'is-red' : '' }}"
                                x-bind:class="{ 'is-selected': selectedDates.includes('{{ $date }}') }"
                                x-on:click="if (!@js($isOutsideMonth) && !$event.target.closest('button, select, input, form')) toggleDate('{{ $date }}')"
                                @if ($isOutsideMonth) aria-disabled="true" @endif
                            >
                                <input
                                    type="checkbox"
                                    value="{{ $date }}"
                                    x-bind:checked="selectedDates.includes('{{ $date }}')"
                                    x-on:change="toggleDate('{{ $date }}')"
                                    {{ $isOutsideMonth ? 'disabled' : '' }}
                                >

                                <span class="shift-day__top">
                                    <strong>{{ $day->format('d') }}</strong>
                                    @if ($isOutsideMonth)
                                        <em>Khác tháng</em>
                                    @elseif ($isWeekend)
                                        <em>Nghỉ</em>
                                    @endif
                                </span>

                                @if ($isOutsideMonth)
                                    <span class="shift-day__empty">Không áp dụng</span>
                                @else
                                    <span class="shift-day__assigned">Chọn cập nhật</span>
                                    <span class="shift-day__empty">Dùng ca đã gán</span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <script>
        window.initShiftMonthAjax = window.initShiftMonthAjax || function () {
            document.addEventListener('change', async function (event) {
                const input = event.target.closest('[data-shift-month-input]');

                if (!input) {
                    return;
                }

                const form = input.closest('[data-shift-month-form]');
                const status = form.querySelector('[data-shift-month-status]');
                const url = new URL(form.action, window.location.origin);
                url.searchParams.set('month', input.value);

                if (status) {
                    status.textContent = 'Đang tải...';
                }

                try {
                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const current = document.querySelector('.shift-planner');
                    const next = doc.querySelector('.shift-planner');

                    if (!response.ok || !current || !next) {
                        throw new Error('Không thể tải dữ liệu tháng.');
                    }

                    current.replaceWith(next);
                    window.history.replaceState({}, '', url.toString());

                    if (window.Alpine) {
                        window.Alpine.initTree(next);
                    }
                } catch (error) {
                    if (status) {
                        status.textContent = 'Không tải được';
                    }
                }
            });
        };

        window.initShiftMonthAjax();
    </script>
</x-app-layout>
