<x-app-layout>
    @php
        $money = fn ($value) => number_format((float) $value, 0, ',', '.');

        $summary = [
            'employees' => $rows->count(),

            'work' => round(
                $rows->sum(
                    fn ($row) => $row['metrics']['work_units']
                ),
                2
            ),

            /*
             * Cộng tổng phút tăng ca trước,
             * sau đó mới đổi sang giờ.
             */
            'overtime' => round(
                $rows->sum(
                    fn ($row) =>
                        $row['metrics']['overtime_minutes']
                ) / 60,
                1
            ),

            'nkp' => round(
                $rows->sum(
                    fn ($row) =>
                        $row['metrics']['unauthorized_absence_days']
                ),
                2
            ),
        ];
    @endphp

    <div class="payroll-page p-3 sm:p-4 space-y-4">
        <div class="payroll-panel bg-white rounded-[1.75rem] p-4 sm:p-5 space-y-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[.18em] text-blue-600">
                        Payroll
                    </div>

                    <h1 class="mt-1 text-2xl sm:text-3xl font-black text-gray-950">
                        Bảng lương
                    </h1>

                    <p class="text-sm text-gray-500">
                        Tháng {{ $month }} · tổng hợp công, tăng ca và các khoản lương
                    </p>
                </div>

                <a
                    href="{{ route('payrolls.export', request()->query()) }}"
                    class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 font-bold text-white shadow-sm hover:bg-emerald-700"
                >
                    Xuất Excel
                </a>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="payroll-summary-card rounded-2xl p-4">
                    <div class="text-xs font-bold uppercase text-slate-500">
                        Nhân sự
                    </div>

                    <div class="mt-1 text-2xl font-black text-slate-950">
                        {{ $summary['employees'] }}
                    </div>
                </div>

                <div class="payroll-summary-card rounded-2xl p-4">
                    <div class="text-xs font-bold uppercase text-blue-600">
                        Tổng công
                    </div>

                    <div class="mt-1 text-2xl font-black text-blue-700">
                        {{ number_format((float) $summary['work'], 2, ',', '.') }}
                    </div>
                </div>

                <div class="payroll-summary-card rounded-2xl p-4">
                    <div class="text-xs font-bold uppercase text-amber-600">
                        Tăng ca
                    </div>

                    <div class="mt-1 text-2xl font-black text-amber-700">
                        {{ number_format((float) $summary['overtime'], 1, ',', '.') }}h
                    </div>
                </div>

                <div class="payroll-summary-card rounded-2xl p-4">
                    <div class="text-xs font-bold uppercase text-rose-600">
                        NKP
                    </div>

                    <div class="mt-1 text-2xl font-black text-rose-700">
                        {{ number_format((float) $summary['nkp'], 2, ',', '.') }} ngày
                    </div>
                </div>
            </div>

            <form
                method="GET"
                action="{{ route('payrolls.index') }}"
                class="payroll-filter grid gap-3 md:grid-cols-4"
            >
                <input
                    type="month"
                    name="month"
                    value="{{ $month }}"
                    class="rounded-2xl border-gray-300"
                    aria-label="Chọn tháng"
                >

                @if (auth()->user()->role === 'admin')
                    <select
                        name="branch_id"
                        class="rounded-2xl border-gray-300"
                        aria-label="Chi nhánh"
                    >
                        <option value="">Tất cả chi nhánh</option>

                        @foreach ($branches as $branch)
                            <option
                                value="{{ $branch->id }}"
                                @selected((string) $branchId === (string) $branch->id)
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
                    placeholder="Tìm tên, email, mã NV..."
                    class="rounded-2xl border-gray-300"
                >

                <button class="rounded-2xl bg-blue-600 px-5 py-3 font-bold text-white shadow-sm">
                    Lọc dữ liệu
                </button>
            </form>
        </div>

        <div class="payroll-panel bg-white rounded-[1.75rem] overflow-hidden">
            <div class="border-b border-slate-100 px-4 sm:px-5 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="font-black text-slate-950">
                        Chi tiết bảng lương
                    </div>

                    <div class="text-sm text-slate-500">
                        {{ $summary['employees'] }} nhân sự -
                        {{ count($columns) + 13 }} cột dữ liệu
                    </div>
                </div>

                <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                    {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>

            <div class="payroll-table-wrap overflow-auto">
                <table class="payroll-table min-w-[3700px] w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="payroll-sticky-1 sticky top-0 z-40 bg-slate-100 px-3 sm:px-4 py-3">
                                STT
                            </th>

                            <th class="payroll-sticky-2 sticky top-0 z-40 bg-slate-100 px-3 sm:px-4 py-3">
                                Tên
                            </th>

                            <th class="payroll-sticky-3 sticky top-0 z-40 bg-slate-100 px-3 sm:px-4 py-3">
                                Bộ phận
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3">
                                Trạng thái
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3">
                                Mã NV
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Công
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Công + tăng ca
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Số ngày đi trễ
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Tổng giờ đi trễ
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Về sớm (giờ)
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Tăng ca (giờ)
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                NKP (ngày)
                            </th>

                            <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                Về sớm (ngày)
                            </th>

                            @foreach ($columns as $label)
                                <th class="sticky top-0 z-30 bg-slate-100 px-4 py-3 text-right">
                                    {{ $label }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="group hover:bg-slate-50">
                                <td class="payroll-sticky-1 sticky z-20 bg-white px-3 sm:px-4 py-3 font-bold group-hover:bg-slate-50">
                                    {{ $row['stt'] }}
                                </td>

                                <td class="payroll-sticky-2 sticky z-20 bg-white px-3 sm:px-4 py-3 group-hover:bg-slate-50">
                                    <div class="max-w-[9rem] sm:max-w-[13rem] truncate font-bold text-slate-950">
                                        {{ $row['user']->name }}
                                    </div>

                                    <div class="max-w-[9rem] sm:max-w-[13rem] truncate text-xs text-slate-500">
                                        {{ $row['user']->email }}
                                    </div>
                                </td>

                                <td class="payroll-sticky-3 sticky z-20 bg-white px-3 sm:px-4 py-3 font-medium text-slate-700 group-hover:bg-slate-50">
                                    <div class="max-w-[6.5rem] sm:max-w-[10rem] truncate">
                                        {{ $row['user']->branch?->name ?? 'Chưa có' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">
                                        {{ $row['status'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex min-w-14 justify-center rounded-xl bg-slate-100 px-3 py-1 font-mono text-sm font-black text-slate-800">
                                        {{ $row['employee_code'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['work_units'], 2, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['work_with_overtime'], 2, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['late_days'], 0, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['late_hours'], 2, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['early_leave_hours'], 2, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['overtime_hours'], 1, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['unauthorized_absence_days'], 2, ',', '.') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    {{ number_format((float) $row['metrics']['early_leave_days'], 0, ',', '.') }}
                                </td>

                                @foreach ($columns as $key => $label)
                                    <td class="px-4 py-3 text-right">
                                        {{ $money($row['money'][$key]) }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ 13 + count($columns) }}"
                                    class="px-4 py-10 text-center text-slate-500"
                                >
                                    Không có nhân sự phù hợp với bộ lọc.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>