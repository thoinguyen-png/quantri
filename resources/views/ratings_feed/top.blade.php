<x-app-layout>
    @php
        $currentMonth = now()->format('Y-m');
        $currentYear = now()->format('Y');

        $rankStart = (($leaders->currentPage() - 1) * $leaders->perPage()) + 1;
    @endphp

    <style>
        .rating-top-page {
            max-width: 1080px;
            margin: 0 auto;
            padding: 20px 16px 36px;
        }

        .rating-top-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .rating-top-head__eyebrow {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
        }

        .rating-top-head h1 {
            margin: 0;
            color: #0f172a;
            font-size: 26px;
            font-weight: 800;
        }

        .rating-top-head p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .rating-top-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .rating-top-filter {
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .rating-top-filter__grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .rating-top-filter label {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .rating-top-filter label > span {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .rating-top-filter select,
        .rating-top-filter input {
            width: 100%;
            min-height: 42px;
            padding: 0 11px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #0f172a;
            outline: none;
        }

        .rating-top-filter select:focus,
        .rating-top-filter input:focus {
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, .18);
        }

        .rating-top-filter__actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 14px;
        }

        .rating-top-filter__submit,
        .rating-top-filter__reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 15px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .rating-top-filter__submit {
            border: 0;
            background: #0f172a;
            color: #fff;
        }

        .rating-top-filter__reset {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
        }

        .rating-top-range {
            display: none;
        }

        .rating-top-range.is-visible {
            display: flex;
        }

        .rating-top-card {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .rating-top-card__summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .rating-top-card__summary strong {
            color: #0f172a;
            font-size: 14px;
        }

        .rating-top-card__summary span {
            color: #64748b;
            font-size: 12px;
        }

        .rating-top-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rating-top-table th,
        .rating-top-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: middle;
        }

        .rating-top-table th {
            background: #fff;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .rating-top-table tr:last-child td {
            border-bottom: 0;
        }

        .rating-top-rank {
            width: 76px;
            color: #334155;
            font-size: 20px;
            font-weight: 800;
        }

        .rating-top-person {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .rating-top-person__avatar {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-weight: 800;
        }

        .rating-top-person strong {
            display: block;
            overflow: hidden;
            color: #0f172a;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rating-top-count {
            font-size: 15px;
            font-weight: 800;
            white-space: nowrap;
        }

        .rating-top-count--good {
            color: #16a34a;
        }

        .rating-top-count--average {
            color: #d97706;
        }

        .rating-top-count--bad {
            color: #dc2626;
        }

        .rating-top-empty {
            padding: 32px 18px;
            color: #64748b;
            text-align: center;
        }

        .rating-top-pagination {
            margin-top: 16px;
        }

        @media (max-width: 820px) {
            .rating-top-filter__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .rating-top-page {
                padding: 16px 12px 28px;
            }

            .rating-top-head {
                flex-direction: column;
            }

            .rating-top-head h1 {
                font-size: 22px;
            }

            .rating-top-filter__grid {
                grid-template-columns: 1fr;
            }

            .rating-top-filter__actions {
                justify-content: stretch;
            }

            .rating-top-filter__submit,
            .rating-top-filter__reset {
                flex: 1;
            }

            .rating-top-card {
                overflow-x: auto;
            }

            .rating-top-table {
                min-width: 720px;
            }
        }
    </style>

    <div class="rating-top-page">
        <header class="rating-top-head">
            <div>
                <span class="rating-top-head__eyebrow">BẢNG XẾP HẠNG</span>
                <h1>Top nhân viên được đánh giá tốt</h1>
                <p>{{ $periodLabel }}</p>
            </div>

            <a href="{{ route('ratings.feed.index', array_filter([
                'branch_id' => $branchFilter,
            ], fn ($value) => $value !== null && $value !== '')) }}"
               class="rating-top-back">
                ← Quay lại feed
            </a>
        </header>

        <form class="rating-top-filter" method="GET" action="{{ route('ratings.feed.top') }}" data-rating-top-filter>
            <div class="rating-top-filter__grid">
                <label>
                    <span>Thời gian</span>
                    <select name="period" data-period-select>
                        <option value="week" @selected($periodFilter === 'week')>Tuần này</option>
                        <option value="month" @selected($periodFilter === 'month')>Theo tháng</option>
                        <option value="year" @selected($periodFilter === 'year')>Theo năm</option>
                        <option value="all" @selected($periodFilter === 'all')>Tất cả</option>
                        <option value="custom" @selected($periodFilter === 'custom')>Từ ngày - đến ngày</option>
                    </select>
                </label>

                @if ($branchOptions->isNotEmpty())
                    <label>
                        <span>Chi nhánh</span>
                        <select name="branch_id">
                            <option value="">Toàn hệ thống</option>
                            @foreach ($branchOptions as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $branchFilter === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label data-period-field="month">
                    <span>Tháng</span>
                    <input
                        type="month"
                        name="month"
                        value="{{ $monthFilter ?: $currentMonth }}"
                    >
                </label>

                <label data-period-field="year">
                    <span>Năm</span>
                    <input
                        type="number"
                        name="year"
                        min="2000"
                        max="2100"
                        value="{{ $yearFilter ?: $currentYear }}"
                    >
                </label>

                <label class="rating-top-range" data-period-field="custom">
                    <span>Từ ngày</span>
                    <input
                        type="date"
                        name="from"
                        value="{{ $fromFilter }}"
                    >
                </label>

                <label class="rating-top-range" data-period-field="custom">
                    <span>Đến ngày</span>
                    <input
                        type="date"
                        name="to"
                        value="{{ $toFilter }}"
                    >
                </label>
            </div>

            <div class="rating-top-filter__actions">
                <a href="{{ route('ratings.feed.top', array_filter([
                    'branch_id' => $branchFilter,
                    'period' => 'week',
                ], fn ($value) => $value !== null && $value !== '')) }}"
                   class="rating-top-filter__reset">
                    Đặt lại
                </a>

                <button type="submit" class="rating-top-filter__submit">
                    Lọc
                </button>
            </div>
        </form>

        <section class="rating-top-card">
            <div class="rating-top-card__summary">
                <strong>{{ $periodLabel }}</strong>
                <span>{{ $leaders->total() }} nhân viên</span>
            </div>

            @if ($leaders->isNotEmpty())
                <table class="rating-top-table">
                    <thead>
                        <tr>
                            <th>Hạng</th>
                            <th>Nhân viên</th>
                            <th>Tốt</th>
                            <th>Trung bình</th>
                            <th>Tệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaders as $leader)
                            @php
                                $rank = $rankStart + $loop->index;
                                $employeeName = $leader->employee?->name ?? 'Nhân sự';
                                $rankLabel = match ($rank) {
                                    1 => '🥇',
                                    2 => '🥈',
                                    3 => '🥉',
                                    default => '#'.$rank,
                                };
                            @endphp

                            <tr>
                                <td class="rating-top-rank">{{ $rankLabel }}</td>
                                <td>
                                    <div class="rating-top-person">
                                        <div class="rating-top-person__avatar">
                                            {{ mb_strtoupper(mb_substr(trim($employeeName), 0, 1)) }}
                                        </div>
                                        <strong>{{ $employeeName }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="rating-top-count rating-top-count--good">
                                        {{ (int) $leader->good_count }}
                                    </span>
                                </td>
                                <td>
                                    <span class="rating-top-count rating-top-count--average">
                                        {{ (int) $leader->average_count }}
                                    </span>
                                </td>
                                <td>
                                    <span class="rating-top-count rating-top-count--bad">
                                        {{ (int) $leader->bad_count }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rating-top-empty">
                    Chưa có đánh giá tốt trong khoảng thời gian này.
                </div>
            @endif
        </section>

        @if ($leaders->hasPages())
            <div class="rating-top-pagination">
                {{ $leaders->links() }}
            </div>
        @endif
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-rating-top-filter]');

            if (!form) {
                return;
            }

            const periodSelect = form.querySelector('[data-period-select]');
            const monthField = form.querySelector('[data-period-field="month"]');
            const yearField = form.querySelector('[data-period-field="year"]');
            const customFields = form.querySelectorAll('[data-period-field="custom"]');

            function refreshPeriodFields() {
                const period = periodSelect.value;

                monthField.style.display = period === 'month' ? 'flex' : 'none';
                yearField.style.display = period === 'year' ? 'flex' : 'none';

                customFields.forEach(function (field) {
                    field.classList.toggle('is-visible', period === 'custom');
                });
            }

            periodSelect.addEventListener('change', refreshPeriodFields);
            refreshPeriodFields();
        })();
    </script>
</x-app-layout>