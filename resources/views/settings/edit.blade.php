<x-app-layout>
    <div class="p-4 bg-gray-100">
        <div class="w-full bg-white rounded-3xl shadow p-5 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Cấu hình hệ thống</h1>
                <p class="text-sm text-gray-500">Thiết lập nghiệp vụ thử việc và trạng thái nhân sự.</p>
            </div>

            @if (session('success'))
                <div class="rounded-2xl bg-green-100 p-3 text-sm font-semibold text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl bg-red-100 p-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <label class="flex items-start gap-3 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
                    <input
                        type="checkbox"
                        name="attendance_strict_mode"
                        value="1"
                        class="mt-1 rounded border-slate-300 text-amber-600"
                        @checked(old('attendance_strict_mode', $attendanceStrictMode))
                    >
                    <span>
                        <span class="block font-bold text-slate-900">
                            Chế độ kiểm tra chấm công nghiêm ngặt
                        </span>
                        <span class="mt-1 block text-sm text-slate-600">
                            Bật để áp dụng khung giờ checkin/checkout theo từng ca. Tắt để bỏ qua khung giờ khi cần vận hành tạm thời; QR, gương mặt, GPS và chống quét lại ngay vẫn được kiểm tra.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="font-bold">Mốc xử lý ca đêm qua ngày</label>
                    <input
                        type="time"
                        name="overnight_cutoff_time"
                        value="{{ old('overnight_cutoff_time', $overnightCutoffTime) }}"
                        class="mt-1 w-full rounded-xl border-gray-300"
                        required
                    >
                    <p class="mt-1 text-sm text-slate-500">Attendance mở từ hôm trước được checkout đến mốc này của sáng hôm sau. Ví dụ 08:50 nghĩa là sau 08:50 sẽ xem là quá hạn checkout.</p>
                    @error('overnight_cutoff_time')
                        <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <input
                        type="checkbox"
                        name="probation_auto_promote_enabled"
                        value="1"
                        class="mt-1 rounded border-slate-300 text-blue-600"
                        @checked(old('probation_auto_promote_enabled', $probationAutoPromoteEnabled))
                    >
                    <span>
                        <span class="block font-bold text-slate-900">
                            Tự động chuyển nhân viên thử việc thành chính thức khi đủ điều kiện
                        </span>
                        <span class="mt-1 block text-sm text-slate-500">
                            Khi bật, nhân sự mới luôn bắt đầu ở trạng thái thử việc và hệ thống tự xét lên chính thức theo ngày công hợp lệ.
                        </span>
                    </span>
                </label>

                <section class="rounded-2xl bg-indigo-50 p-4 ring-1 ring-indigo-200">
                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            name="shift_assignment_show_all_shifts"
                            value="1"
                            class="mt-1 rounded border-slate-300 text-indigo-600"
                            @checked(old('shift_assignment_show_all_shifts', $shiftAssignmentShowAllShifts))
                        >
                        <span>
                            <span class="block font-bold text-slate-900">Hiển thị toàn bộ ca khi gán ca</span>
                            <span class="mt-1 block text-sm text-slate-600">Bật: dùng mọi ca như hiện tại. Tắt: chỉ dùng các ca admin đã cấu hình cho từng cơ sở.</span>
                        </span>
                    </label>
                    <a href="{{ route('branch-shift-permissions.index') }}" class="mt-3 inline-flex text-sm font-bold text-indigo-700 hover:text-indigo-800">Cấu hình ca được phép theo cơ sở</a>
                </section>

                <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <input
                        type="checkbox"
                        name="allow_past_leave_requests"
                        value="1"
                        class="mt-1 rounded border-slate-300 text-blue-600"
                        @checked(old('allow_past_leave_requests', $allowPastLeaveRequests))
                    >
                    <span>
                        <span class="block font-bold text-slate-900">
                            Cho phép nộp đơn nghỉ phép/off cho ngày quá khứ
                        </span>
                        <span class="mt-1 block text-sm text-slate-500">
                            Khi tắt, nhân sự chỉ được nộp đơn từ hôm nay trở đi.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="font-bold">Giới hạn bổ sung công</label>
                    <select
                        name="attendance_supplement_limit_mode"
                        class="mt-1 w-full rounded-xl border-gray-300"
                        required
                    >
                        <option value="last_3_days" @selected(old('attendance_supplement_limit_mode', $attendanceSupplementLimitMode) === 'last_3_days')>
                            Chỉ 3 ngày gần nhất
                        </option>
                        <option value="free" @selected(old('attendance_supplement_limit_mode', $attendanceSupplementLimitMode) === 'free')>
                            Tự do
                        </option>
                    </select>
                    <p class="mt-1 text-sm text-slate-500">Chế độ 3 ngày gần nhất tính cả hôm nay: hôm nay và 2 ngày trước đó. Admin luôn được bổ sung công tự do, không bị giới hạn bởi quy tắc này.</p>
                    @error('attendance_supplement_limit_mode')
                        <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="font-bold">Số ngày nghỉ tối đa trong một đơn OFF</label>
                    <input
                        type="number"
                        name="max_leave_request_days"
                        min="1"
                        max="365"
                        value="{{ old('max_leave_request_days', $maxLeaveRequestDays) }}"
                        class="mt-1 w-full rounded-xl border-gray-300"
                        required
                    >
                    <p class="mt-1 text-sm text-slate-500">Ví dụ nhập 3 thì mỗi đơn nghỉ chỉ được gửi tối đa 3 ngày, tính cả ngày bắt đầu và ngày kết thúc.</p>
                    @error('max_leave_request_days')
                        <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="font-bold">Số ngày công cần để lên chính thức</label>
                    <input
                        type="number"
                        name="probation_required_workdays"
                        min="1"
                        max="365"
                        value="{{ old('probation_required_workdays', $probationRequiredWorkdays) }}"
                        class="mt-1 w-full rounded-xl border-gray-300"
                        required
                    >
                    @error('probation_required_workdays')
                        <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <section class="rounded-2xl bg-blue-50 p-4 ring-1 ring-blue-200">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Cấu hình QR đánh giá và thưởng</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Các thay đổi chỉ áp dụng cho đánh giá mới. Rating/reward cũ vẫn dùng snapshot đã lưu tại thời điểm phát sinh.
                        </p>
                    </div>

                    <label class="mt-4 flex items-start gap-3 rounded-2xl bg-white/80 p-4 ring-1 ring-blue-100">
                        <input
                            type="checkbox"
                            name="rating_require_active_attendance"
                            value="1"
                            class="mt-1 rounded border-slate-300 text-blue-600"
                            @checked(old('rating_require_active_attendance', $ratingRequireActiveAttendance))
                        >
                        <span>
                            <span class="block font-bold text-slate-900">
                                Chỉ cho đánh giá khi nhân sự đang checkin trong ca
                            </span>
                            <span class="mt-1 block text-sm text-slate-600">
                                Khi bật, khách chỉ gửi đánh giá nếu nhân sự đang checkin và còn trong ca. Khi tắt, QR vẫn nhận đánh giá theo nhân sự/chi nhánh hiện tại nhưng các lớp chặn QR, duplicate, fraud/risk và reward vẫn giữ nguyên.
                            </span>
                        </span>
                    </label>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="font-bold">Tiền thưởng mỗi lượt Tốt</label>
                            <input
                                type="number"
                                name="good_reward_amount"
                                min="0"
                                max="10000000"
                                step="1000"
                                value="{{ old('good_reward_amount', $customerRatingSettings['good_reward_amount']) }}"
                                class="mt-1 w-full rounded-xl border-gray-300"
                                required
                            >
                            <p class="mt-1 text-sm text-slate-500">Đơn vị: VNĐ. Nhập 0 nếu chưa áp dụng thưởng tiền.</p>
                            @error('good_reward_amount')
                                <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="font-bold">Lượt Tốt được thưởng tối đa/người/ngày</label>
                            <input
                                type="number"
                                name="max_rewarded_good_per_employee_per_business_date"
                                min="0"
                                max="1000"
                                value="{{ old('max_rewarded_good_per_employee_per_business_date', $customerRatingSettings['max_rewarded_good_per_employee_per_business_date']) }}"
                                class="mt-1 w-full rounded-xl border-gray-300"
                                required
                            >
                            <p class="mt-1 text-sm text-slate-500">Đơn vị: lượt good đủ điều kiện thưởng trong một ngày nghiệp vụ.</p>
                            @error('max_rewarded_good_per_employee_per_business_date')
                                <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="font-bold">Nhân sự tối đa mỗi browser/chi nhánh/ngày</label>
                            <input
                                type="number"
                                name="max_distinct_employees_per_guest_browser_per_branch_per_business_date"
                                min="1"
                                max="1000"
                                value="{{ old('max_distinct_employees_per_guest_browser_per_branch_per_business_date', $customerRatingSettings['max_distinct_employees_per_guest_browser_per_branch_per_business_date']) }}"
                                class="mt-1 w-full rounded-xl border-gray-300"
                                required
                            >
                            <p class="mt-1 text-sm text-slate-500">Đơn vị: số nhân sự khác nhau được một browser đánh giá trong cùng chi nhánh/ngày.</p>
                            @error('max_distinct_employees_per_guest_browser_per_branch_per_business_date')
                                <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </section>

                <button class="w-full rounded-2xl bg-gray-900 py-3 font-bold text-white">
                    Lưu cấu hình
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
