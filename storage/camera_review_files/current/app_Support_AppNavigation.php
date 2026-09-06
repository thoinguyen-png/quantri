<?php

namespace App\Support;

use App\Models\AttendanceSupplementRequest;
use App\Models\LeaveRequest;
use App\Models\User;

class AppNavigation
{
    public static function pendingRequestCount(User $user): int
    {
        return self::pendingRequestStats($user)['total'];
    }

    public static function pendingRequestStats(User $user): array
    {
        if (!in_array($user->role, ['admin', 'manager'], true)) {
            return [
                'supplements' => 0,
                'leaves' => 0,
                'total' => 0,
            ];
        }

        $supplements = AttendanceSupplementRequest::query()
            ->where('status', 'pending')
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->whereHas('user', function ($userQuery) use ($user) {
                    $userQuery
                        ->where('branch_id', $user->branch_id)
                        ->whereIn('role', ['staff', 'cashier']);
                });
            })
            ->count();

        $leaveRequests = LeaveRequest::query()
            ->where('status', 'pending')
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->whereHas('user', function ($userQuery) use ($user) {
                    $userQuery
                        ->where('branch_id', $user->branch_id)
                        ->whereIn('role', ['staff', 'cashier']);
                });
            })
            ->count();

        return [
            'supplements' => $supplements,
            'leaves' => $leaveRequests,
            'total' => $supplements + $leaveRequests,
        ];
    }

    public static function desktopItems(User $user): array
    {
        return match ($user->role) {
            'staff' => self::staffItems(),
            'cashier' => self::cashierItems(),
            'manager' => self::managerItems(),
            'admin' => self::adminItems(),
            default => self::profileOnlyItems(),
        };
    }

    public static function bottomItems(User $user): array
    {
        return match ($user->role) {
            'staff' => self::staffItems(),
            'cashier' => [
                self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
                self::item('attendance.scanner', 'attendance.scanner', 'scan', 'Quét Mã'),
                self::item('qr.show', 'qr.*', 'qr', 'QR'),
                self::item('attendance.history', 'attendance.history', 'history', 'Lịch Sử'),
                self::item('shift-assignments.index', 'shift-assignments.*', 'shift', 'Gán Ca'),
                self::item('users.me', 'users.me', 'profile', 'Tôi'),
            ],
            'manager' => [
                self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
                self::item('attendance.scanner', 'attendance.scanner', 'scan', 'Quét Mã'),
                self::item('attendance-statistics.index', 'attendance-statistics.*', 'stats', 'Thống Kê'),
                self::item('payrolls.index', 'payrolls.*', 'payroll', 'Lương'),
                self::item('leave-requests.index', 'leave-requests.*', 'request', 'OFF'),
                self::item('users.index', 'users.*', 'users', 'Nhân Sự'),
            ],
            'admin' => [
                self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
                self::item('attendance-statistics.index', 'attendance-statistics.*', 'stats', 'Thống Kê'),
                self::item('payrolls.index', 'payrolls.*', 'payroll', 'Lương'),
                self::item('leave-requests.index', 'leave-requests.*', 'request', 'OFF'),
                self::item('users.index', 'users.*', 'users', 'Nhân Sự'),
                self::item('shifts.index', 'shifts.*', 'shift', 'Tạo Ca'),
            ],
            default => self::profileOnlyItems(),
        };
    }

    public static function icons(): array
    {
        return [
            'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8v9.45a.75.75 0 0 1-.75.75h-5.1v-6.2h-6.3V21h-5.1a.75.75 0 0 1-.75-.75v-9.45Z"/></svg>',
            'scan' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.25 4.5h4V3h5.5v1.5h4A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Zm6.75 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm5.5-6.75a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8Z"/></svg>',
            'qr' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6.5v6.5H4V4Zm2 2v2.5h2.5V6H6Zm7.5-2H20v6.5h-6.5V4Zm2 2v2.5H18V6h-2.5ZM4 13.5h6.5V20H4v-6.5Zm2 2V18h2.5v-2.5H6Zm7.5-2H16v2.25h-2.5V13.5Zm4.25 0H20V16h-2.25v-2.5Zm-4.25 4.25h2.25V20H13.5v-2.25Zm3.75.25H20v2h-2.75v-2Z"/></svg>',
            'history' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 4.5h11A1.5 1.5 0 0 1 19 6v12a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 18V6a1.5 1.5 0 0 1 1.5-1.5Zm2 4h7v1.6h-7V8.5Zm0 3.35h7v1.6h-7v-1.6Zm0 3.35h4.8v1.6H8.5v-1.6Z"/></svg>',
            'stats' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 19.5v-15h2v13h13v2h-15Zm4-3.5V10h2.4v6H8.5Zm4.2 0V6.5h2.4V16h-2.4Zm4.2 0v-7.5h2.4V16h-2.4Z"/></svg>',
            'report' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 20.25A1.5 1.5 0 0 1 4 18.75V5.25h2v13h14v2H5.5Zm3.25-3.5v-5.8h2.8v5.8h-2.8Zm4.7 0V7.25h2.8v9.5h-2.8Zm4.7 0v-7h2.8v7h-2.8Z"/></svg>',
            'payroll' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 5.25A1.5 1.5 0 0 1 6 3.75h12a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V5.25Zm3 2.25V9h9V7.5h-9Zm0 3.25v1.5h2.25v-1.5H7.5Zm4 0v1.5h2.25v-1.5H11.5Zm4 0v1.5h1v-1.5h-1Zm-8 3.25v1.5h2.25V14H7.5Zm4 0v1.5h2.25V14H11.5Zm4 0v1.5h1V14h-1Z"/></svg>',
            'request' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 4.5h7.2L18.5 9v9.5A1.5 1.5 0 0 1 17 20H6.5A1.5 1.5 0 0 1 5 18.5V6A1.5 1.5 0 0 1 6.5 4.5Zm6.4 1.7V10h4l-4-3.8ZM8 12.25h7.5v1.5H8v-1.5Zm0 3h5.5v1.5H8v-1.5Z"/></svg>',
            'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.25a2.6 2.6 0 0 0 2.5-1.85h-5a2.6 2.6 0 0 0 2.5 1.85Zm7.15-5.55-1.6-1.95V10a5.6 5.6 0 0 0-4.25-5.45V3.8a1.3 1.3 0 1 0-2.6 0v.75A5.6 5.6 0 0 0 6.45 10v3.75l-1.6 1.95a1.05 1.05 0 0 0 .8 1.72h12.7a1.05 1.05 0 0 0 .8-1.72Z"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 11.25a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Zm7.8.25a3.1 3.1 0 1 0 0-6.2 3.1 3.1 0 0 0 0 6.2ZM2.75 19.8c.55-4.1 2.85-6.2 5.75-6.2s5.2 2.1 5.75 6.2a.7.7 0 0 1-.7.8H3.45a.7.7 0 0 1-.7-.8Zm11.8.8h5.95a.7.7 0 0 0 .7-.8c-.45-3.35-2.25-5.25-4.65-5.55a6.8 6.8 0 0 1-2 6.35Z"/></svg>',
            'shift' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm.9 4.25v4.05l3.3 1.95-.9 1.45-4.2-2.5V7.75h1.8Z"/></svg>',
            'profile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12.25a4.25 4.25 0 1 0 0-8.5 4.25 4.25 0 0 0 0 8.5Zm-7.25 7.5c.65-3.75 3.45-6 7.25-6s6.6 2.25 7.25 6a.75.75 0 0 1-.75.9h-13a.75.75 0 0 1-.75-.9Z"/></svg>',
        ];
    }

    private static function staffItems(): array
    {
        return [
            self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
            self::item('attendance.scanner', 'attendance.scanner', 'scan', 'Quét Mã'),
            self::item('attendance.history', 'attendance.history', 'history', 'Lịch Sử'),
            self::item('attendance-supplements.index', 'attendance-supplements.*', 'request', 'Bổ Sung'),
            self::item('leave-requests.index', 'leave-requests.*', 'request', 'Đơn Xin OFF'),
            self::item('users.me', 'users.me', 'profile', 'Tôi'),
        ];
    }

    private static function managerItems(): array
    {
        return [
            self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
            self::item('attendance-supplements.index', ['attendance-supplements.*', 'leave-requests.*'], 'bell', 'Thông Báo', 'pending_requests'),
            self::item('attendance.scanner', 'attendance.scanner', 'scan', 'Quét Mã'),
            self::item('qr.show', 'qr.*', 'qr', 'QR'),
            self::item('attendance-statistics.index', 'attendance-statistics.*', 'stats', 'Thống Kê'),
            self::item('attendance-reports.index', 'attendance-reports.*', 'report', 'Báo Cáo'),
            self::item('payrolls.index', 'payrolls.*', 'payroll', 'Bảng Lương'),
            self::item('attendance-supplements.index', 'attendance-supplements.*', 'request', 'Bổ Sung'),
            self::item('leave-requests.index', 'leave-requests.*', 'request', 'Đơn Xin OFF'),
            self::item('users.index', 'users.*', 'users', 'Nhân Sự'),
            self::item('shift-assignments.index', 'shift-assignments.*', 'shift', 'Gán Ca'),
        ];
    }

    private static function cashierItems(): array
    {
        return [
            self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
            self::item('attendance.scanner', 'attendance.scanner', 'scan', 'Quét Mã'),
            self::item('qr.show', 'qr.*', 'qr', 'QR'),
            self::item('attendance.history', 'attendance.history', 'history', 'Lịch Sử'),
            self::item('attendance-supplements.index', 'attendance-supplements.*', 'request', 'Bổ Sung'),
            self::item('leave-requests.index', 'leave-requests.*', 'request', 'Đơn Xin OFF'),
            self::item('shift-assignments.index', 'shift-assignments.*', 'shift', 'Gán Ca'),
            self::item('users.me', 'users.me', 'profile', 'Tôi'),
        ];
    }

    private static function adminItems(): array
    {
        return [
            self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
            self::item('attendance-supplements.index', ['attendance-supplements.*', 'leave-requests.*'], 'bell', 'Thông Báo', 'pending_requests'),
            self::item('qr.show', 'qr.*', 'qr', 'QR'),
            self::item('attendance-statistics.index', 'attendance-statistics.*', 'stats', 'Thống Kê'),
            self::item('attendance-reports.index', 'attendance-reports.*', 'report', 'Báo Cáo'),
            self::item('payrolls.index', 'payrolls.*', 'payroll', 'Bảng Lương'),
            self::item('attendance-supplements.index', 'attendance-supplements.*', 'request', 'Bổ Sung'),
            self::item('leave-requests.index', 'leave-requests.*', 'request', 'Đơn Xin OFF'),
            self::item('users.index', 'users.*', 'users', 'Nhân Sự'),
            self::item('shifts.index', 'shifts.*', 'shift', 'Tạo Ca'),
            self::item('shift-assignments.index', 'shift-assignments.*', 'shift', 'Gán Ca'),
        ];
    }

    private static function profileOnlyItems(): array
    {
        return [
            self::item('dashboard', 'dashboard', 'home', 'Trang Chủ'),
            self::item('users.me', 'users.me', 'profile', 'Tôi'),
        ];
    }

    private static function item(string $route, string|array $active, string $icon, string $label, ?string $badge = null): array
    {
        return compact('route', 'active', 'icon', 'label', 'badge');
    }
}
