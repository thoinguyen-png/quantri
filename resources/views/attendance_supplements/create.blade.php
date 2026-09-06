<x-app-layout>
    <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Đơn bổ sung công</h1>
                    <p class="mt-1 text-sm text-slate-500">Bổ sung giờ vào/ra bị thiếu cho nhân viên.</p>
                </div>

                <a
                    href="{{ route('attendance-supplements.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Quay lại
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('attendance-supplements.store') }}"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
                data-supplement-form
                data-day-info-url="{{ route('attendance-supplements.day-info', absolute: false) }}"
                data-old-checkin="{{ old('requested_checkin_time') }}"
                data-old-checkout="{{ old('requested_checkout_time') }}"
                data-old-segment-checkins='@json(old("segment_requested_checkin_time", []))'
                data-old-segment-checkouts='@json(old("segment_requested_checkout_time", []))'
            >
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="supplement-user-id" class="mb-1.5 block text-sm font-bold text-slate-700">Nhân viên</label>
                        <select
                            id="supplement-user-id"
                            name="user_id"
                            required
                            data-user-id
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('user_id', auth()->id()) === $employee->id)>
                                    {{ $employee->employee_code }} - {{ $employee->name }}
                                    @if ($employee->branch)
                                        | {{ $employee->branch->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="supplement-work-date" class="mb-1.5 block text-sm font-bold text-slate-700">Ngày làm việc</label>
                        <input
                            id="supplement-work-date"
                            type="date"
                            name="work_date"
                            value="{{ old('work_date', now()->toDateString()) }}"
                            @if($supplementDateRestricted)
                                min="{{ $supplementDateMin }}"
                                max="{{ $supplementDateMax }}"
                            @endif
                            required
                            data-work-date
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500" data-picker-help>
                    @if(auth()->user()->role === 'admin')
                        Admin được chọn ngày bổ sung công tự do.
                    @elseif($supplementDateRestricted)
                        Chỉ được chọn hôm nay và 2 ngày trước đó.
                    @else
                        Chọn nhân viên và ngày làm việc để xem khung giờ cần bổ sung.
                    @endif
                </p>

                <section class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3" aria-live="polite">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-black uppercase tracking-wide text-slate-500">Ca làm dự kiến</div>
                            <div class="mt-1 text-sm font-black text-slate-900" data-shift-summary>Đang tải...</div>
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700" data-day-status>
                            Đang kiểm tra
                        </span>
                    </div>
                </section>

                <section class="mt-5 hidden" data-missing-section>
                    <div class="mb-3">
                        <h2 class="text-base font-black text-slate-900">Khung giờ cần bổ sung</h2>
                        <p class="mt-1 text-sm text-slate-500">Nhập lại giờ vào/ra cần bổ sung cho ngày làm việc này.</p>
                    </div>

                    <div class="grid gap-3" data-missing-list></div>
                </section>

                <div class="mt-5">
                    <label for="supplement-reason" class="mb-1.5 block text-sm font-bold text-slate-700">Lý do bổ sung</label>
                    <textarea
                        id="supplement-reason"
                        name="reason"
                        rows="4"
                        required
                        placeholder="Ví dụ: Quên chấm giờ ra do hết pin điện thoại."
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a
                        href="{{ route('attendance-supplements.index') }}"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
                    >
                        Quay lại
                    </a>
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
                    >
                        Gửi đơn bổ sung
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-supplement-form]');

            if (!form) {
                return;
            }

            const userInput = form.querySelector('[data-user-id]');
            const dateInput = form.querySelector('[data-work-date]');
            const shiftSummary = form.querySelector('[data-shift-summary]');
            const dayStatus = form.querySelector('[data-day-status]');
            const pickerHelp = form.querySelector('[data-picker-help]');
            const missingSection = form.querySelector('[data-missing-section]');
            const missingList = form.querySelector('[data-missing-list]');
            const dayInfoUrl = form.dataset.dayInfoUrl;
            const oldCheckin = form.dataset.oldCheckin || '';
            const oldCheckout = form.dataset.oldCheckout || '';
            const oldSegmentCheckins = JSON.parse(form.dataset.oldSegmentCheckins || '{}');
            const oldSegmentCheckouts = JSON.parse(form.dataset.oldSegmentCheckouts || '{}');

            function oldSegmentValue(values, order) {
                return values && Object.prototype.hasOwnProperty.call(values, order)
                    ? String(values[order])
                    : '';
            }

            function currentFieldValue(name) {
                const field = Array.from(form.elements).find(function (element) {
                    return element.name === name;
                });

                return field ? field.value : '';
            }

            function setStatus(text, tone = 'info') {
                dayStatus.textContent = text;
                dayStatus.className = 'inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold ';
                dayStatus.className += tone === 'warning'
                    ? 'bg-amber-50 text-amber-700'
                    : (tone === 'error' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700');
            }

            function compactShiftText(data) {
                const segments = Array.isArray(data.segments) ? data.segments : [];

                if (segments.length >= 2) {
                    return (data.shift_name || 'Ca làm') + ' · ' + segments.map(function (segment) {
                        return (segment.start || '--:--') + '–' + (segment.end || '--:--');
                    }).join(' · ');
                }

                return data.shift_name
                    ? data.shift_name + ' · ' + (data.shift_time || '--:--')
                    : 'Ngày này chưa được gán ca.';
            }

            function timeInput(name, value, label) {
                const id = 'supplement-' + name.replace(/[^a-z0-9]/gi, '-');

                return '<label for="' + id + '" class="block">'
                    + '<span class="mb-1.5 block text-sm font-bold text-slate-700">' + label + '</span>'
                    + '<input id="' + id + '" type="time" name="' + name + '" value="' + (value || '') + '" required '
                    + 'class="h-12 w-full rounded-xl border-slate-300 text-base font-bold text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">'
                    + '</label>';
            }

            function renderTimeCard(item) {
                return '<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">'
                    + (item.title ? '<div class="mb-3"><h3 class="text-lg font-black text-slate-900">' + item.title + '</h3>'
                    + '<p class="mt-0.5 text-sm font-semibold text-slate-500">' + item.expected + '</p></div>' : '')
                    + '<div class="grid gap-3 sm:grid-cols-2">'
                    + timeInput(item.checkinName, item.checkinValue, item.checkinLabel)
                    + timeInput(item.checkoutName, item.checkoutValue, item.checkoutLabel)
                    + '</div>'
                    + '</article>';
            }

            function timeItemsFrom(data) {
                const segments = Array.isArray(data.segments) ? data.segments : [];

                if (segments.length >= 2) {
                    return segments
                        .map(function (segment) {
                            const order = String(segment.order);
                            const checkinName = 'segment_requested_checkin_time[' + order + ']';
                            const checkoutName = 'segment_requested_checkout_time[' + order + ']';

                            return {
                                title: 'Ca ' + order,
                                expected: (segment.start || '--:--') + '–' + (segment.end || '--:--'),
                                checkinName: checkinName,
                                checkoutName: checkoutName,
                                checkinLabel: 'Giờ vào ' + order,
                                checkoutLabel: 'Giờ ra ' + order,
                                checkinValue: currentFieldValue(checkinName) || oldSegmentValue(oldSegmentCheckins, order) || segment.checkin || '',
                                checkoutValue: currentFieldValue(checkoutName) || oldSegmentValue(oldSegmentCheckouts, order) || segment.checkout || '',
                            };
                        });
                }

                if (!data.shift_id) {
                    return [];
                }

                return [{
                    title: '',
                    expected: data.shift_time || '--:--',
                    checkinName: 'requested_checkin_time',
                    checkoutName: 'requested_checkout_time',
                    checkinLabel: 'Giờ vào',
                    checkoutLabel: 'Giờ ra',
                    checkinValue: currentFieldValue('requested_checkin_time') || oldCheckin || data.checkin_time || '',
                    checkoutValue: currentFieldValue('requested_checkout_time') || oldCheckout || data.checkout_time || '',
                }];
            }

            function renderTimeFields(items) {
                missingList.innerHTML = '';
                missingSection.classList.toggle('hidden', items.length === 0);
                pickerHelp.classList.toggle('hidden', items.length > 0);

                if (!items.length) {
                    return;
                }

                missingList.innerHTML = items.map(renderTimeCard).join('');
            }

            function resetState() {
                shiftSummary.textContent = 'Đang tải...';
                setStatus('Đang kiểm tra');
                renderTimeFields([]);
            }

            function fillDayInfo(data) {
                const items = timeItemsFrom(data);

                shiftSummary.textContent = compactShiftText(data);

                if (!data.shift_id) {
                    setStatus('Chưa gán ca', 'warning');
                } else if (items.length) {
                    setStatus('Đã tải ca');
                } else {
                    setStatus('Không có ca', 'warning');
                }

                renderTimeFields(items);
            }

            async function loadDayInfo() {
                if (!userInput.value || !dateInput.value) {
                    return;
                }

                resetState();

                try {
                    const url = new URL(dayInfoUrl, window.location.origin);
                    url.searchParams.set('user_id', userInput.value);
                    url.searchParams.set('work_date', dateInput.value);

                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Không tải được dữ liệu ca.');
                    }

                    fillDayInfo(await response.json());
                } catch (error) {
                    shiftSummary.textContent = error.message || 'Không tải được dữ liệu ca.';
                    setStatus('Lỗi dữ liệu', 'error');
                    renderTimeFields([]);
                }
            }

            form.addEventListener('submit', function (event) {
                if (missingSection.classList.contains('hidden')) {
                    event.preventDefault();
                    setStatus('Chưa có khung giờ', 'error');
                }
            });

            userInput.addEventListener('change', loadDayInfo);
            dateInput.addEventListener('change', loadDayInfo);
            loadDayInfo();
        })();
    </script>
</x-app-layout>
