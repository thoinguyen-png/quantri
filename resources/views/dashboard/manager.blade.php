<x-app-layout>
    <div class="p-4 bg-gray-100 space-y-4">
        <h1 class="text-2xl font-bold">Bảng điều khiển quản lý</h1>

        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white p-4 rounded-3xl shadow text-center">
                <div class="text-gray-500 text-sm">Nhân sự</div>
                <div class="text-3xl font-bold">{{ $staffCount }}</div>
            </div>

            <div class="bg-white p-4 rounded-3xl shadow text-center">
                <div class="text-gray-500 text-sm">Công hôm nay</div>
                <div class="text-3xl font-bold">{{ $todayAttendanceCount }}</div>
            </div>
        </div>

        <a href="{{ route('attendance.scanner') }}" class="block bg-blue-600 text-white p-5 rounded-3xl shadow font-bold text-center">
            Chấm công
        </a>

        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('qr.show') }}" class="bg-blue-600 text-white p-5 rounded-3xl shadow font-bold text-center">
                QR chấm công
            </a>

            <a href="{{ route('attendance-reports.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Báo cáo công
            </a>

            <a href="{{ route('attendance-statistics.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Thống kê công
            </a>

            <a href="{{ route('payrolls.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Bảng lương
            </a>

            <a href="{{ route('attendance-supplements.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Bổ sung công
            </a>

            <a href="{{ route('leave-requests.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Đơn xin OFF
            </a>

            <a href="{{ route('users.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Nhân sự
            </a>

            <a href="{{ route('shift-assignments.index') }}" class="bg-white p-5 rounded-3xl shadow font-bold text-center">
                Gán ca
            </a>
        </div>
    </div>
</x-app-layout>
