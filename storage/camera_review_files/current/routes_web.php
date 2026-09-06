<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceAdjustmentLogController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AttendanceStatisticController;
use App\Http\Controllers\AttendanceSupplementRequestController;
use App\Http\Controllers\Admin\WorkHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use App\Support\AppNavigation;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/auth/session-status', function () {
    return response()->json([
        'authenticated' => auth()->check(),
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('auth.session-status');

Route::middleware('auth')->group(function () {
    Route::get('/auth/session-keepalive', function () {
        return response()->json([
            'authenticated' => true,
            'cashier_desktop_session' => (bool) session('cashier_desktop_session'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    })->name('auth.session-keepalive');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/qr/unsupported', 'errors.unsupported-qr')->name('qr.unsupported');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/me', [UserController::class, 'me'])->name('users.me');

    Route::get('/notifications/pending-count', function () {
        $stats = AppNavigation::pendingRequestStats(auth()->user());

        return response()->json([
            'count' => $stats['total'],
            'supplements' => $stats['supplements'],
            'leaves' => $stats['leaves'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    })->middleware('role:admin,manager')->name('notifications.pending-count');

    Route::prefix('attendance-supplements')
        ->name('attendance-supplements.')
        ->group(function () {
            Route::get('/', [AttendanceSupplementRequestController::class, 'index'])->name('index');

            Route::middleware('role:staff,cashier,manager,admin')->group(function () {
                Route::get('/create', [AttendanceSupplementRequestController::class, 'create'])->name('create');
                Route::get('/day-info', [AttendanceSupplementRequestController::class, 'dayInfo'])->name('day-info');
                Route::post('/', [AttendanceSupplementRequestController::class, 'store'])->name('store');
            });

            Route::middleware('role:admin,manager')->group(function () {
                Route::patch('/{attendanceSupplement}/approve', [AttendanceSupplementRequestController::class, 'approve'])->name('approve');
                Route::patch('/{attendanceSupplement}/reject', [AttendanceSupplementRequestController::class, 'reject'])->name('reject');
            });
        });

    Route::prefix('leave-requests')
        ->name('leave-requests.')
        ->group(function () {
            Route::get('/', [LeaveRequestController::class, 'index'])->name('index');

            Route::middleware('role:staff,cashier,manager')->group(function () {
                Route::get('/create', [LeaveRequestController::class, 'create'])->name('create');
                Route::post('/', [LeaveRequestController::class, 'store'])->name('store');
            });

            Route::middleware('role:admin,manager')->group(function () {
                Route::patch('/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('approve');
                Route::patch('/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('reject');
            });
        });
});

Route::middleware(['auth', 'role:staff,cashier,manager'])->group(function () {
    Route::get('/attendance/checkin', [AttendanceController::class, 'create'])->name('attendance.checkin');
    Route::post('/attendance/checkin', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/scan/{token}', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('/attendance/scanner', [AttendanceController::class, 'scanner'])->name('attendance.scanner');
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');

    Route::get('/face/register', [FaceController::class, 'create'])->name('face.register');
    Route::post('/face/register', [FaceController::class, 'store'])->name('face.store');
    Route::post('/face/verify-pass', [FaceController::class, 'verifyPass'])->name('face.verify-pass');
});

Route::middleware(['auth', 'role:admin,manager,cashier'])->group(function () {
    Route::get('/qr', [QrController::class, 'show'])->name('qr.show');
    Route::get('/qr/generate', [QrController::class, 'generate'])->name('qr.generate');
});

Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/attendance-reports', [AttendanceReportController::class, 'index'])->name('attendance-reports.index');
    Route::get('/attendance-reports/{attendance}/edit', [AttendanceReportController::class, 'edit'])->name('attendance-reports.edit');
    Route::put('/attendance-reports/{attendance}', [AttendanceReportController::class, 'update'])->name('attendance-reports.update');
    Route::patch('/attendance-reports/{attendance}/lock', [AttendanceReportController::class, 'lock'])->name('attendance-reports.lock');
    Route::patch('/attendance-reports/{attendance}/unlock', [AttendanceReportController::class, 'unlock'])->name('attendance-reports.unlock');
    Route::delete('/attendance-reports/{attendance}', [AttendanceReportController::class, 'destroy'])->name('attendance-reports.destroy');
    Route::get('/attendance-adjustment-logs', [AttendanceAdjustmentLogController::class, 'index'])->name('attendance-adjustment-logs.index');

    Route::get('/attendance-statistics', [AttendanceStatisticController::class, 'index'])->name('attendance-statistics.index');

    Route::get('/payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('/payrolls/export', [PayrollController::class, 'export'])->name('payrolls.export');

    Route::post('/shift-assignments/recalculate', [ShiftAssignmentController::class, 'recalculate'])->name('shift-assignments.recalculate');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/work-histories', [WorkHistoryController::class, 'index'])->name('admin.work-histories.index');
    Route::get('/admin/users/{user}/work-histories', [WorkHistoryController::class, 'user'])->name('admin.users.work-histories.index');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
});

Route::middleware(['auth', 'role:admin,manager,cashier'])->group(function () {
    Route::get('/shift-assignments', [ShiftAssignmentController::class, 'index'])->name('shift-assignments.index');
    Route::get('/shift-assignments/create', [ShiftAssignmentController::class, 'create'])->name('shift-assignments.create');
    Route::post('/shift-assignments', [ShiftAssignmentController::class, 'store'])->name('shift-assignments.store');
    Route::delete('/shift-assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'destroy'])->name('shift-assignments.destroy');
});

require __DIR__ . '/auth.php';
