<x-app-layout>
    @php
        $assignmentMap = $assignments->groupBy(fn ($assignment) => $assignment->work_date->toDateString());
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month);
    @endphp

    <div
        class="shift-planner"
        x-data="{
            search: '',
            selectedUsers: [],
            selectedDates: [],
            branchShiftMap: @js($branchShiftMap),
            currentBranchId: @js(in_array(auth()->user()->role, ['manager', 'cashier'], true) ? auth()->user()->branch_id : null),
            showAllShifts: @js($showAllShifts),
            userBranchMap: @js($users->mapWithKeys(fn ($user) => [$user->id => $user->branch_id])),
            activeUserId: null,
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
            selectedBranchIds() {
                const branches = this.selectedUsers
                    .map((userId) => this.userBranchMap[userId])
                    .filter((branchId) => branchId !== null && branchId !== undefined)
                    .map(String);

                return [...new Set(branches)];
            },
            isShiftVisible(shiftId) {
                if (this.showAllShifts) {
                    return true;
                }

                const branches = this.selectedBranchIds();
                const scopedBranches = branches.length ? branches : (this.currentBranchId ? [String(this.currentBranchId)] : []);

                if (!scopedBranches.length) {
                    return true;
                }

                return scopedBranches.every((branchId) => (this.branchShiftMap[branchId] || []).map(String).includes(String(shiftId)));
            },
            hasAllowedShift() {
                if (this.showAllShifts) {
                    return true;
                }

                const branches = this.selectedBranchIds();
                const scopedBranches = branches.length ? branches : (this.currentBranchId ? [String(this.currentBranchId)] : []);

                return !scopedBranches.length || scopedBranches.some((branchId) => (this.branchShiftMap[branchId] || []).length > 0);
            },
            prepareSubmit(event) {
                const form = event.target;
                const shiftSelect = document.getElementById('shift_id');
                const appendHidden = function (name, value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    input.dataset.generatedShiftInputState = '1';
                    form.appendChild(input);
                };

                form.querySelectorAll('[data-generated-shift-input-state]').forEach(function (input) {
                    input.remove();
                });

                this.selectedUsers.forEach(function (userId) {
                    appendHidden('user_ids[]', userId);
                });

                this.selectedDates.forEach(function (workDate) {
                    appendHidden('work_dates[]', workDate);
                });

                if (shiftSelect && shiftSelect.value) {
                    appendHidden('shift_id', shiftSelect.value);
                }
            }
        }"
    >
        <form id="shift-batch-form" method="POST" action="{{ route('shift-assignments.store') }}" x-on:submit="prepareSubmit($event)">
            @csrf
        </form>

        <div class="shift-planner__header">
            <div>
                <span class="shift-planner__eyebrow">Quản lý ca làm</span>
                <h1>Gán và cập nhật ca hàng loạt</h1>
                <p>Tick nhân viên, click các ngày cần đổi hoặc gán, chọn ca rồi lưu. Ngày đã có ca sẽ được cập nhật sang ca mới.</p>
            </div>

            <form method="GET" action="{{ route('shift-assignments.index') }}" class="shift-planner__month-form" data-shift-month-form>
                <input type="month" name="month" value="{{ $month }}" data-shift-month-input aria-label="Chọn tháng">
                <span class="shift-planner__month-status" data-shift-month-status></span>
            </form>
        </div>

        @if ($errors->any())
            <div class="shift-planner__alert shift-planner__alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('warning'))
            <div class="shift-planner__alert shift-planner__alert--warning">
                <div>{{ session('warning') }}</div>
                @if (in_array(auth()->user()->role, ['admin', 'manager'], true) && old('shift_id') && old('user_ids') && old('work_dates'))
                    <form
                        method="POST"
                        action="{{ route('shift-assignments.recalculate') }}"
                        class="mt-3"
                        onsubmit="return confirm('Bạn chắc chắn muốn đổi ca và tính lại công theo ca mới? Dữ liệu checkin, checkout, GPS và khuôn mặt sẽ được giữ nguyên.')"
                    >
                        @csrf
                        <input type="hidden" name="shift_id" value="{{ old('shift_id') }}">
                        @foreach ((array) old('user_ids', []) as $userId)
                            <input type="hidden" name="user_ids[]" value="{{ $userId }}">
                        @endforeach
                        @foreach ((array) old('work_dates', []) as $workDate)
                            <input type="hidden" name="work_dates[]" value="{{ $workDate }}">
                        @endforeach
                        <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600">
                            Đổi ca và tính lại công
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <div class="shift-planner__layout">
            <aside class="shift-planner__sidebar">
                <div class="shift-planner__panel">
                    <label class="shift-planner__label" for="employee-search">Tìm nhân viên</label>
                    <input
                        id="employee-search"
                        type="search"
                        x-model="search"
                        placeholder="Nhập tên hoặc email..."
                        class="shift-planner__search"
                    >

                    <div class="shift-planner__employee-list">
                        @forelse ($users as $user)
                            <label
                                class="shift-planner__employee"
                                x-bind:class="activeUserId === '{{ $user->id }}' ? 'is-viewing' : ''"
                                x-show="@js(strtolower($user->name . ' ' . $user->email)).includes(search.toLowerCase())"
                            >
                                <input
                                    form="shift-batch-form"
                                    type="checkbox"
                                    name="user_ids[]"
                                    value="{{ $user->id }}"
                                    data-shift-user-checkbox
                                    x-bind:checked="selectedUsers.includes('{{ $user->id }}')"
                                    x-on:change="toggleUser('{{ $user->id }}', @js($user->name), $event.target.checked)"
                                    aria-label="Chọn {{ $user->name }}"
                                >
                                <span>
                                    <strong>{{ $user->name }}</strong>
                                    <small>{{ $user->branch?->name ?? 'Chưa có chi nhánh' }}</small>
                                </span>
                            </label>
                        @empty
                            <div class="shift-planner__empty">Chưa có nhân viên để gán ca.</div>
                        @endforelse
                    </div>
                </div>

                <div class="shift-planner__panel">
                    <label class="shift-planner__label" for="shift_id">Ca áp dụng</label>
                    <select
                        id="shift_id"
                        name="shift_id"
                        class="shift-planner__select"
                        form="shift-batch-form"
                        required
                    >
                        <option value="">Chọn ca làm</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" x-show="isShiftVisible('{{ $shift->id }}')" x-bind:disabled="!isShiftVisible('{{ $shift->id }}')">
                                {{ $shift->name }} · {{ $shift->start_at->format('H:i') }} - {{ $shift->end_at->format('H:i') }}
                            </option>
                        @endforeach
                    </select>

                    <p x-show="!showAllShifts && !hasAllowedShift()" x-cloak class="mt-2 text-sm font-semibold text-amber-700">
                        Chi nhánh này chưa được admin cấu hình ca được phép.
                    </p>
                    <p x-show="!showAllShifts && selectedUsers.length > 1 && selectedBranchIds().length > 1" x-cloak class="mt-2 text-sm text-slate-500">
                        Chỉ hiển thị các ca được phép tại tất cả chi nhánh của nhân sự đã chọn.
                    </p>

                    <div class="shift-planner__summary">
                        <span><b x-text="selectedUsers.length">0</b> nhân viên</span>
                        <span><b x-text="selectedDates.length">0</b> ngày</span>
                    </div>

                    <button type="submit" form="shift-batch-form" class="shift-planner__submit">
                        Lưu phân ca
                    </button>
                </div>
            </aside>

            <section class="shift-planner__calendar">
                <div class="shift-planner__calendar-head">
                    <div>
                        <h2>Tháng {{ $monthDate->format('m/Y') }}</h2>
                        <p>Click nhiều ngày để chọn. Các ngày đã có ca của nhân viên đang tick sẽ hiện tên ca để bạn biết đang cập nhật ngày nào.</p>
                    </div>
                    <span class="shift-planner__active-user" x-show="activeUserName" x-text="'Đang xem: ' + activeUserName"></span>
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
                                $isHoliday = in_array($date, $holidayDates, true);
                                $isWeekend = in_array($day->dayOfWeekIso, [6, 7], true);
                                $dayAssignments = $assignmentMap->get($date, collect());
                                $assignedUserIds = $dayAssignments->pluck('user_id')->map(fn ($id) => (string) $id)->values();
                            @endphp

                            <div
                                class="shift-day {{ $isOutsideMonth ? 'is-muted' : '' }} {{ ($isWeekend || $isHoliday) ? 'is-red' : '' }}"
                                x-bind:class="{
                                    'is-selected': selectedDates.includes('{{ $date }}'),
                                    'has-active-user-shift': activeUserId && @js($assignedUserIds).includes(activeUserId)
                                }"
                                x-on:click="if (!@js($isOutsideMonth) && !$event.target.closest('button, select, input, form')) toggleDate('{{ $date }}')"
                                @if ($isOutsideMonth) aria-disabled="true" @endif
                            >
                                <input
                                    form="shift-batch-form"
                                    type="checkbox"
                                    name="work_dates[]"
                                    value="{{ $date }}"
                                    data-shift-date-checkbox
                                    x-bind:checked="selectedDates.includes('{{ $date }}')"
                                    x-on:change="toggleDate('{{ $date }}')"
                                    {{ $isOutsideMonth ? 'disabled' : '' }}
                                >

                                <span class="shift-day__top">
                                    <strong>{{ $day->format('d') }}</strong>
                                    @if ($isOutsideMonth)
                                        <em>Khác tháng</em>
                                    @elseif ($isHoliday)
                                        <em>Lễ</em>
                                    @elseif ($isWeekend)
                                        <em>Nghỉ</em>
                                    @endif
                                </span>

                                @if ($isOutsideMonth)
                                    <span class="shift-day__empty">Không áp dụng</span>
                                @elseif ($dayAssignments->isNotEmpty())
                                    <span class="shift-day__assigned">
                                        {{ $dayAssignments->count() }} đã gán
                                    </span>
                                    <span class="shift-day__shifts">
                                        {{ $dayAssignments->pluck('shift.name')->filter()->unique()->take(2)->join(', ') }}
                                    </span>
                                @else
                                    <span class="shift-day__empty">Chưa gán</span>
                                @endif

                                @if (!$isOutsideMonth)
                                    <div class="shift-day__user-actions" x-show="activeUserId" x-cloak>
                                        @foreach ($dayAssignments as $assignment)
                                            <div x-show="activeUserId === '{{ $assignment->user_id }}'" class="space-y-1">
                                                <button
                                                    type="button"
                                                    class="shift-day__user-shift"
                                                    x-bind:class="selectedDates.includes('{{ $date }}') ? 'is-active' : ''"
                                                    x-on:click.stop="toggleDate('{{ $date }}')"
                                                >
                                                    {{ $assignment->shift?->name ?? 'Chưa có ca' }}
                                                </button>
                                                <form
                                                    method="POST"
                                                    action="{{ route('shift-assignments.destroy', $assignment) }}"
                                                    x-on:click.stop
                                                    onsubmit="return confirm('Bạn chắc chắn muốn xóa gán ca ngày {{ $day->format('d/m/Y') }} của {{ $assignment->user?->name }}?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700">
                                                        Xóa gán ca
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach

                                        <span
                                            class="shift-day__no-user-shift"
                                            x-show="activeUserId && !@js($assignedUserIds).includes(activeUserId)"
                                        >
                                            Chưa có ca
                                        </span>
                                    </div>
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
