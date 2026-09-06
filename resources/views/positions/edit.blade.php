<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-5 p-4 sm:p-6">
        <!-- Header -->
        <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-indigo-600">Cập nhật chức vụ</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $position->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Chỉnh sửa tên tiếng Việt, tên tiếng Anh in thẻ và điều chỉnh nhóm quyền hệ thống.
                    </p>
                </div>
                <a
                    href="{{ route('positions.index') }}"
                    class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-200 transition"
                >
                    Quay lại danh sách
                </a>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form Chỉnh Sửa -->
        <form method="POST" action="{{ route('positions.update', $position) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6 space-y-5">
                <!-- Tên Tiếng Việt -->
                <div>
                    <label for="name" class="block text-sm font-black text-slate-800">
                        Tên chức vụ (Tiếng Việt) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $position->name) }}"
                        required
                        placeholder="VD: Tổng quản lý, Bếp, Quản lý CSKH..."
                        class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                    >
                    <p class="mt-1 text-xs text-slate-500">Tên hiển thị trong danh sách nhân sự, báo cáo và các bộ lọc.</p>
                </div>

                <!-- Tên Tiếng Anh -->
                <div>
                    <label for="name_en" class="block text-sm font-black text-slate-800">
                        Tên chức vụ tiếng Anh (In thẻ nhân sự)
                    </label>
                    <input
                        type="text"
                        name="name_en"
                        id="name_en"
                        value="{{ old('name_en', $position->name_en) }}"
                        placeholder="VD: General Manager, Chef, PR Manager..."
                        class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                    >
                    <p class="mt-1 text-xs text-slate-500">Tên chức danh in hoa hiển thị trên thẻ tên nhân sự (Staff Card PDF).</p>
                </div>

                <!-- Mã Slug (code) -->
                <div>
                    <label for="code" class="block text-sm font-black text-slate-800">
                        Mã định danh (Slug) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        value="{{ old('code', $position->code) }}"
                        required
                        class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 font-mono text-sm font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                    >
                    <p class="mt-1 text-xs text-slate-500">Mã duy nhất trong hệ thống.</p>
                </div>

                <!-- Nhóm quyền hệ thống -->
                <div>
                    <label for="system_role" class="block text-sm font-black text-slate-800">
                        Nhóm quyền hệ thống phân cấp <span class="text-rose-500">*</span>
                    </label>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'staff' => ['Nhân viên vận hành (Staff)', 'Chấm công cá nhân, đăng ký khuôn mặt, tạo đơn nghỉ phép/bổ sung công, nhận QR đánh giá.'],
                            'cashier' => ['Thu ngân (Cashier)', 'Quyền nhân viên + Mở mã QR động tại quầy thu ngân + Phân ca cho nhân sự cùng chi nhánh.'],
                            'manager' => ['Quản lý chi nhánh (Manager)', 'Quản trị chi nhánh, duyệt đơn nghỉ/bù công, xếp ca, xuất thẻ nhân viên chi nhánh, xem báo cáo.'],
                            'admin' => ['Ban Quản trị (Admin)', 'Toàn quyền quản trị hệ thống, quản lý đa chi nhánh, cấu hình ca làm việc, bảng lương toàn chuỗi.'],
                        ] as $roleKey => [$label, $desc])
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-indigo-300 hover:bg-indigo-50/30 transition">
                                <input
                                    type="radio"
                                    name="system_role"
                                    value="{{ $roleKey }}"
                                    @checked(old('system_role', $position->system_role) === $roleKey)
                                    class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                >
                                <div>
                                    <strong class="block text-sm font-black text-slate-900">{{ $label }}</strong>
                                    <p class="mt-1 text-xs text-slate-500 leading-relaxed">{{ $desc }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Thứ tự & Trạng thái -->
                <div class="grid gap-4 sm:grid-cols-2 border-t border-slate-100 pt-4">
                    <div>
                        <label for="sort_order" class="block text-sm font-black text-slate-800">
                            Thứ tự hiển thị
                        </label>
                        <input
                            type="number"
                            name="sort_order"
                            id="sort_order"
                            value="{{ old('sort_order', $position->sort_order) }}"
                            min="0"
                            class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                        >
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $position->is_active))
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-5 w-5"
                            >
                            <span class="text-sm font-bold text-slate-900">Cho phép sử dụng chức vụ này</span>
                        </label>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <a
                    href="{{ route('positions.index') }}"
                    class="rounded-xl bg-slate-200 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-300 transition"
                >
                    Hủy bỏ
                </a>
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-black text-white shadow-md hover:bg-indigo-700 transition"
                >
                    Cập nhật chức vụ
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
