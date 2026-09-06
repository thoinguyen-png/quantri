@php
    use App\Support\AppNavigation;

    $user = Auth::user();
    $pendingRequestStats = $user ? AppNavigation::pendingRequestStats($user) : ['supplements' => 0, 'leaves' => 0, 'total' => 0];
    $pendingRequestCount = $pendingRequestStats['total'];
    $roleLabels = [
        'staff' => 'Nhân Viên',
        'cashier' => 'Thu Ngân',
        'manager' => 'Quản Lý',
        'admin' => 'Quản Trị',
    ];

    $role = $user?->role;

    $attendanceLinks = match ($role) {
        'admin' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công', 'hint' => 'Trang chấm công hiện tại'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR Chấm Công', 'hint' => 'Hiển thị mã QR'],
            ['route' => 'attendance-reports.index', 'active' => 'attendance-reports.*', 'label' => 'Báo Cáo Công', 'hint' => 'Xem và sửa chấm công'],
            ['route' => 'quick-attendance.index', 'active' => 'quick-attendance.*', 'label' => 'Cập Nhật Công Nhanh', 'hint' => 'Đánh dấu đủ công'],
            ['route' => 'attendance-statistics.index', 'active' => 'attendance-statistics.*', 'label' => 'Thống Kê Công', 'hint' => 'Tổng hợp theo tháng'],
            ['route' => 'shifts.index', 'active' => 'shifts.*', 'label' => 'Tạo Ca', 'hint' => 'Quản lý ca làm'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca', 'hint' => 'Phân lịch làm việc'],
        ],
        'manager' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công', 'hint' => 'Trang chấm công hiện tại'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công', 'hint' => 'Quét QR chấm công'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR Chấm Công', 'hint' => 'Hiển thị mã QR'],
            ['route' => 'attendance-reports.index', 'active' => 'attendance-reports.*', 'label' => 'Báo Cáo Công', 'hint' => 'Xem và sửa chấm công'],
            ['route' => 'quick-attendance.index', 'active' => 'quick-attendance.*', 'label' => 'Cập Nhật Công Nhanh', 'hint' => 'Đánh dấu đủ công'],
            ['route' => 'attendance-statistics.index', 'active' => 'attendance-statistics.*', 'label' => 'Thống Kê Công', 'hint' => 'Tổng hợp theo tháng'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công', 'hint' => 'Lịch sử cá nhân'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca', 'hint' => 'Phân lịch làm việc'],
        ],
        'cashier' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công', 'hint' => 'Trang chấm công hiện tại'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công', 'hint' => 'Quét QR chấm công'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR Chấm Công', 'hint' => 'Hiển thị mã QR'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công', 'hint' => 'Lịch sử cá nhân'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca', 'hint' => 'Phân lịch làm việc'],
        ],
        'staff' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công', 'hint' => 'Trang chấm công hiện tại'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công', 'hint' => 'Quét QR chấm công'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công', 'hint' => 'Lịch sử cá nhân'],
        ],
        default => [],
    };

    $requestLinks = match ($role) {
        'admin' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công', 'hint' => 'Tạo cập nhật công'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Duyệt Bổ Sung', 'hint' => 'Đơn bổ sung chờ duyệt', 'count_key' => 'supplements'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.*', 'label' => 'Duyệt OFF', 'hint' => 'Đơn nghỉ chờ duyệt', 'count_key' => 'leaves'],
        ],
        'manager' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công', 'hint' => 'Tạo cập nhật công'],
            ['route' => 'leave-requests.create', 'active' => 'leave-requests.create', 'label' => 'Đơn Xin OFF', 'hint' => 'Tạo đơn nghỉ'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Duyệt Bổ Sung', 'hint' => 'Đơn bổ sung chờ duyệt', 'count_key' => 'supplements'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.index', 'label' => 'Duyệt OFF', 'hint' => 'Đơn nghỉ chờ duyệt', 'count_key' => 'leaves'],
        ],
        'cashier', 'staff' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công', 'hint' => 'Tạo cập nhật công'],
            ['route' => 'leave-requests.create', 'active' => 'leave-requests.create', 'label' => 'Đơn Xin OFF', 'hint' => 'Tạo đơn nghỉ'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Theo Dõi Bổ Sung', 'hint' => 'Trạng thái đơn công'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.index', 'label' => 'Theo Dõi OFF', 'hint' => 'Trạng thái đơn nghỉ'],
        ],
        default => [],
    };

    $accountLinks = match ($role) {
        'admin' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân', 'hint' => $user->name],
            ['route' => 'payrolls.index', 'active' => 'payrolls.*', 'label' => 'Bảng Lương', 'hint' => 'Tổng hợp lương'],
            ['route' => 'users.index', 'active' => 'users.*', 'label' => 'Nhân Sự', 'hint' => 'Quản lý tài khoản'],
            ['route' => 'branches.index', 'active' => 'branches.*', 'label' => 'Cơ Sở', 'hint' => 'Quản lý cơ sở & logo'],
            ['route' => 'positions.index', 'active' => 'positions.*', 'label' => 'Chức Vụ', 'hint' => 'Quản lý chức danh & quyền'],
            ['route' => 'quick-onboarding.index', 'active' => 'quick-onboarding.*', 'label' => 'Quick Onboarding', 'hint' => 'Tạo nhanh lời mời nhân sự'],
            ['route' => 'ratings.risk-review.index', 'active' => 'ratings.risk-review.*', 'label' => 'Duyệt Đánh Giá', 'hint' => 'Đánh giá nghi ngờ chờ duyệt'],
            ['route' => 'face-verification-modes.index', 'active' => 'face-verification-modes.*', 'label' => 'Xác Thực Khuôn Mặt', 'hint' => 'Cấu hình bình thường/ưu tiên'],
            ['route' => 'settings.edit', 'active' => 'settings.*', 'label' => 'Cấu Hình', 'hint' => 'Thiết lập thử việc'],
        ],
        'manager' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân', 'hint' => $user->name],
            ['route' => 'my-ratings.index', 'active' => 'my-ratings.*', 'label' => 'Đánh Giá Của Tôi', 'hint' => 'Phản hồi và thưởng của bạn'],
            ['route' => 'face.register', 'active' => 'face.register', 'label' => 'Cập Nhật Gương Mặt', 'hint' => 'Đăng ký face verify'],
            ['route' => 'payrolls.index', 'active' => 'payrolls.*', 'label' => 'Bảng Lương', 'hint' => 'Tổng hợp lương'],
            ['route' => 'users.index', 'active' => 'users.*', 'label' => 'Nhân Sự', 'hint' => 'Quản lý tài khoản'],
            ['route' => 'quick-onboarding.index', 'active' => 'quick-onboarding.*', 'label' => 'Quick Onboarding', 'hint' => 'Tạo nhanh lời mời nhân sự'],
            ['route' => 'ratings.risk-review.index', 'active' => 'ratings.risk-review.*', 'label' => 'Duyệt Đánh Giá', 'hint' => 'Đánh giá nghi ngờ cùng chi nhánh'],
            ['route' => 'face-verification-modes.index', 'active' => 'face-verification-modes.*', 'label' => 'Xác Thực Khuôn Mặt', 'hint' => 'Cấu hình bình thường/ưu tiên'],
        ],
        'cashier', 'staff' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân', 'hint' => $user->name],
            ['route' => 'my-ratings.index', 'active' => 'my-ratings.*', 'label' => 'Đánh Giá Của Tôi', 'hint' => 'Phản hồi và thưởng của bạn'],
            ['route' => 'face.register', 'active' => 'face.register', 'label' => 'Cập Nhật Gương Mặt', 'hint' => 'Đăng ký face verify'],
        ],
        default => [],
    };

    $isAttendanceActive = collect($attendanceLinks)->contains(fn ($item) => request()->routeIs($item['active']));
    $isRequestActive = collect($requestLinks)->contains(fn ($item) => request()->routeIs($item['active']));
    $isAccountActive = collect($accountLinks)->contains(fn ($item) => request()->routeIs($item['active']));
@endphp

<nav x-data="{ open: false }" class="desktop-nav hidden md:block">
    <div class="app-container">
        <div class="desktop-nav__bar">
            <div class="desktop-nav__main">
                <a href="{{ route('dashboard') }}" class="desktop-nav__brand" aria-label="{{ config('app.name', 'Quản trị') }}">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    <span>{{ config('app.name', 'Quản trị') }}</span>
                </a>

                <div class="desktop-nav__links desktop-nav__links--grouped" aria-label="Điều hướng chính">
                    <a
                        href="{{ route('dashboard') }}"
                        class="desktop-nav__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                        @if (request()->routeIs('dashboard')) aria-current="page" @endif
                    >
                        <span class="desktop-nav__link-text">Home</span>
                    </a>

                    <div class="desktop-nav__group {{ $isAttendanceActive ? 'is-active' : '' }}">
                        <button type="button" class="desktop-nav__link desktop-nav__group-trigger {{ $isAttendanceActive ? 'is-active' : '' }}">
                            <span class="desktop-nav__link-text">Chấm Công</span>
                        </button>
                        <div class="desktop-nav__group-menu desktop-nav__group-menu--right">
                            @foreach ($attendanceLinks as $link)
                                <a href="{{ route($link['route']) }}" class="desktop-nav__group-item">
                                    <span>
                                        <strong>{{ $link['label'] }}</strong>
                                        <small>{{ $link['hint'] }}</small>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="desktop-nav__group {{ $isRequestActive ? 'is-active' : '' }}">
                        <button type="button" class="desktop-nav__link desktop-nav__group-trigger {{ $isRequestActive ? 'is-active' : '' }} {{ $pendingRequestCount > 0 ? 'has-badge' : '' }}" data-pending-target>
                            <span class="desktop-nav__link-text">Đơn Từ</span>
                            <span
                                class="nav-alert-badge {{ $pendingRequestCount > 0 ? '' : 'is-hidden' }}"
                                data-pending-badge
                                aria-label="{{ $pendingRequestCount }} đơn chờ xử lý"
                            >
                                <span data-pending-count>{{ $pendingRequestCount > 99 ? '99+' : $pendingRequestCount }}</span>
                            </span>
                        </button>
                        <div class="desktop-nav__group-menu desktop-nav__group-menu--right">
                            @foreach ($requestLinks as $link)
                                @php($linkCount = isset($link['count_key']) ? ($pendingRequestStats[$link['count_key']] ?? 0) : null)
                                <a href="{{ route($link['route']) }}" class="desktop-nav__group-item">
                                    <span>
                                        <strong>{{ $link['label'] }}</strong>
                                        <small>{{ $link['hint'] }}</small>
                                    </span>
                                    @if (isset($link['count_key']))
                                        <span class="desktop-nav__notify-count {{ $linkCount > 0 ? '' : 'is-hidden' }}" data-pending-type="{{ $link['count_key'] }}">
                                            {{ $linkCount > 99 ? '99+' : $linkCount }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="desktop-nav__group {{ $isAccountActive ? 'is-active' : '' }}">
                        <button type="button" class="desktop-nav__link desktop-nav__group-trigger {{ $isAccountActive ? 'is-active' : '' }}">
                            <span class="desktop-nav__link-text">Tài Khoản</span>
                        </button>
                        <div class="desktop-nav__group-menu desktop-nav__group-menu--right">
                            @foreach ($accountLinks as $link)
                                <a href="{{ route($link['route']) }}" class="desktop-nav__group-item">
                                    <span>
                                        <strong>{{ $link['label'] }}</strong>
                                        <small>{{ $link['hint'] }}</small>
                                    </span>
                                </a>
                            @endforeach

                            <form method="POST" action="{{ route('logout', absolute: false) }}">
                                @csrf
                                <button type="submit" class="desktop-nav__group-item desktop-nav__group-item--button">
                                    <span>
                                        <strong>Đăng Xuất</strong>
                                        <small>{{ $roleLabels[$user->role] ?? $user->role }}</small>
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
