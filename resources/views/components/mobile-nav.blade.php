@php
    use App\Support\AppNavigation;

    $user = auth()->user();
    $icons = AppNavigation::icons();
    $role = $user?->role;
    $pendingRequestStats = $user ? AppNavigation::pendingRequestStats($user) : ['supplements' => 0, 'leaves' => 0, 'total' => 0];
    $pendingRequestCount = $pendingRequestStats['total'];

    $attendanceLinks = match ($role) {
        'admin' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR'],
            ['route' => 'attendance-reports.index', 'active' => 'attendance-reports.*', 'label' => 'Báo Cáo Công'],
            ['route' => 'quick-attendance.index', 'active' => 'quick-attendance.*', 'label' => 'Cập Nhật Công'],
            ['route' => 'attendance-statistics.index', 'active' => 'attendance-statistics.*', 'label' => 'Thống Kê Công'],
            ['route' => 'shifts.index', 'active' => 'shifts.*', 'label' => 'Tạo Ca'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca'],
        ],
        'manager' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR'],
            ['route' => 'attendance-reports.index', 'active' => 'attendance-reports.*', 'label' => 'Báo Cáo Công'],
            ['route' => 'quick-attendance.index', 'active' => 'quick-attendance.*', 'label' => 'Cập Nhật Công'],
            ['route' => 'attendance-statistics.index', 'active' => 'attendance-statistics.*', 'label' => 'Thống Kê Công'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca'],
        ],
        'cashier' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công'],
            ['route' => 'qr.show', 'active' => 'qr.*', 'label' => 'QR'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công'],
            ['route' => 'shift-assignments.index', 'active' => 'shift-assignments.*', 'label' => 'Gán Ca'],
        ],
        'staff' => [
            ['route' => 'attendance.dashboard', 'active' => 'attendance.dashboard', 'label' => 'Bảng Công'],
            ['route' => 'attendance.scanner', 'active' => 'attendance.scanner', 'label' => 'Chấm Công'],
            ['route' => 'attendance.history', 'active' => 'attendance.history', 'label' => 'Lịch Sử Công'],
        ],
        default => [],
    };

    $requestLinks = match ($role) {
        'admin' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Duyệt Bổ Sung', 'count_key' => 'supplements'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.*', 'label' => 'Duyệt OFF', 'count_key' => 'leaves'],
        ],
        'manager' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công'],
            ['route' => 'leave-requests.create', 'active' => 'leave-requests.create', 'label' => 'Đơn Xin OFF'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Duyệt Bổ Sung', 'count_key' => 'supplements'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.index', 'label' => 'Duyệt OFF', 'count_key' => 'leaves'],
        ],
        'cashier', 'staff' => [
            ['route' => 'attendance-supplements.create', 'active' => 'attendance-supplements.create', 'label' => 'Bổ Sung Công'],
            ['route' => 'leave-requests.create', 'active' => 'leave-requests.create', 'label' => 'Đơn Xin OFF'],
            ['route' => 'attendance-supplements.index', 'active' => 'attendance-supplements.index', 'label' => 'Theo Dõi Bổ Sung'],
            ['route' => 'leave-requests.index', 'active' => 'leave-requests.index', 'label' => 'Theo Dõi OFF'],
        ],
        default => [],
    };

    $accountLinks = match ($role) {
        'admin' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân'],
            ['route' => 'payrolls.index', 'active' => 'payrolls.*', 'label' => 'Bảng Lương'],
            ['route' => 'users.index', 'active' => 'users.*', 'label' => 'Nhân Sự'],
            ['route' => 'quick-onboarding.index', 'active' => 'quick-onboarding.*', 'label' => 'Quick Onboarding'],
            ['route' => 'ratings.risk-review.index', 'active' => 'ratings.risk-review.*', 'label' => 'Duyệt Đánh Giá'],
            ['route' => 'face-verification-modes.index', 'active' => 'face-verification-modes.*', 'label' => 'Xác Thực Khuôn Mặt'],
            ['route' => 'settings.edit', 'active' => 'settings.*', 'label' => 'Cấu Hình'],
        ],
        'manager' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân'],
            ['route' => 'my-ratings.index', 'active' => 'my-ratings.*', 'label' => 'Đánh Giá Của Tôi'],
            ['route' => 'face.register', 'active' => 'face.register', 'label' => 'Đăng Ký / Cập Nhật Gương Mặt'],
            ['route' => 'payrolls.index', 'active' => 'payrolls.*', 'label' => 'Bảng Lương'],
            ['route' => 'users.index', 'active' => 'users.*', 'label' => 'Nhân Sự'],
            ['route' => 'quick-onboarding.index', 'active' => 'quick-onboarding.*', 'label' => 'Quick Onboarding'],
            ['route' => 'ratings.risk-review.index', 'active' => 'ratings.risk-review.*', 'label' => 'Duyệt Đánh Giá'],
            ['route' => 'face-verification-modes.index', 'active' => 'face-verification-modes.*', 'label' => 'Xác Thực Khuôn Mặt'],
        ],
        'cashier', 'staff' => [
            ['route' => 'users.me', 'active' => 'users.me', 'label' => 'Thông Tin Cá Nhân'],
            ['route' => 'my-ratings.index', 'active' => 'my-ratings.*', 'label' => 'Đánh Giá Của Tôi'],
            ['route' => 'face.register', 'active' => 'face.register', 'label' => 'Đăng Ký / Cập Nhật Gương Mặt'],
        ],
        default => [],
    };

    $panels = [
        'attendance' => ['title' => 'Chấm Công', 'links' => $attendanceLinks],
        'requests' => ['title' => 'Đơn Từ', 'links' => $requestLinks],
        'account' => ['title' => 'Tài Khoản', 'links' => $accountLinks],
    ];

    $isAttendanceActive = collect($attendanceLinks)->contains(fn ($item) => request()->routeIs($item['active']));
    $isRequestActive = collect($requestLinks)->contains(fn ($item) => request()->routeIs($item['active']));
    $isAccountActive = collect($accountLinks)->contains(fn ($item) => request()->routeIs($item['active']));
@endphp

@if ($user)
    <div class="mobile-nav" data-mobile-nav>
        <div class="mobile-nav__sheet" data-mobile-sheet aria-hidden="true">
            <button type="button" class="mobile-nav__backdrop" data-mobile-close aria-label="Đóng menu"></button>

            @foreach ($panels as $key => $panel)
                <section class="mobile-nav__panel" data-mobile-panel="{{ $key }}" aria-label="{{ $panel['title'] }}">
                    <div class="mobile-nav__panel-list">
                        @foreach ($panel['links'] as $link)
                            <a href="{{ route($link['route']) }}" class="mobile-nav__panel-link">
                                <span>{{ $link['label'] }}</span>
                                @if (isset($link['count_key']))
                                    @php($linkCount = $pendingRequestStats[$link['count_key']] ?? 0)
                                    <span class="mobile-nav__panel-count {{ $linkCount > 0 ? '' : 'is-hidden' }}" data-pending-type="{{ $link['count_key'] }}">
                                        {{ $linkCount > 99 ? '99+' : $linkCount }}
                                    </span>
                                @endif
                            </a>
                        @endforeach

                        @if ($key === 'account')
                            <form method="POST" action="{{ route('logout', absolute: false) }}">
                                @csrf
                                <button type="submit" class="mobile-nav__panel-link mobile-nav__panel-link--button">
                                    Đăng Xuất
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <nav class="mobile-nav__bar" aria-label="Điều hướng mobile">
            <a
                href="{{ route('dashboard') }}"
                class="mobile-nav__tab {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                @if (request()->routeIs('dashboard')) aria-current="page" @endif
            >
                <span class="mobile-nav__icon" aria-hidden="true">{!! $icons['home'] !!}</span>
                <span class="mobile-nav__label">Home</span>
            </a>

            <button type="button" class="mobile-nav__tab {{ $isAttendanceActive ? 'is-active' : '' }}" data-mobile-trigger="attendance">
                <span class="mobile-nav__icon" aria-hidden="true">{!! $icons['scan'] !!}</span>
                <span class="mobile-nav__label">Chấm Công</span>
            </button>

            <button type="button" class="mobile-nav__tab {{ $isRequestActive ? 'is-active' : '' }} {{ $pendingRequestCount > 0 ? 'has-badge' : '' }}" data-mobile-trigger="requests" @if (in_array($role, ['admin', 'manager'], true)) data-pending-target @endif>
                <span class="mobile-nav__icon" aria-hidden="true">
                    {!! $icons['request'] !!}
                    @if (in_array($role, ['admin', 'manager'], true))
                        <span
                            class="mobile-nav__badge {{ $pendingRequestCount > 0 ? '' : 'is-hidden' }}"
                            data-pending-badge
                            aria-label="{{ $pendingRequestCount }} đơn chờ xử lý"
                        >
                            <span data-pending-count>{{ $pendingRequestCount > 99 ? '99+' : $pendingRequestCount }}</span>
                        </span>
                    @endif
                </span>
                <span class="mobile-nav__label">Đơn Từ</span>
            </button>

            <button type="button" class="mobile-nav__tab {{ $isAccountActive ? 'is-active' : '' }}" data-mobile-trigger="account">
                <span class="mobile-nav__icon" aria-hidden="true">{!! $icons['profile'] !!}</span>
                <span class="mobile-nav__label">Tài Khoản</span>
            </button>
        </nav>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-mobile-nav]');

            if (!root) {
                return;
            }

            const sheet = root.querySelector('[data-mobile-sheet]');
            const panels = root.querySelectorAll('[data-mobile-panel]');

            function openPanel(key) {
                panels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.dataset.mobilePanel === key);
                });

                root.classList.add('is-open');
                sheet.setAttribute('aria-hidden', 'false');
            }

            function closePanel() {
                root.classList.remove('is-open');
                sheet.setAttribute('aria-hidden', 'true');
            }

            root.querySelectorAll('[data-mobile-trigger]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openPanel(button.dataset.mobileTrigger);
                });
            });

            root.querySelectorAll('[data-mobile-close]').forEach(function (button) {
                button.addEventListener('click', closePanel);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closePanel();
                }
            });
        })();
    </script>
@endif
