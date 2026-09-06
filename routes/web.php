<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceAdjustmentLogController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AttendanceStatisticController;
use App\Http\Controllers\AttendanceSupplementRequestController;
use App\Http\Controllers\Admin\WorkHistoryController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchShiftPermissionController;
use App\Http\Controllers\BranchStaffCardLogoController;
use App\Http\Controllers\StaffCardPreviewController;
use App\Http\Controllers\StaffCardExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceController;
use App\Http\Controllers\FaceVerificationModeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MyRatingController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickAttendanceController;
use App\Http\Controllers\QuickOnboardingController;
use App\Http\Controllers\QuickOnboardingPublicController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\QrRatingController;
use App\Http\Controllers\RatingFeedController;
use App\Http\Controllers\RatingNotificationController;
use App\Http\Controllers\RatingQrController;
use App\Http\Controllers\RatingRiskReviewController;
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



Route::prefix('quick-onboarding/invite/{token}')
    ->name('quick-onboarding.public.')
    ->group(function () {
        Route::get('/', [QuickOnboardingPublicController::class, 'show'])->name('show');
        Route::post('/ocr', [QuickOnboardingPublicController::class, 'ocr'])
            ->middleware('throttle:10,1')
            ->name('ocr');
        Route::post('/preview', [QuickOnboardingPublicController::class, 'preview'])->name('preview');
        Route::post('/confirm', [QuickOnboardingPublicController::class, 'confirm'])->name('confirm');
    });

Route::get('/onboarding/{token}', function (string $token) {
    return redirect()->route('quick-onboarding.public.show', [
        'token' => $token,
    ]);
})->name('quick-onboarding.public.legacy');


// PUBLIC: khach hang mo QR de danh gia.
// Khong dat middleware auth o day. QrRatingController tu chan tai khoan
// va thiet bi noi bo bang session + cookie rating_internal_device.
Route::get('/rating-thank-you', [QrRatingController::class, 'thankYou'])
    ->name('qr-rating.thank-you');

Route::prefix('qr-rating')
    ->name('qr-rating.')
    ->group(function () {
        Route::get('/{token}', [QrRatingController::class, 'show'])
            ->name('show');

        Route::post('/{token}', [QrRatingController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
    });

Route::middleware(['auth', 'role:admin,manager'])
    ->prefix('quick-onboarding')
    ->name('quick-onboarding.')
    ->group(function () {
        Route::get('/', [QuickOnboardingController::class, 'index'])->name('index');
        Route::post('/', [QuickOnboardingController::class, 'store'])->name('store');
        Route::post('/add-more', [QuickOnboardingController::class, 'addMore'])->name('add-more');
        Route::post('/bulk-update', [QuickOnboardingController::class, 'bulkUpdate'])->name('bulk-update');
        Route::get('/sync', [QuickOnboardingController::class, 'sync'])->name('sync');

        Route::get('/entries/{entry}/qr', [QuickOnboardingController::class, 'qr'])->name('entries.qr');
        Route::post('/entries/{entry}/sent', [QuickOnboardingController::class, 'markSent'])->name('entries.sent');
        Route::post('/entries/{entry}/expected-name', [QuickOnboardingController::class, 'expectedName'])->name('entries.expected-name');
        Route::post('/entries/{entry}/role', [QuickOnboardingController::class, 'role'])->name('entries.role');
        Route::post('/entries/{entry}/avatar', [QuickOnboardingController::class, 'avatar'])->name('entries.avatar');
        Route::delete('/entries/{entry}', [QuickOnboardingController::class, 'destroyEntry'])->name('entries.destroy');
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

    Route::get('/attendance/dashboard', [DashboardController::class, 'attendance'])
        ->name('attendance.dashboard');

    Route::get('/dashboard/rating-summary', [DashboardController::class, 'ratingSummary'])
        ->name('dashboard.rating-summary');

    // NOI BO: chi xem ket qua, thong bao va quan ly QR.
    // Cac route nay KHONG phai route gui danh gia cua khach.
    Route::get('/my-ratings', [MyRatingController::class, 'index'])
        ->name('my-ratings.index');

    Route::prefix('ratings/feed')
        ->name('ratings.feed.')
        ->group(function () {
            Route::get('/', [RatingFeedController::class, 'index'])
                ->name('index');

            Route::get('/top', [RatingFeedController::class, 'top'])
                ->name('top');

            Route::get('/recent', [RatingFeedController::class, 'recent'])
                ->name('recent');
        });

    Route::prefix('rating-notifications')
        ->name('rating-notifications.')
        ->group(function () {
            Route::get('/recent', [RatingNotificationController::class, 'index'])
                ->name('recent');

            Route::patch('/{notification}/read', [RatingNotificationController::class, 'markRead'])
                ->name('read');
        });

    Route::prefix('rating-qrs/{user}')
        ->name('rating-qrs.')
        ->group(function () {
            Route::get('/', [RatingQrController::class, 'show'])
                ->name('show');

            Route::get('/svg', [RatingQrController::class, 'svg'])
                ->name('svg');

            Route::get('/download', [RatingQrController::class, 'download'])
                ->name('download');

            Route::patch('/enable', [RatingQrController::class, 'enable'])
                ->name('enable');

            Route::patch('/disable', [RatingQrController::class, 'disable'])
                ->name('disable');

            Route::post('/regenerate', [RatingQrController::class, 'regenerate'])
                ->name('regenerate');
        });

    Route::middleware('role:admin,manager')
        ->prefix('ratings/risk-review')
        ->name('ratings.risk-review.')
        ->group(function () {
            Route::get('/', [RatingRiskReviewController::class, 'index'])
                ->name('index');

            Route::post('/{customerRating}/approve', [RatingRiskReviewController::class, 'approve'])
                ->name('approve');

            Route::post('/{customerRating}/reject', [RatingRiskReviewController::class, 'reject'])
                ->name('reject');
        });

    Route::view('/qr/unsupported', 'errors.unsupported-qr')->name('qr.unsupported');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/me', [UserController::class, 'me'])->name('users.me');
    Route::put('/me', [UserController::class, 'updateMe'])->name('users.me.update');

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
            Route::get('/', [AttendanceSupplementRequestController::class, 'index'])
                ->name('index');

            Route::middleware('role:staff,cashier,manager,admin')->group(function () {
                Route::get('/create', [AttendanceSupplementRequestController::class, 'create'])
                    ->name('create');

                Route::get('/day-info', [AttendanceSupplementRequestController::class, 'dayInfo'])
                    ->name('day-info');

                Route::post('/', [AttendanceSupplementRequestController::class, 'store'])
                    ->name('store');
            });

            Route::middleware('role:admin,manager')->group(function () {
                Route::patch('/bulk-review', [AttendanceSupplementRequestController::class, 'bulkReview'])
                    ->name('bulk-review');

                Route::patch('/{attendanceSupplement}/approve', [AttendanceSupplementRequestController::class, 'approve'])
                    ->name('approve');

                Route::patch('/{attendanceSupplement}/reject', [AttendanceSupplementRequestController::class, 'reject'])
                    ->name('reject');
            });
        });

    Route::prefix('leave-requests')
        ->name('leave-requests.')
        ->group(function () {
            Route::get('/', [LeaveRequestController::class, 'index'])->name('index');

            Route::middleware('role:admin')->group(function () {
                Route::get('/admin/create', [LeaveRequestController::class, 'adminCreate'])
                    ->name('admin-create');

                Route::post('/admin', [LeaveRequestController::class, 'adminStore'])
                    ->name('admin-store');
            });

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
    Route::get('/users/{user}/staff-card/preview', [StaffCardPreviewController::class, 'show'])
        ->name('users.staff-card.preview');
    Route::get('/staff-cards/export', [StaffCardExportController::class, 'index'])
        ->name('staff-cards.export.index');
    Route::post('/staff-cards/export/preview', [StaffCardExportController::class, 'preview'])
        ->name('staff-cards.export.preview');
    Route::post('/staff-cards/export/download', [StaffCardExportController::class, 'download'])
        ->name('staff-cards.export.download');
    Route::post('/branches/{branch}/staff-card-logo', [BranchStaffCardLogoController::class, 'store'])
        ->name('branches.staff-card-logo.store');
    Route::delete('/branches/{branch}/staff-card-logo', [BranchStaffCardLogoController::class, 'destroy'])
        ->name('branches.staff-card-logo.destroy');
    Route::get('/face-verification-modes', [FaceVerificationModeController::class, 'index'])->name('face-verification-modes.index');
    Route::post('/face-verification-modes/update', [FaceVerificationModeController::class, 'update'])->name('face-verification-modes.update');

    Route::get('/attendance-reports', [AttendanceReportController::class, 'index'])->name('attendance-reports.index');
    Route::get('/attendance-reports/create', [AttendanceReportController::class, 'create'])->name('attendance-reports.create');
    Route::post('/attendance-reports', [AttendanceReportController::class, 'store'])->name('attendance-reports.store');
    Route::get('/attendance-reports/{attendance}/edit', [AttendanceReportController::class, 'edit'])->name('attendance-reports.edit');
    Route::put('/attendance-reports/{attendance}', [AttendanceReportController::class, 'update'])->name('attendance-reports.update');
    Route::patch('/attendance-reports/{attendance}/lock', [AttendanceReportController::class, 'lock'])->name('attendance-reports.lock');
    Route::patch('/attendance-reports/{attendance}/unlock', [AttendanceReportController::class, 'unlock'])->name('attendance-reports.unlock');
    Route::delete('/attendance-reports/{attendance}', [AttendanceReportController::class, 'destroy'])->name('attendance-reports.destroy');
    Route::get('/attendance-adjustment-logs', [AttendanceAdjustmentLogController::class, 'index'])->name('attendance-adjustment-logs.index');
    Route::get('/admin/quick-attendance', [QuickAttendanceController::class, 'index'])->name('quick-attendance.index');
    Route::post('/admin/quick-attendance', [QuickAttendanceController::class, 'store'])->name('quick-attendance.store');

    Route::get('/attendance-statistics', [AttendanceStatisticController::class, 'index'])->name('attendance-statistics.index');

    Route::get('/payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('/payrolls/export', [PayrollController::class, 'export'])->name('payrolls.export');

    Route::post('/shift-assignments/recalculate', [ShiftAssignmentController::class, 'recalculate'])->name('shift-assignments.recalculate');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::patch('/branches/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])
        ->name('branches.toggle-status');
    Route::resource('branches', BranchController::class)->names('branches');
    Route::resource('admin/positions', PositionController::class)->names('positions');
    Route::get('/admin/work-histories', [WorkHistoryController::class, 'index'])->name('admin.work-histories.index');
    Route::get('/admin/users/{user}/work-histories', [WorkHistoryController::class, 'user'])->name('admin.users.work-histories.index');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/branch-shift-permissions', [BranchShiftPermissionController::class, 'index'])
        ->name('branch-shift-permissions.index');

    Route::put('/branch-shift-permissions/{branch}', [BranchShiftPermissionController::class, 'update'])
        ->name('branch-shift-permissions.update');

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

// PUBLIC SHORT QR: phai dat cuoi file de khong nuot /dashboard, /login, /users...
// Chi chap nhan ma cong khai 8 ky tu cua RatingQrService.
Route::get('/{publicRatingCode}', [QrRatingController::class, 'showByCode'])
    ->where('publicRatingCode', '[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}')
    ->name('qr-rating.short.show');

Route::post('/{publicRatingCode}', [QrRatingController::class, 'storeByCode'])
    ->where('publicRatingCode', '[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}')
    ->middleware('throttle:10,1')
    ->name('qr-rating.short.store');