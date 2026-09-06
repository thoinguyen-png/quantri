<x-app-layout>
    <div class="p-4 bg-gray-100">
        <div class="w-full bg-white rounded-3xl shadow p-5 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Bổ sung chấm công thủ công</h1>
                <p class="text-sm text-gray-500">Dùng khi nhân sự quên checkin/checkout và chưa có bản ghi chấm công.</p>
            </div>

            @if ($errors->any())
                <div class="p-3 bg-red-100 text-red-700 rounded-2xl">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('attendance-reports.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="font-bold">Nhân sự</label>
                    <select name="user_id" class="w-full mt-1 rounded-2xl border-gray-300" required>
                        <option value="">Chọn nhân sự</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                Mã NV {{ $user->employee_code }} - {{ $user->name }} - {{ $user->branch?->name ?? 'Chưa có chi nhánh' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="font-bold">Ca làm</label>
                    <select name="shift_id" class="w-full mt-1 rounded-2xl border-gray-300">
                        <option value="">Không chọn ca</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected(old('shift_id') == $shift->id)>
                                {{ $shift->name }} - {{ $shift->start_at->format('H:i') }} den {{ $shift->end_at->format('H:i') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="font-bold">Giờ vào</label>
                        <input
                            type="datetime-local"
                            name="checkin_at"
                            value="{{ old('checkin_at') }}"
                            class="w-full mt-1 rounded-2xl border-gray-300"
                            required
                        >
                    </div>

                    <div>
                        <label class="font-bold">Giờ ra</label>
                        <input
                            type="datetime-local"
                            name="checkout_at"
                            value="{{ old('checkout_at') }}"
                            class="w-full mt-1 rounded-2xl border-gray-300"
                        >
                    </div>
                </div>

                <div>
                    <label class="font-bold">Ghi chú</label>
                    <textarea
                        name="note"
                        rows="4"
                        class="w-full mt-1 rounded-2xl border-gray-300"
                        placeholder="Ví dụ: Nhân sự quên chấm công, đã được quản lý xác nhận."
                    >{{ old('note') }}</textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button class="bg-blue-600 text-white py-4 rounded-2xl font-bold shadow">
                        Lưu chấm công
                    </button>

                    <a href="{{ route('attendance-reports.index') }}" class="text-center bg-gray-200 py-4 rounded-2xl font-bold">
                        Quay lại
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
