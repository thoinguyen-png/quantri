<x-app-layout>
    @php
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month);
        $previousMonth = $monthDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthDate->copy()->addMonth()->format('Y-m');
        $weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

        $weekdayNames = [
            1 => 'Thứ Hai',
            2 => 'Thứ Ba',
            3 => 'Thứ Tư',
            4 => 'Thứ Năm',
            5 => 'Thứ Sáu',
            6 => 'Thứ Bảy',
            7 => 'Chủ Nhật',
        ];

        $monthLabel = 'Tháng ' . $monthDate->format('m/Y');

        /*
         * Đếm trực tiếp theo trạng thái chuẩn từ AttendanceStatusService.
         * Không dùng work_day để suy đoán nhãn hiển thị.
         */
        $statusCounts = collect($cells)
            ->groupBy('status')
            ->map
            ->count();

        /*
         * Dashboard Manager và Nhân viên chỉ hiển thị 5 nhóm tổng kết.
         *
         * Các trạng thái thể hiện thiếu thời gian làm việc được gom chung
         * vào "Thiếu công" để không làm mất số liệu khi ẩn các thẻ chi tiết.
         */
        $insufficientWorkCount =
            (int) $statusCounts->get('insufficient_work', 0)
            + (int) $statusCounts->get('late', 0)
            + (int) $statusCounts->get('early_leave', 0)
            + (int) $statusCounts->get('missing_checkin', 0)
            + (int) $statusCounts->get('missing_checkout', 0);

        $summaryCards = [
            [
                'label' => 'Đủ công',
                'value' => (int) $statusCounts->get('full_day', 0),
                'meta' => 'ngày',
                'status' => 'full_day',
            ],
            [
                'label' => 'Thiếu công',
                'value' => $insufficientWorkCount,
                'meta' => 'ngày',
                'status' => 'insufficient_work',
            ],
            [
                'label' => 'Nghỉ OFF',
                'value' => (int) $statusCounts->get('no_shift', 0),
                'meta' => 'ngày',
                'status' => 'no_shift',
            ],
            [
                'label' => 'Không phép',
                'value' => (int) $statusCounts->get('absent_without_leave', 0),
                'meta' => 'ngày',
                'status' => 'absent_without_leave',
            ],
            [
                'label' => 'Tăng ca',
                'value' => round((float) $summary['overtime'], 1),
                'meta' => 'giờ',
                'status' => 'overtime',
            ],
        ];
    @endphp
    <style>
        /*
         * Màu trạng thái dùng chung cho lịch, thẻ tổng kết và modal.
         * Khai báo trực tiếp để không phụ thuộc Tailwind quét class động.
         */
        .home-calendar .home-day {
            border: 1px solid transparent;
        }

        .home-calendar .home-day.home-day--full_day,
        .home-status-pill--full_day {
            background: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #065f46 !important;
        }

        .home-calendar .home-day.home-day--insufficient_work,
        .home-status-pill--insufficient_work {
            background: #fffbeb !important;
            border-color: #fde68a !important;
            color: #92400e !important;
        }

        .home-calendar .home-day.home-day--late,
        .home-status-pill--late {
            background: #fff7ed !important;
            border-color: #fdba74 !important;
            color: #9a3412 !important;
        }

        .home-calendar .home-day.home-day--working,
        .home-status-pill--working {
            background: #f0f9ff !important;
            border-color: #7dd3fc !important;
            color: #075985 !important;
        }

        .home-calendar .home-day.home-day--not_started,
        .home-calendar .home-day.home-day--not_checked_in_yet,
        .home-status-pill--not_started,
        .home-status-pill--not_checked_in_yet {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #475569 !important;
        }

        .home-calendar .home-day.home-day--no_shift,
        .home-status-pill--no_shift {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
            color: #4b5563 !important;
        }

        .home-calendar .home-day.home-day--leave_approved,
        .home-status-pill--leave_approved {
            background: #f5f3ff !important;
            border-color: #c4b5fd !important;
            color: #6d28d9 !important;
        }

        .home-calendar .home-day.home-day--absent_without_leave,
        .home-status-pill--absent_without_leave {
            background: #fef2f2 !important;
            border-color: #fca5a5 !important;
            color: #b91c1c !important;
        }

        .home-calendar .home-day.home-day--missing_checkin,
        .home-status-pill--missing_checkin {
            background: #fdf4ff !important;
            border-color: #f0abfc !important;
            color: #a21caf !important;
        }

        .home-calendar .home-day.home-day--missing_checkout,
        .home-status-pill--missing_checkout {
            background: #fff1f2 !important;
            border-color: #fda4af !important;
            color: #9f1239 !important;
        }

        .home-calendar .home-day.home-day--upcoming,
        .home-status-pill--upcoming {
            background: #eef2ff !important;
            border-color: #c7d2fe !important;
            color: #4338ca !important;
        }

        .home-calendar .home-day.home-day--pending,
        .home-status-pill--pending {
            background: #ecfeff !important;
            border-color: #a5f3fc !important;
            color: #0e7490 !important;
        }

        .home-status-pill--overtime {
            background: #f0fdf4 !important;
            border-color: #86efac !important;
            color: #166534 !important;
        }

        .home-day-modal[data-status="full_day"] [data-modal-label] {
            color: #047857;
        }

        .home-day-modal[data-status="insufficient_work"] [data-modal-label] {
            color: #b45309;
        }

        .home-day-modal[data-status="late"] [data-modal-label] {
            color: #c2410c;
        }

        .home-day-modal[data-status="working"] [data-modal-label] {
            color: #0369a1;
        }

        .home-day-modal[data-status="leave_approved"] [data-modal-label] {
            color: #7c3aed;
        }

        .home-day-modal[data-status="absent_without_leave"] [data-modal-label],
        .home-day-modal[data-status="missing_checkout"] [data-modal-label] {
            color: #be123c;
        }

        .home-day-modal[data-status="missing_checkin"] [data-modal-label] {
            color: #a21caf;
        }

        .home-day-modal[data-status="upcoming"] [data-modal-label] {
            color: #4338ca;
        }
    </style>


    <div class="home-page home-page--employee">
        <section class="home-brand-bar" aria-label="Maxsim">
            <img src="{{ asset('icons/logoMaxSim.png') }}" alt="Maxsim">
        </section>

        <section class="home-topline">
            <div>
                Xin chào, <strong>{{ $user->name }}</strong>
            </div>
            <a href="{{ route('users.me') }}" class="home-profile-button" aria-label="Tài khoản">
                <span>{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            </a>
        </section>
<!-- 
        <section class="home-tabs">
                <a href="{{ route('attendance.dashboard', ['month' => $month]) }}" class="home-tab is-active">Bảng chấm công</a>
            <a href="{{ route('attendance-supplements.index') }}" class="home-tab">Thông báo</a>
        </section> -->

        <section class="home-calendar-card">
            <div class="home-month-nav">
                <a href="{{ route('attendance.dashboard', ['month' => $previousMonth]) }}" aria-label="Tháng trước">‹</a>
                <strong>{{ $monthLabel }}</strong>
                <a href="{{ route('attendance.dashboard', ['month' => $nextMonth]) }}" aria-label="Tháng sau">›</a>
            </div>

            <h1>Bảng công - {{ $monthLabel }}</h1>

            <div class="home-status-legend">
                @foreach ($summaryCards as $card)
                    <div class="home-status-pill home-status-pill--{{ $card['status'] }}">
                        <span>{{ $card['label'] }}</span>
                        <strong>{{ $card['value'] }} {{ $card['meta'] }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="home-calendar">
                @foreach ($weekdays as $weekday)
                    <div class="home-weekday">{{ $weekday }}</div>
                @endforeach

                @for ($i = 0; $i < $calendarLeadingDays; $i++)
                    <div class="home-day home-day--blank"></div>
                @endfor

                @foreach ($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $cell = $cells[$dateKey];
                    @endphp

                    <button
                        type="button"
                        class="home-day home-day--{{ $cell['status'] }} {{ $day->isToday() ? 'is-today' : '' }}"
                        data-day-trigger
                        data-day-status="{{ $cell['status'] }}"
                        data-day-date="{{ $day->format('d/m/Y') }}"
                        data-day-weekday="{{ $weekdayNames[$day->dayOfWeekIso] }}"
                        data-day-label="{{ $cell['label'] }}"
                        data-day-shift="{{ $cell['shift'] ?? 'Không có ca' }}"
                        data-day-time="{{ $cell['time'] }}"
                        data-day-checkin="{{ $cell['checkin'] ?? '--:--' }}"
                        data-day-checkout="{{ $cell['checkout'] ?? '--:--' }}"
                        data-day-work-label="{{ $cell['work_label'] ?? $cell['label'] }}"
                        data-day-overtime="{{ $cell['overtime_hours'] }}"
                        data-day-note="{{ $cell['note'] ?? '' }}"
                        data-day-badges="{{ implode('|', $cell['badges'] ?? []) }}"
                        data-day-segments='@json($cell['segments'] ?? [])'
                    >
                        <span>{{ $day->day }}</span>
                        <small>{{ $cell['checkin'] ? $cell['checkin'] : '-' }}</small>
                    </button>
                @endforeach
            </div>
        </section>

        <div class="home-day-modal" data-day-modal aria-hidden="true">
            <button class="home-day-modal__backdrop" type="button" data-day-modal-close aria-label="Đóng"></button>
            <section class="home-day-modal__panel" role="dialog" aria-modal="true" aria-labelledby="day-modal-title">
                <div class="home-day-modal__handle" aria-hidden="true"></div>
                <div class="home-day-modal__head">
                    <div>
                        <span data-modal-weekday>--</span>
                        <h2 id="day-modal-title" data-modal-date>--</h2>
                    </div>
                    <strong data-modal-label>--</strong>
                </div>

                <div class="home-day-modal__grid">
                    <div><span>Ca làm</span><strong data-modal-shift>--</strong></div>
                    <div><span>Giờ ca</span><strong data-modal-time>--</strong></div>
                    <div><span>Giờ vào</span><strong data-modal-checkin>--</strong></div>
                    <div><span>Giờ ra</span><strong data-modal-checkout>--</strong></div>
                    <div><span>Công</span><strong data-modal-work-label>--</strong></div>
                    <div><span>Tăng ca</span><strong class="home-day-modal__hours"><span data-modal-overtime>0</span>h</strong></div>
                </div>

                <p class="home-day-modal__note" data-modal-note></p>
                <div class="home-day-modal__badges" data-modal-segments></div>
                <div class="home-day-modal__badges" data-modal-badges></div>

                <button class="home-day-modal__close" type="button" data-day-modal-close>
                    Dong
                </button>
            </section>
        </div>

    </div>

    <script>
        (function () {
            const modal = document.querySelector('[data-day-modal]');

            if (!modal) {
                return;
            }

            const fields = {
                weekday: modal.querySelector('[data-modal-weekday]'),
                date: modal.querySelector('[data-modal-date]'),
                label: modal.querySelector('[data-modal-label]'),
                shift: modal.querySelector('[data-modal-shift]'),
                time: modal.querySelector('[data-modal-time]'),
                checkin: modal.querySelector('[data-modal-checkin]'),
                checkout: modal.querySelector('[data-modal-checkout]'),
                workLabel: modal.querySelector('[data-modal-work-label]'),
                overtime: modal.querySelector('[data-modal-overtime]'),
                note: modal.querySelector('[data-modal-note]'),
                segments: modal.querySelector('[data-modal-segments]'),
                badges: modal.querySelector('[data-modal-badges]'),
            };

            function openModal(button) {
                fields.weekday.textContent = button.dataset.dayWeekday;
                fields.date.textContent = button.dataset.dayDate;
                fields.label.textContent = button.dataset.dayLabel;
                fields.shift.textContent = button.dataset.dayShift;
                fields.time.textContent = button.dataset.dayTime;
                fields.checkin.textContent = button.dataset.dayCheckin;
                fields.checkout.textContent = button.dataset.dayCheckout;
                fields.workLabel.textContent = button.dataset.dayWorkLabel || button.dataset.dayLabel;
                fields.overtime.textContent = button.dataset.dayOvertime || 0;
                fields.note.textContent = button.dataset.dayNote || 'Không có ghi chú.';
                let segments = [];
                try {
                    segments = JSON.parse(button.dataset.daySegments || '[]');
                } catch (error) {
                    segments = [];
                }
                fields.segments.innerHTML = segments.length
                    ? '<div class="home-segment-grid">' + segments.map(function (segment) {
                        const checkinValue = segment.checkin || segment.start;
                        const checkoutValue = segment.checkout || segment.end;

                        return '<div class="home-segment-card"><span>Đoạn ' + segment.order + ' · Vào</span><strong>' + checkinValue + '</strong><small>' + segment.status + '</small></div>'
                            + '<div class="home-segment-card"><span>Đoạn ' + segment.order + ' · Ra</span><strong>' + checkoutValue + '</strong><small>' + segment.status + '</small></div>';
                    }).join('') + '</div>'
                    : '';
                const badges = (button.dataset.dayBadges || '').split('|').filter(Boolean);
                fields.badges.innerHTML = badges.map(function (badge) {
                    const escaped = badge.replace(/[&<>"']/g, function (char) {
                        return {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        }[char];
                    });
                    const isOvertime = escaped.toLowerCase().includes('tăng ca');
                    const className = isOvertime
                        ? 'home-day-modal__badge home-day-modal__badge--overtime'
                        : 'home-day-modal__badge';

                    return '<span class="' + className + '">' + escaped + '</span>';
                }).join('');
                modal.dataset.status = button.dataset.dayStatus || 'none';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-day-trigger]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button);
                });
            });

            modal.querySelectorAll('[data-day-modal-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });
        })();
    </script>
</x-app-layout>