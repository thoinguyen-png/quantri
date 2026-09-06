<x-app-layout>
    <div class="p-4 bg-gray-100">
        <div class="w-full bg-white rounded-3xl shadow p-5 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Sửa chấm công</h1>
                <p class="text-sm text-gray-500">{{ $attendance->user?->name }}</p>
            </div>

            @if ($attendance->is_locked)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-bold text-amber-800">
                    Bản ghi công này đã khóa. Admin đang sửa dữ liệu đã khóa và cần xác nhận khi lưu.
                </div>
            @endif

            <form method="POST" action="{{ route('attendance-reports.update', $attendance) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @if ($attendance->is_locked && auth()->user()->role === 'admin')
                    <input type="hidden" name="confirm_locked" value="1">
                @endif

                <div>
                    <label class="font-bold">Giờ vào</label>
                    <input
                        type="datetime-local"
                        name="checkin_at"
                        value="{{ optional($attendance->checkin_at)->format('Y-m-d\TH:i') }}"
                        class="w-full mt-1 rounded-2xl border-gray-300"
                        required
                    >
                </div>

                <div>
                    <label class="font-bold">Giờ ra</label>
                    <input
                        type="datetime-local"
                        name="checkout_at"
                        value="{{ optional($attendance->checkout_at)->format('Y-m-d\TH:i') }}"
                        class="w-full mt-1 rounded-2xl border-gray-300"
                    >
                </div>

                <div>
                    <label class="font-bold">Ghi chú</label>
                    <textarea name="note" rows="4" class="w-full mt-1 rounded-2xl border-gray-300">{{ $attendance->note }}</textarea>
                </div>

                <button class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">
                    Lưu thay đổi
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
