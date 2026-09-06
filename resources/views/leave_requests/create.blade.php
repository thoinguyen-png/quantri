<x-app-layout>
    <div class="p-4 bg-gray-100">
        <div class="w-full bg-white rounded-3xl shadow p-5 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Đơn xin OFF</h1>
                <p class="text-sm text-gray-500">
                    Gửi yêu cầu xin nghỉ có phép để quản lý hoặc admin duyệt.
                </p>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Mỗi đơn được gửi tối đa {{ $maxLeaveRequestDays }} ngày.
                </p>
            </div>

            @if ($errors->any())
                <div class="attendance-alert attendance-alert--error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('leave-requests.store') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="font-bold">Từ ngày</label>
                        <input
                            type="date"
                            name="start_date"
                            value="{{ old('start_date', now()->toDateString()) }}"
                            @unless($allowPastLeaveRequests) min="{{ now()->toDateString() }}" @endunless
                            class="w-full mt-1 rounded-2xl border-gray-300"
                            required
                        >
                    </div>

                    <div>
                        <label class="font-bold">Đến ngày</label>
                        <input
                            type="date"
                            name="end_date"
                            value="{{ old('end_date', now()->toDateString()) }}"
                            @unless($allowPastLeaveRequests) min="{{ now()->toDateString() }}" @endunless
                            class="w-full mt-1 rounded-2xl border-gray-300"
                            required
                        >
                    </div>
                </div>

                <div>
                    <label class="font-bold">Lý do</label>
                    <textarea
                        name="reason"
                        rows="5"
                        class="w-full mt-1 rounded-2xl border-gray-300"
                        required
                        minlength="10"
                        maxlength="2000"
                        placeholder="Ví dụ: Xin OFF việc gia đình..."
                    >{{ old('reason') }}</textarea>

                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        Đơn này được hiểu là yêu cầu nghỉ có phép. Nếu được duyệt, ngày nghỉ sẽ được ghi nhận là nghỉ có phép.
                    </p>
                </div>

                <button class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold">
                    Gửi đơn
                </button>

                <a
                    href="{{ route('leave-requests.index') }}"
                    class="block text-center bg-gray-200 py-3 rounded-2xl font-bold"
                >
                    Quay lại
                </a>
            </form>
        </div>
    </div>
</x-app-layout>