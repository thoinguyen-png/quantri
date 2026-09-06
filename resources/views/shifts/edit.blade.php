<x-app-layout>
    <div class="p-4 space-y-4">
        <h1 class="text-2xl font-bold">Sửa ca làm</h1>

        <form
            method="POST"
            action="{{ route('shifts.update', $shift) }}"
            class="space-y-4"
        >
            @csrf
            @method('PUT')

            <div>
                <label class="font-bold">Tên ca</label>
                <input
                    name="name"
                    value="{{ old('name', $shift->name) }}"
                    class="w-full mt-1 rounded-xl border-gray-300"
                    placeholder="Ví dụ: Ca sáng"
                    required
                >
                @error('name')
                    <div class="text-red-500 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="font-bold">Giờ bắt đầu</label>
                <input
                    name="start_at"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="5"
                    pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                    data-time-24h
                    value="{{ old('start_at', $shift->start_at?->format('H:i')) }}"
                    class="w-full mt-1 rounded-xl border-gray-300"
                    placeholder="10:00"
                    required
                >
                @error('start_at')
                    <div class="text-red-500 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="font-bold">Giờ kết thúc</label>
                <input
                    name="end_at"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="5"
                    pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                    data-time-24h
                    value="{{ old('end_at', $shift->end_at?->format('H:i')) }}"
                    class="w-full mt-1 rounded-xl border-gray-300"
                    placeholder="22:00"
                    required
                >
                <p class="mt-1 text-xs text-gray-500">
                    Ca qua đêm vẫn nhập bình thường, ví dụ 17:00 đến 04:00.
                </p>
                @error('end_at')
                    <div class="text-red-500 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div>
                <label class="font-bold">
                    Cho phép trễ bao nhiêu phút?
                </label>
                <input
                    name="late_after_minutes"
                    type="number"
                    value="{{ old('late_after_minutes', $shift->late_after_minutes ?? 0) }}"
                    class="w-full mt-1 rounded-xl border-gray-300"
                    min="0"
                    required
                >
                @error('late_after_minutes')
                    <div class="text-red-500 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <section class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div>
                    <h2 class="font-bold text-slate-900">
                        Ràng buộc giờ chấm công
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Chỉ áp dụng khi chế độ kiểm tra nghiêm ngặt được bật.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="font-bold">
                            Checkin sớm trước giờ vào (phút)
                        </label>
                        <input
                            name="checkin_open_before_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            value="{{ old('checkin_open_before_minutes', $shift->checkin_open_before_minutes ?? 60) }}"
                            class="w-full mt-1 rounded-xl border-gray-300"
                        >
                    </div>

                    <div>
                        <label class="font-bold">
                            Checkin trễ sau giờ vào (phút)
                        </label>
                        <input
                            name="checkin_close_after_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            value="{{ old('checkin_close_after_minutes', $shift->checkin_close_after_minutes ?? 240) }}"
                            class="w-full mt-1 rounded-xl border-gray-300"
                        >
                    </div>

                    <div>
                        <label class="font-bold">
                            Sau checkin bao nhiêu phút mới được checkout
                        </label>
                        <input
                            name="checkout_min_after_checkin_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            value="{{ old('checkout_min_after_checkin_minutes', $shift->checkout_min_after_checkin_minutes ?? 3) }}"
                            class="w-full mt-1 rounded-xl border-gray-300"
                        >
                    </div>

                    <div>
                        <label class="font-bold">
                            Checkout muộn sau giờ ra (phút)
                        </label>
                        <input
                            name="checkout_close_after_shift_end_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            value="{{ old('checkout_close_after_shift_end_minutes', $shift->checkout_close_after_shift_end_minutes) }}"
                            class="w-full mt-1 rounded-xl border-gray-300"
                            placeholder="Để trống để dùng hạn 08:50"
                        >
                    </div>
                </div>
            </section>

            <button class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold shadow">
                Lưu thay đổi
            </button>
        </form>
    </div>

    <script>
        document.querySelectorAll('[data-time-24h]').forEach(function (input) {
            input.addEventListener('input', function () {
                const digits = input.value.replace(/\D/g, '').slice(0, 4);
                input.value = digits.length > 2
                    ? digits.slice(0, 2) + ':' + digits.slice(2)
                    : digits;
            });
        });
    </script>
</x-app-layout>
