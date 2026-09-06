<!-- ATTENDANCE_OVERTIME_SYNC_V4 -->

@php
    /*
     * Nguồn duy nhất để tính tổng tăng ca là phút nguyên.
     * Không cộng overtime_hours đã làm tròn theo từng ngày.
     */
    $attendanceTotalOvertimeMinutes = (int) $rows->sum(
        fn ($row) => $row['cells']->sum(
            fn ($cell) => (int) (
                $cell['overtime_minutes'] ?? 0
            )
        )
    );

    $attendanceTotalOvertimeHours = round(
        $attendanceTotalOvertimeMinutes / 60,
        1
    );
@endphp

<x-app-layout>
    <div class="attendance-stats-page">
        <section class="attendance-stats-toolbar">
            <div>
                <h1>Tổng hợp công</h1>
                <p>Quản lý chấm công nhân viên theo tháng</p>
            </div>

            <form
                method="GET"
                action="{{ route('attendance-statistics.index') }}"
                class="attendance-stats-filters"
            >
                <input
                    type="month"
                    name="month"
                    value="{{ $month }}"
                    aria-label="Tháng"
                >

                @if (auth()->user()->role === 'admin')
                    <select name="branch_id" aria-label="Chi nhánh">
                        <option value="">Tất cả chi nhánh</option>

                        @foreach ($branches as $branch)
                            <option
                                value="{{ $branch->id }}"
                                @selected(
                                    (string) $branchId
                                    === (string) $branch->id
                                )
                            >
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Tìm tên, email, ID hoặc mã NV..."
                    aria-label="Tìm tên, email, ID hoặc mã NV"
                >

                <button type="submit">Lọc dữ liệu</button>
            </form>
        </section>

        <section class="attendance-stat-cards">
            <div class="attendance-stat-card">
                <span>Tổng nhân viên</span>
                <strong>{{ $summary['totalEmployees'] }}</strong>
            </div>

            <div class="attendance-stat-card attendance-stat-card--blue">
                <span>Tổng giờ tăng ca</span>
                <strong>
                    {{ number_format(
                        $attendanceTotalOvertimeHours,
                        1,
                        ',',
                        '.'
                    ) }}h
                </strong>

                <small>
                    {{ number_format(
                        $attendanceTotalOvertimeMinutes,
                        0,
                        ',',
                        '.'
                    ) }}
                    phút
                </small>
            </div>

            <div class="attendance-stat-card attendance-stat-card--orange">
                <span>Lượt đi trễ</span>
                <strong>{{ $summary['lateCount'] }}</strong>
            </div>

            <div class="attendance-stat-card attendance-stat-card--red">
                <span>Không phép</span>
                <strong>{{ $summary['absentCount'] }}</strong>
            </div>
        </section>

        <section class="attendance-board">
            <div class="attendance-board__header">
                <div>
                    <h2>Bảng chấm công</h2>
                    <p>
                        Trạng thái được tính từ phân ca, giờ vào,
                        giờ ra và đơn xin phép đã duyệt.
                    </p>
                </div>

                <div class="attendance-board__segmented">
                    <span>Theo tháng</span>
                </div>
            </div>

            <div class="attendance-board__mobile-hint">
                Vuốt ngang để xem đủ các ngày trong tháng.
            </div>

            <div class="attendance-board__table-wrap">
                <table class="attendance-board__table">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Bộ phận</th>

                            @foreach ($days as $day)
                                <th>
                                    <div>{{ $day->format('d/m') }}</div>
                                    <small>
                                        {{ mb_convert_case($day->locale('vi')->translatedFormat('l'), MB_CASE_TITLE, 'UTF-8') }}
                                    </small>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="attendance-board__employee">
                                    <strong>{{ $row['user']->name }}</strong>

                                    <span>
                                        Mã NV:
                                        {{ $row['user']->employee_code ?? $row['user']->id }}

                                        @if ($row['user']->email)
                                            - {{ $row['user']->email }}
                                        @endif
                                    </span>
                                </td>

                                <td>
                                    <span class="attendance-branch-pill">
                                        {{ $row['user']->branch?->name ?? 'Chưa có' }}
                                    </span>
                                </td>

                                @foreach ($days as $day)
                                    @php
                                        $cell = $row['cells'][$day->toDateString()];

                                        /*
                                         * Dùng màu inline để trạng thái luôn tô
                                         * toàn bộ ô, không phụ thuộc thứ tự CSS
                                         * hoặc quá trình biên dịch Tailwind.
                                         */
                                        $statusThemeMap = [
                                            'full_day' => [
                                                'background' => '#D1FAE5',
                                                'border' => '#10B981',
                                                'text' => '#065F46',
                                                'label' => 'Đủ Công',
                                            ],

                                            'insufficient_work' => [
                                                'background' => '#FFEDD5',
                                                'border' => '#F97316',
                                                'text' => '#9A3412',
                                                'label' => 'Thiếu Công',
                                            ],

                                            'late' => [
                                                'background' => '#FEF3C7',
                                                'border' => '#F59E0B',
                                                'text' => '#92400E',
                                                'label' => 'Đi Trễ',
                                            ],

                                            'working' => [
                                                'background' => '#DBEAFE',
                                                'border' => '#3B82F6',
                                                'text' => '#1E40AF',
                                                'label' => 'Đang Làm',
                                            ],

                                            'not_started' => [
                                                'background' => '#F8FAFC',
                                                'border' => '#CBD5E1',
                                                'text' => '#64748B',
                                                'label' => 'Chưa Làm',
                                                'border_width' => '1px',
                                            ],

                                            'not_checked_in_yet' => [
                                                'background' => '#E0F2FE',
                                                'border' => '#38BDF8',
                                                'text' => '#075985',
                                                'label' => 'Chưa Checkin',
                                            ],

                                            'no_shift' => [
                                                'background' => '#F3F4F6',
                                                'border' => '#6B7280',
                                                'text' => '#374151',
                                                'label' => 'Nghỉ',
                                            ],

                                            'leave_approved' => [
                                                'background' => '#EDE9FE',
                                                'border' => '#8B5CF6',
                                                'text' => '#5B21B6',
                                                'label' => 'Có Phép',
                                            ],

                                            'absent_without_leave' => [
                                                'background' => '#FEE2E2',
                                                'border' => '#EF4444',
                                                'text' => '#991B1B',
                                                'label' => 'Không Phép',
                                            ],

                                            'missing_checkin' => [
                                                'background' => '#FAE8FF',
                                                'border' => '#D946EF',
                                                'text' => '#86198F',
                                                'label' => 'Thiếu Checkin',
                                            ],

                                            'missing_checkout' => [
                                                'background' => '#FFE4E6',
                                                'border' => '#E11D48',
                                                'text' => '#9F1239',
                                                'label' => 'Thiếu Checkout',
                                            ],

                                            'upcoming' => [
                                                'background' => '#F5F7FF',
                                                'border' => '#C7D2FE',
                                                'text' => '#4F46E5',
                                                'label' => 'Sắp Đến',
                                                'border_width' => '1px',
                                            ],

                                            'pending' => [
                                                'background' => '#F1F5F9',
                                                'border' => '#64748B',
                                                'text' => '#334155',
                                                'label' => 'Chờ Xử Lý',
                                            ],

                                            'unknown' => [
                                                'background' => '#F1F5F9',
                                                'border' => '#94A3B8',
                                                'text' => '#475569',
                                                'label' => 'Không Xác Định',
                                            ],
                                        ];

                                        $theme = $statusThemeMap[$cell['status']]
                                            ?? $statusThemeMap['unknown'];

                                        $displayLabel = $theme['label']
                                            ?? mb_convert_case(
                                                $cell['label'],
                                                MB_CASE_TITLE,
                                                'UTF-8'
                                            );

                                        $cellStyle = sprintf(
                                            'background-color:%s;'
                                            . 'border-color:%s;'
                                            . 'color:%s;'
                                            . 'border-width:%s;'
                                            . 'box-shadow:inset 0 0 0 1px rgba(255,255,255,.35);',
                                            $theme['background'],
                                            $theme['border'],
                                            $theme['text'],
                                            $theme['border_width']
                                                ?? '2px'
                                        );

                                        $labelStyle = sprintf(
                                            'background-color:rgba(255,255,255,.72);'
                                            . 'border-color:%s;'
                                            . 'color:%s;',
                                            $theme['border'],
                                            $theme['text']
                                        );

                                    @endphp

                                    <td>
                                        <div
                                            class="
                                                attendance-cell
                                                attendance-cell--{{ $cell['status'] }}
                                            "
                                            style="{{ $cellStyle }}"
                                            title="{{ $cell['subtitle'] }}"
                                        >
                                            <div class="attendance-cell__top">
                                                <strong>
                                                    {{ $cell['shift'] ?? $cell['label'] }}
                                                </strong>

                                                <span
                                                    class="
                                                        inline-flex
                                                        rounded-full
                                                        border
                                                        px-2
                                                        py-0.5
                                                        text-[11px]
                                                        font-extrabold
                                                    "
                                                    style="{{ $labelStyle }}"
                                                >
                                                    {{ $displayLabel }}
                                                </span>
                                            </div>

                                            <div class="attendance-cell__time">
                                                {{ $cell['time'] }}
                                            </div>

                                            @if ((int) ($cell['overtime_minutes'] ?? 0) > 0)
                                                <div
                                                    class="
                                                        mt-auto
                                                        flex
                                                        justify-end
                                                        pt-3
                                                    "
                                                >
                                                    <span
                                                        class="
                                                            rounded-full
                                                            border
                                                            border-emerald-500
                                                            bg-emerald-600
                                                            px-2
                                                            py-1
                                                            text-[11px]
                                                            font-extrabold
                                                            text-white
                                                        "
                                                    >
                                                        +{{ (int) $cell['overtime_minutes'] }}
                                                        phút
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ 2 + $days->count() }}"
                                    class="attendance-board__empty"
                                >
                                    Không có nhân sự phù hợp với bộ lọc.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>