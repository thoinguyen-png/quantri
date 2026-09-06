<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\Setting;
use App\Models\ShiftAssignment;
use App\Services\EmploymentStatusService;
use App\Services\ShiftScheduleService;
use App\Services\AttendanceActionResolver;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceLateCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function create(
        AttendanceActionResolver $actionResolver
    ) {
        $strictMode =
            $this->attendanceStrictModeEnabled();

        return view('attendance.checkin', [
            'nextAttendanceAction' =>
                $this->nextAttendanceActionForUser(
                    auth()->user(),
                    now(),
                    $strictMode,
                    $actionResolver
                ),
        ]);
    }

    public function scanner()
    {
        return view('attendance.scanner');
    }

    public function scan($token)
    {
        $qrToken = QrToken::with('branch')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$qrToken) {
            return redirect()
                ->route('attendance.scanner')
                ->withErrors(['qr' => 'QR đã hết hạn, vui lòng quét lại.']);
        }

        if (!$qrToken->branch_id) {
            return redirect()
                ->route('attendance.scanner')
                ->withErrors(['qr' => 'QR chưa được gắn chi nhánh. Vui lòng tạo lại mã QR.']);
        }

        session([
            'qr_token' => $token,
            'qr_branch_id' => $qrToken->branch_id,
            'qr_scanned_at' => now(),
        ]);

        return redirect()->route('attendance.checkin');
    }

    public function store(
        Request $request,
        EmploymentStatusService $employmentStatus,
        AttendanceActionResolver $actionResolver,
        AttendanceCalculationService $calculator
    ) {
        $user = auth()->user();

        if (!$user || !trim((string) $user->face_descriptor)) {
            return back()->withErrors([
                'face' => 'Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.',
            ]);
        }

        $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'face_verified' => [
                'required',
                'in:1',
            ],

            'attendance_decision' => [
                'nullable',
                'in:checkin,checkout_missing_checkin',
            ],

        ]);

        $now = now();
        $strictMode = $this->attendanceStrictModeEnabled();

        $this->debugAttendance('Attendance store started', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'has_session_qr_token' => (bool) session('qr_token'),
            'attendance_strict_mode' => $strictMode,
        ]);

        $faceVerifiedAt = session('face_verified_at');
        $faceVerifiedUserId = session('face_verified_user_id');
        $faceVerificationMode = in_array($user->face_verification_mode, ['normal', 'priority'], true)
            ? $user->face_verification_mode
            : 'normal';
        $faceSessionLimitSeconds = 120;

        if (
            !$faceVerifiedAt ||
            (int) $faceVerifiedUserId !== (int) $user->id ||
            Carbon::parse($faceVerifiedAt)->diffInSeconds($now) > $faceSessionLimitSeconds
        ) {
            session()->forget([
                'face_verified_user_id',
                'face_verified_at',
                'face_verified_distance',
            ]);

            return back()->withErrors([
                'face' => 'Vui lòng xác minh khuôn mặt trước khi chấm công.',
            ]);
        }

        $sessionQrToken = session('qr_token');
        $scannedAt = session('qr_scanned_at');

        if (!$sessionQrToken || !$scannedAt) {
            $this->debugAttendance('Attendance store blocked: missing QR session', [
                'user_id' => $user->id,
                'role' => $user->role,
                'has_session_qr_token' => (bool) $sessionQrToken,
                'has_qr_scanned_at' => (bool) $scannedAt,
            ]);

            return redirect()
                ->route('attendance.scanner')
                ->withErrors(['qr' => 'Bạn chưa quét QR.']);
        }

        $qrSessionLimitSeconds = 180;

        if (Carbon::parse($scannedAt)->diffInSeconds($now) > $qrSessionLimitSeconds) {
            session()->forget(['qr_token', 'qr_branch_id', 'qr_scanned_at']);

            $this->debugAttendance('Attendance store blocked: QR session expired', [
                'user_id' => $user->id,
                'role' => $user->role,
                'qr_scanned_at' => $scannedAt,
            ]);

            return redirect()
                ->route('attendance.scanner')
                ->withErrors(['qr' => 'QR đã quá thời gian cho phép. Vui lòng quét lại.']);
        }

        /*
         * The rotating QR token only needs to be valid in scan(). Once that
         * succeeds, this server-side context has its own 180-second window
         * for face verification and GPS. The 12-second display token may
         * expire or be deleted while the user is completing those steps.
         */
        $qrBranchId = (int) session('qr_branch_id');

        if (!$qrBranchId) {
            session()->forget([
                'qr_token',
                'qr_branch_id',
                'qr_scanned_at',
            ]);

            $this->debugAttendance('Attendance store blocked: invalid scanned QR context', [
                'user_id' => $user->id,
                'role' => $user->role,
                'has_qr_branch_id' => false,
            ]);

            return redirect()
                ->route('attendance.scanner')
                ->withErrors([
                    'qr' =>
                    'QR không còn hợp lệ. Vui lòng quét lại.',
                ]);
        }

        $this->debugAttendance(
            'Attendance store QR context',
            [
                'user_id' => $user->id,
                'role' => $user->role,
                'qr_was_validated_at_scan' => true,
                'qr_branch_id' => $qrBranchId,
            ]
        );

        $latitude = (float) $request->latitude;
        $longitude = (float) $request->longitude;

        /*
         * Nhân sự có thể được điều chuyển giữa các cơ sở.
         * Ưu tiên cơ sở nơi QR code được quét nếu nhân sự đang nằm trong bán kính hợp lệ.
         * Nếu không, tìm cơ sở hoạt động gần nhất thỏa mãn bán kính GPS.
         */
        $gpsBranches = Branch::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($gpsBranches->isEmpty()) {
            return back()->withErrors([
                'gps' =>
                'Chưa có chi nhánh nào được cấu hình GPS.',
            ]);
        }

        $branch = null;
        $distance = INF;
        $allowedRadius = 100.0;

        // 1. Kiểm tra trước cơ sở gắn với mã QR đã quét
        $qrBranch = $gpsBranches->firstWhere('id', $qrBranchId);
        if ($qrBranch) {
            $qrDistance = $this->distance(
                $latitude,
                $longitude,
                (float) $qrBranch->latitude,
                (float) $qrBranch->longitude
            );
            $qrRadius = (float) max($qrBranch->gps_radius ?: 100, 50);

            if ($qrDistance <= $qrRadius) {
                $branch = $qrBranch;
                $distance = $qrDistance;
                $allowedRadius = $qrRadius;
            }
        }

        // 2. Nếu không nằm trong bán kính QR branch, kiểm tra xem có nằm trong bán kính của cơ sở hợp lệ nào khác không
        if (!$branch) {
            $matchingBranches = [];
            foreach ($gpsBranches as $candidateBranch) {
                $candidateDistance = $this->distance(
                    $latitude,
                    $longitude,
                    (float) $candidateBranch->latitude,
                    (float) $candidateBranch->longitude
                );
                $candidateRadius = (float) max($candidateBranch->gps_radius ?: 100, 50);

                if ($candidateDistance <= $candidateRadius) {
                    $matchingBranches[] = [
                        'branch' => $candidateBranch,
                        'distance' => $candidateDistance,
                        'radius' => $candidateRadius,
                    ];
                }
            }

            if (!empty($matchingBranches)) {
                usort($matchingBranches, fn ($a, $b) => $a['distance'] <=> $b['distance']);
                $branch = $matchingBranches[0]['branch'];
                $distance = $matchingBranches[0]['distance'];
                $allowedRadius = $matchingBranches[0]['radius'];
            }
        }

        // 3. Nếu vẫn không nằm trong bất kỳ cơ sở nào, tìm cơ sở gần nhất để thông báo lỗi rõ ràng
        if (!$branch) {
            $nearestBranch = $qrBranch ?? null;
            $nearestDistance = INF;
            $nearestRadius = 100.0;

            foreach ($gpsBranches as $candidateBranch) {
                $candidateDistance = $this->distance(
                    $latitude,
                    $longitude,
                    (float) $candidateBranch->latitude,
                    (float) $candidateBranch->longitude
                );
                $candidateRadius = (float) max($candidateBranch->gps_radius ?: 100, 50);

                if ($candidateDistance < $nearestDistance) {
                    $nearestDistance = $candidateDistance;
                    $nearestBranch = $candidateBranch;
                    $nearestRadius = $candidateRadius;
                }
            }

            $branch = $nearestBranch ?: $gpsBranches->first();
            $distance = $nearestDistance;
            $allowedRadius = $nearestRadius;
        }

        $this->debugAttendance(
            'Attendance store GPS checked',
            [
                'user_id' => $user->id,
                'role' => $user->role,
                'branch_id' => $branch->id,
                'qr_branch_id' => $qrBranchId,
                'branch_selection' => ($qrBranch && $branch->id === $qrBranch->id) ? 'qr_branch' : 'nearest_gps',
                'branch_latitude' =>
                $branch->latitude,
                'branch_longitude' =>
                $branch->longitude,
                'request_latitude' => $latitude,
                'request_longitude' => $longitude,
                'distance' => round($distance, 2),
                'allowed_radius' => $allowedRadius,
            ]
        );

        if ($distance > $allowedRadius) {
            return back()->withErrors([
                'gps' =>
                'Bạn đang ở ngoài phạm vi cho phép của cơ sở ' . $branch->name . '. '
                    . 'Khoảng cách ghi nhận: '
                    . number_format($distance, 0, ',', '.')
                    . ' m; bán kính cho phép: '
                    . number_format($allowedRadius, 0, ',', '.')
                    . ' m.',
            ]);
        }


        return DB::transaction(function () use (
            $request,
            $user,
            $now,
            $latitude,
            $longitude,
            $faceVerificationMode,
            $strictMode,
            $employmentStatus,
            $actionResolver,
            $calculator,
            $branch
        ) {
            $user->newQuery()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            /*
             * Chặn request bị gửi lặp ngay sau khi checkout.
             *
             * Phải lấy lại thời gian sau lockForUpdate vì
             * request thứ hai có thể đã chờ request checkout
             * đầu tiên hoàn tất.
             */
            $spamCheckAt = now();

            if (
                $this->hasRecentCheckout(
                    (int) $user->id,
                    $spamCheckAt
                )
            ) {
                $this->forgetAttendanceFlowSessions();

                return back()->withErrors([
                    'attendance' =>
                    'Bạn vừa checkout thành công, vui lòng không quét lại ngay.',
                ]);
            }

            /*
             * Không gọi luồng ca chia đoạn nữa.
             *
             * Mọi ca đều đi qua AttendanceActionResolver:
             * - attendance đang mở còn hạn -> checkout;
             * - không có attendance mở -> checkin.
             */
            /*
             * Đánh dấu các attendance đã quá hạn nhưng vẫn còn mở.
             * Việc này phải chạy trước khi resolver xét ca mới.
             */
            $markedMissingCheckout =
                $this->markExpiredOpenAttendances(
                    (int) $user->id,
                    $now,
                    $strictMode
                );

            /*
 * Resolver quyết định đây là:
 * - check-in;
 * - checkout;
 * - thiếu check-in;
 * - trường hợp cần người dùng chọn.
 */
            $resolvedAction = $actionResolver->resolveForUser(
                $user,
                $now,
                $strictMode,
                true,
            );

            $action = $resolvedAction['action'] ??
                AttendanceActionResolver::BLOCKED;
            $isUnassignedCheckin =
                $action === AttendanceActionResolver::CHECKIN_UNASSIGNED;

            $requestedDecision = (string) $request->input(
                'attendance_decision',
                '',
            );

            /*
 * Không tìm thấy ca phù hợp.
 */
            if ($action === AttendanceActionResolver::BLOCKED) {
                return back()->withErrors([
                    'attendance' => $resolvedAction['message']
                        ?? 'Không tìm thấy ca phù hợp để chấm công.',
                ]);
            }

            /*
 * Ca này đã hoàn thành.
 */
            if ($action === AttendanceActionResolver::COMPLETED) {
                $this->forgetAttendanceFlowSessions();

                return back()->withErrors([
                    'attendance' => 'Bạn đã hoàn tất chấm công cho ca này.',
                ]);
            }

            /*
 * Không có giờ vào nhưng đang ở khoảng thời gian ra ca.
 */
            if (
                in_array(
                    $action,
                    [
                        AttendanceActionResolver::CHECKOUT_MISSING_CHECKIN,
                        AttendanceActionResolver::AMBIGUOUS,
                    ],
                    true,
                )
            ) {
                $allowedDecisions = [
                    'checkout_missing_checkin',
                ];

                /*
     * Ca trong ngày có thể là:
     * - check-in muộn;
     * - hoặc quên check-in và đang checkout.
     *
     * Vì vậy cho người dùng tự chọn.
     */
                if ($action === AttendanceActionResolver::AMBIGUOUS) {
                    $allowedDecisions[] = 'checkin';
                }

                /*
     * Chưa có lựa chọn thì quay lại trang và hiện modal.
     */
                if (
                    !in_array(
                        $requestedDecision,
                        $allowedDecisions,
                        true,
                    )
                ) {
                    return back()->with(
                        'attendance_confirmation',
                        [
                            'action' => $action,

                            'can_choose_checkin' =>
                            $action ===
                                AttendanceActionResolver::AMBIGUOUS,

                            'shift_name' =>
                            $resolvedAction['shift_name']
                                ?? 'Ca làm việc',

                            'work_date' =>
                            $resolvedAction['work_date'],

                            'scheduled_start' =>
                            $resolvedAction['scheduled_start_at']
                                ?->format('H:i d/m/Y'),

                            'scheduled_end' =>
                            $resolvedAction['scheduled_end_at']
                                ?->format('H:i d/m/Y'),

                            'actual_checkout' =>
                            $now->format('H:i d/m/Y'),

                            /*
                 * Giữ lại GPS để xác nhận lần hai
                 * không cần lấy lại tọa độ.
                 */
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ],
                    );
                }

                /*
     * Người dùng xác nhận đây là giờ ra
     * nhưng bị thiếu giờ vào.
     */
                if (
                    $requestedDecision ===
                    'checkout_missing_checkin'
                ) {
                    /** @var Attendance|null $missingAttendance */
                    $missingAttendance =
                        $resolvedAction['attendance'] ?? null;

                    if (!$missingAttendance) {
                        $missingAttendance = new Attendance();

                        $missingAttendance->user_id = $user->id;
                        $missingAttendance->shift_id =
                            $resolvedAction['shift_id'];

                        /*
             * work_date luôn là ngày bắt đầu ca,
             * kể cả checkout vào sáng hôm sau.
             */
                        $missingAttendance->work_date =
                            $resolvedAction['work_date'];
                    }

                    /*
         * Không được ghi đè một attendance
         * đã có giờ vào.
         */
                    if ($missingAttendance->checkin_at) {
                        return back()->withErrors([
                            'attendance' =>
                            'Ca này đã có giờ vào. Vui lòng tải lại và chấm công lại.',
                        ]);
                    }

                    $note = trim(
                        (string) $missingAttendance->note
                    );

                    $missingNote =
                        'Đã ghi nhận giờ ra thực tế nhưng thiếu giờ vào. '
                        . 'Nhân sự cần gửi yêu cầu bổ sung công.';

                    $updateData = [
                        'branch_id' => $missingAttendance->branch_id ?? $branch->id,
                        'shift_id' =>
                            $resolvedAction['shift_id']
                            ?? $missingAttendance->shift_id,
                        'checkout_at' => $now,
                        'checkout_latitude' => $latitude,
                        'checkout_longitude' => $longitude,

                        /*
             * Chưa có giờ vào nên chưa được tính công.
             */
                        'late_minutes' => 0,
                        'worked_minutes' => 0,
                        'overtime_minutes' => 0,

                        'status' => 'missing_checkin',
                        'face_verification_mode' =>
                        $faceVerificationMode,

                        'note' => $note === ''
                            ? $missingNote
                            : $note . PHP_EOL . $missingNote,
                    ];

                    if (
                        Schema::hasColumn(
                            'attendances',
                            'overtime_hours',
                        )
                    ) {
                        $updateData['overtime_hours'] = 0;
                    }

                    if (
                        Schema::hasColumn(
                            'attendances',
                            'work_day',
                        )
                    ) {
                        $updateData['work_day'] = 0;
                    }

                    if (
                        Schema::hasColumn(
                            'attendances',
                            'work_units',
                        )
                    ) {
                        $updateData['work_units'] = 0;
                    }

                    if (
                        Schema::hasColumn(
                            'attendances',
                            'penalty_workday',
                        )
                    ) {
                        $updateData['penalty_workday'] = 0;
                    }

                    $missingAttendance->fill($updateData);
                    $missingAttendance->save();

                    $this->debugAttendance(
                        'Attendance missing checkin checkout recorded',
                        [
                            'user_id' => $user->id,
                            'attendance_id' =>
                            $missingAttendance->id,

                            'shift_id' =>
                            $missingAttendance->shift_id,

                            'work_date' =>
                            $missingAttendance->work_date
                                ? Carbon::parse(
                                    $missingAttendance
                                        ->work_date
                                )->toDateString()
                                : null,

                            'checkout_at' =>
                            $missingAttendance->checkout_at
                                ? Carbon::parse(
                                    $missingAttendance
                                        ->checkout_at
                                )->toDateTimeString()
                                : null,
                        ],
                    );

                    $this->forgetAttendanceFlowSessions();

                    return redirect()
                        ->route('dashboard')
                        ->with('attendance_modal', [
                            'type' => 'success',
                            'title' => 'Đã ghi nhận giờ ra',

                            'message' =>
                            'Hệ thống đã lưu giờ ra thực tế. '
                                . 'Ca này đang thiếu giờ vào nên chưa được tính công. '
                                . 'Vui lòng gửi yêu cầu bổ sung công.',
                        ]);
                }

                /*
     * Người dùng xác nhận đây là giờ vào
     * của ca trong ngày.
     */
                if (
                    $requestedDecision === 'checkin'
                    && $action !==
                    AttendanceActionResolver::AMBIGUOUS
                ) {
                    return back()->withErrors([
                        'attendance' =>
                        'Không thể ghi nhận giờ vào cho trường hợp này.',
                    ]);
                }
            }

            /*
 * Chuẩn hóa dữ liệu để phần checkout/checkin cũ
 * phía dưới tiếp tục hoạt động.
 */
            $openAttendance =
                $action === AttendanceActionResolver::CHECKOUT
                ? $resolvedAction['attendance']
                : null;

            /*
             * Resolver đã ưu tiên phân ca hiện tại của đúng ngày công.
             * Không bỏ assignment khi đang checkout vì attendance.shift_id
             * có thể chỉ là snapshot cũ trước khi quản lý đổi ca.
             */
            $assignment =
                $resolvedAction['assignment'] ?? null;

            $effectiveShift =
                $resolvedAction['shift']
                ?? $assignment?->shift
                ?? $openAttendance?->shift;

            $shiftId = $effectiveShift?->id
                ?? $resolvedAction['shift_id']
                ?? $assignment?->shift_id
                ?? $openAttendance?->shift_id;

            $workDate = $openAttendance
                ? (
                    $openAttendance->work_date
                    ? Carbon::parse(
                        $openAttendance->work_date
                    )->toDateString()
                    : Carbon::parse(
                        $openAttendance->checkin_at
                    )->toDateString()
                )
                : (
                    $resolvedAction['work_date']
                    ?? (
                        $assignment?->work_date
                        ? Carbon::parse(
                            $assignment->work_date
                        )->toDateString()
                        : $now->toDateString()
                    )
                );

            if (
                !$openAttendance
                && !$isUnassignedCheckin
                && (
                    !$assignment
                    || !$assignment->shift
                )
            ) {
                return back()->withErrors([
                    'attendance' =>
                    'Không tìm thấy ca phù hợp để chấm công.',
                ]);
            }

            if ($openAttendance) {
                $minimumCheckoutAt = Carbon::parse(
                    $openAttendance->checkin_at
                )->addMinutes(
                    $this->checkoutMinAfterCheckinMinutes(
                        $effectiveShift
                    )
                );

                if ($now->lessThan($minimumCheckoutAt)) {
                    return back()->withErrors([
                        'attendance' => 'Bạn vừa checkin thành công, vui lòng không quét lại ngay.',
                    ]);
                }

                $checkoutLimit = $this->getCheckoutLimit(
                    $openAttendance,
                    $now,
                    $strictMode,
                    $effectiveShift
                );

                if ($now->lessThanOrEqualTo($checkoutLimit)) {
                    /*
                     * Với attendance đã có ca, toàn bộ công chính / OT
                     * phải dùng chung AttendanceCalculationService.
                     *
                     * Rule:
                     * - Checkin sớm hơn giờ bắt đầu ca không được cộng công.
                     * - worked_minutes chỉ tính phần nằm trong khung giờ ca.
                     * - Phần sau giờ kết thúc ca được tách riêng thành OT.
                     * - OT không được dùng để bù phần công chính bị thiếu.
                     *
                     * Attendance chưa có ca vẫn giữ tổng thời gian thực tế
                     * để làm dữ liệu lịch sử, nhưng work_day và OT bằng 0.
                     */
                    $workedMinutes = Carbon::parse(
                        $openAttendance->checkin_at
                    )->diffInMinutes($now);

                    $overtimeMinutes = 0;
                    $overtimeHours = 0.0;
                    $workDay = 0.0;
                    $calculated = null;

                    if ($effectiveShift) {
                        /*
                         * Gắn checkout tạm trên model để calculator tính
                         * theo đúng thời điểm checkout hiện tại. Giá trị này
                         * sẽ được lưu cùng updateData ngay bên dưới.
                         */
                        $openAttendance->checkout_at = $now;

                        $calculated = $calculator->recalculateForShift(
                            $openAttendance,
                            $effectiveShift
                        );

                        $workedMinutes = (int) (
                            $calculated['worked_minutes']
                            ?? 0
                        );

                        $overtimeMinutes = (int) (
                            $calculated['overtime_minutes']
                            ?? 0
                        );

                        $overtimeHours = (float) (
                            $calculated['overtime_hours']
                            ?? round(
                                $overtimeMinutes / 60,
                                2
                            )
                        );

                        $workDay = (float) (
                            $calculated['work_day']
                            ?? 0
                        );
                    }

                    $updateData = [
                        /*
                         * Đồng bộ snapshot shift_id khi phân ca của ngày công
                         * đã được thay đổi sau lúc checkin.
                         */
                        'shift_id' => $effectiveShift?->id
                            ?? $openAttendance->shift_id,
                        'checkout_at' => $now,
                        'checkout_latitude' => $latitude,
                        'checkout_longitude' => $longitude,
                        'worked_minutes' => $workedMinutes,
                        'overtime_minutes' => $overtimeMinutes,
                        'status' => 'completed',
                        'face_verification_mode' => $faceVerificationMode,
                    ];

                    if (Schema::hasColumn('attendances', 'overtime_hours')) {
                        $updateData['overtime_hours'] = $overtimeHours;
                    }

                    if (
                        Schema::hasColumn(
                            'attendances',
                            'work_day'
                        )
                    ) {
                        $updateData['work_day'] =
                            $effectiveShift
                                ? $workDay
                                : 0;
                    }

                    if ($effectiveShift) {
                        if (
                            is_array($calculated)
                            && array_key_exists(
                                'late_minutes',
                                $calculated
                            )
                        ) {
                            $updateData['late_minutes'] =
                                (int) $calculated['late_minutes'];
                        }
                    } else {
                        $updateData['late_minutes'] = 0;
                    }

                    try {
                        $openAttendance->update($updateData);
                        $freshUser = $user->fresh();

                        if ($freshUser) {
                            $employmentStatus->promoteIfEligible($freshUser);
                        }

                        $this->debugAttendance('Attendance checkout updated successfully', [
                            'user_id' => $user->id,
                            'role' => $user->role,
                            'attendance_id' => $openAttendance->id,
                            'branch_id' => $branch->id,
                            'has_assignment' => (bool) $openAttendance->shift_id,
                            'worked_minutes' => $workedMinutes,
                            'overtime_minutes' => $overtimeMinutes,
                            'overtime_hours' => $overtimeHours,
                        ]);
                    } catch (\Throwable $exception) {
                        Log::error('Attendance checkout update failed', [
                            'user_id' => $user->id,
                            'role' => $user->role,
                            'attendance_id' => $openAttendance->id,
                            'error' => $exception->getMessage(),
                        ]);

                        throw $exception;
                    }

                    $this->forgetAttendanceFlowSessions();

                    $response = redirect()
                        ->route('dashboard')
                        ->with('attendance_modal', [
                            'type' => 'success',
                            'title' => 'Checkout thành công',
                            'message' => $effectiveShift
                                ? 'Ca làm của bạn đã được ghi nhận. Bạn có thể xem lại trong lịch sử chấm công.'
                                : 'Giờ vào và giờ ra đã được ghi nhận, nhưng bạn chưa được gắn ca.',
                        ]);

                    if (!$effectiveShift) {
                        $response->with(
                            'warning',
                            'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
                        );
                    }

                    return $response;
                }

                $openAttendance->update([
                    'status' => 'missing_checkout',
                ]);

                $markedMissingCheckout = true;
            }

            $this->debugAttendance('Attendance store assignment checked', [
                'user_id' => $user->id,
                'role' => $user->role,
                'assignment_exists' => (bool) $assignment,
                'shift_id' => $assignment?->shift_id,
                'work_date' => $workDate,
                'attendance_strict_mode' => $strictMode,
            ]);

            $lateMinutes = 0;

            if ($assignment && $assignment->shift) {
                $shiftStart = $this->shiftDateTimeFor($assignment->shift, Carbon::parse($workDate), 'start_at');

                if ($strictMode) {
                    $checkinOpenAt = $shiftStart->copy()->subMinutes($this->checkinOpenBeforeMinutes($assignment->shift));
                    $checkinCloseAt = $shiftStart->copy()->addMinutes($this->checkinCloseAfterMinutes($assignment->shift));

                    if ($now->lessThan($checkinOpenAt)) {
                        return back()->withErrors(['attendance' => 'Chưa đến giờ checkin của ca này.']);
                    }

                    if ($now->greaterThan($checkinCloseAt)) {
                        return back()->withErrors(['attendance' => 'Đã quá giờ checkin cho phép của ca này.']);
                    }
                }

                $lateMinutes = AttendanceLateCalculator::minutes(
                    $assignment->shift,
                    Carbon::parse($workDate),
                    $now
                );
            }

            $createData = [
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'shift_id' => $shiftId,
                'work_date' => $workDate,
                'checkin_at' => $now,
                'checkin_latitude' => $latitude,
                'checkin_longitude' => $longitude,
                'late_minutes' => $lateMinutes,
                'overtime_minutes' => 0,
                'worked_minutes' => 0,
                'status' => 'checked_in',
                'face_verification_mode' => $faceVerificationMode,
            ];

            if (Schema::hasColumn('attendances', 'overtime_hours')) {
                $createData['overtime_hours'] = 0;
            }
            if (
                Schema::hasColumn(
                    'attendances',
                    'work_day'
                )
            ) {
                $createData['work_day'] = 0;
            }

            try {
                $attendance = Attendance::create($createData);

                $this->debugAttendance('Attendance checkin created successfully', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'attendance_id' => $attendance->id,
                    'branch_id' => $branch->id,
                    'has_assignment' => (bool) $assignment,
                    'shift_id' => $shiftId,
                    'work_date' => $workDate,
                    'late_minutes' => $lateMinutes,
                ]);
            } catch (\Throwable $exception) {
                Log::error('Attendance checkin create failed', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'branch_id' => $branch->id,
                    'has_assignment' => (bool) $assignment,
                    'shift_id' => $shiftId,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            $this->forgetAttendanceFlowSessions();

            $response = redirect()
                ->route('dashboard')
                ->with('attendance_modal', [
                    'type' => 'success',
                    'title' => 'Checkin thành công',
                    'message' => $isUnassignedCheckin
                        ? 'Bạn đã checkin thành công nhưng chưa được gắn ca.'
                        : (
                            $markedMissingCheckout
                            ? 'Bạn đã checkin ca mới. Ca trước đó bị thiếu checkout và đã được ghi nhận để xử lý bổ sung.'
                            : 'Bạn đã bắt đầu ca làm. Chúc bạn một ngày làm việc hiệu quả.'
                        ),
                ]);

            if ($isUnassignedCheckin) {
                $response->with(
                    'warning',
                    $resolvedAction['warning']
                        ?? 'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.'
                );
            }

            return $response;
        });
    }

    public function history()
    {
        $attendances = Attendance::with('shift')
            ->where('user_id', auth()->id())
            ->orderByRaw('COALESCE(work_date, DATE(checkin_at)) DESC')
            ->latest('checkin_at')
            ->get();

        return view('attendance.history', compact('attendances'));
    }

    /**
     * Kiểm tra nhân viên có vừa checkout trong
     * khoảng thời gian chống spam hay không.
     */
    private function hasRecentCheckout(
        int $userId,
        Carbon $checkedAt
    ): bool {
        $cooldownSeconds =
            $this->checkoutSpamCooldownSeconds();

        if ($cooldownSeconds <= 0) {
            return false;
        }

        return Attendance::query()
            ->where('user_id', $userId)
            ->whereNotNull('checkout_at')
            ->whereBetween('checkout_at', [
                $checkedAt
                    ->copy()
                    ->subSeconds($cooldownSeconds),
                $checkedAt,
            ])
            ->exists();
    }

    /**
     * Thời gian chặn request lặp sau checkout.
     *
     * Mặc định: 10 giây.
     */
    private function checkoutSpamCooldownSeconds(): int
    {
        return max(
            0,
            (int) Setting::getValue(
                'attendance_checkout_spam_cooldown_seconds',
                '10'
            )
        );
    }

    private function forgetAttendanceFlowSessions(): void
    {
        session()->forget([
            'qr_token',
            'qr_branch_id',
            'qr_scanned_at',
            'face_verified_user_id',
            'face_verified_at',
            'face_verified_distance',
        ]);
    }




    private function markExpiredOpenAttendances(
        int $userId,
        Carbon $now,
        bool $strictMode
    ): bool {
        $marked = false;

        $attendances = Attendance::with('shift')
            ->where('user_id', $userId)
            ->whereNotNull('checkin_at')
            ->whereNull('checkout_at')
            ->whereIn(
                'work_date',
                [
                    $now->toDateString(),
                    $now->copy()
                        ->subDay()
                        ->toDateString(),
                ]
            )
            ->lockForUpdate()
            ->get();

        foreach ($attendances as $attendance) {
            $assignment =
                $this->effectiveAssignmentForAttendance(
                    $attendance
                );

            $effectiveShift =
                $assignment?->shift
                ?? $attendance->shift;

            if (
                $now->greaterThan(
                    $this->getCheckoutLimit(
                        $attendance,
                        $now,
                        $strictMode,
                        $effectiveShift
                    )
                )
            ) {
                $updates = [
                    /*
                     * Nếu phân ca hiện tại khác snapshot cũ,
                     * sửa snapshot cùng lúc khi đóng attendance quá hạn.
                     */
                    'shift_id' => $effectiveShift?->id
                        ?? $attendance->shift_id,
                    'status' => 'missing_checkout',
                    'worked_minutes' => 0,
                    'overtime_minutes' => 0,
                ];

                if (
                    Schema::hasColumn(
                        'attendances',
                        'overtime_hours'
                    )
                ) {
                    $updates['overtime_hours'] = 0;
                }

                if (
                    Schema::hasColumn(
                        'attendances',
                        'work_day'
                    )
                ) {
                    /*
     * Có ca nhưng thiếu checkout:
     * tạm ghi nhận nửa ngày công.
     *
     * Chưa được gắn ca:
     * chưa thể xác định ngày công,
     * nên vẫn giữ bằng 0.
     */
                    $updates['work_day'] = $effectiveShift
                        ? 0.5
                        : 0;
                }

                if (
                    Schema::hasColumn(
                        'attendances',
                        'penalty_workday'
                    )
                ) {
                    $updates['penalty_workday'] = 0;
                }

                $attendance->update($updates);

                $marked = true;
            }
        }

        return $marked;
    }

    /**
     * Lấy phân ca hiện hành của đúng ngày công cho một attendance cũ.
     *
     * Phân ca theo ngày được ưu tiên. Nếu không có thì dùng
     * phân ca mặc định work_date = null gần nhất đã tồn tại
     * trước ngày công đó.
     */
    private function effectiveAssignmentForAttendance(
        Attendance $attendance
    ): ?ShiftAssignment {
        $workDate = $attendance->work_date
            ? Carbon::parse(
                $attendance->work_date
            )->startOfDay()
            : (
                $attendance->checkin_at
                    ? Carbon::parse(
                        $attendance->checkin_at
                    )->startOfDay()
                    : null
            );

        if (!$workDate) {
            return null;
        }

        $datedAssignment = ShiftAssignment::with('shift')
            ->where(
                'user_id',
                $attendance->user_id
            )
            ->whereDate(
                'work_date',
                $workDate->toDateString()
            )
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($datedAssignment) {
            return $datedAssignment;
        }

        return ShiftAssignment::with('shift')
            ->where(
                'user_id',
                $attendance->user_id
            )
            ->whereNull('work_date')
            ->where(
                'created_at',
                '<=',
                $workDate
                    ->copy()
                    ->endOfDay()
            )
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function nextAttendanceActionForUser(
        $user,
        Carbon $now,
        bool $strictMode,
        AttendanceActionResolver $actionResolver
    ): ?array {
        if (!$user) {
            return null;
        }

        $resolvedAction =
            $actionResolver->resolveForUser(
                $user,
                $now,
                $strictMode,
                false
            );

        $action = $resolvedAction['action']
            ?? AttendanceActionResolver::BLOCKED;

        if (
            $action ===
            AttendanceActionResolver::CHECKOUT
        ) {
            return [
                'type' => 'info',
                'label' =>
                    'Lần chấm công tiếp theo: Giờ ra',
                'note' =>
                    !empty(
                        $resolvedAction['work_date']
                    )
                    ? 'Ca thuộc ngày công '
                        . Carbon::parse(
                            $resolvedAction['work_date']
                        )->format('d/m/Y')
                    : null,
            ];
        }

        if (
            $action ===
            AttendanceActionResolver::
                CHECKOUT_MISSING_CHECKIN
        ) {
            return [
                'type' => 'warning',
                'label' =>
                    'Lần chấm công tiếp theo: Ghi nhận giờ ra',
                'note' =>
                    'Không tìm thấy giờ vào của ca này. '
                    . 'Hệ thống sẽ yêu cầu xác nhận trước khi lưu.',
            ];
        }

        if (
            $action ===
            AttendanceActionResolver::AMBIGUOUS
        ) {
            return [
                'type' => 'warning',
                'label' =>
                    'Cần xác nhận: Giờ vào hay giờ ra',
                'note' =>
                    'Ca đang gần thời điểm kết thúc '
                    . 'nhưng chưa có giờ vào.',
            ];
        }

        if (
            $action ===
            AttendanceActionResolver::COMPLETED
        ) {
            return [
                'type' => 'success',
                'label' =>
                    'Bạn đã hoàn tất chấm công cho ca này.',
            ];
        }

        if (
            $action ===
            AttendanceActionResolver::BLOCKED
        ) {
            return [
                'type' => 'warning',
                'label' =>
                    $resolvedAction['message']
                    ?? 'Không tìm thấy ca phù hợp để chấm công.',
            ];
        }

        return [
            'type' => 'info',
            'label' =>
                'Lần chấm công tiếp theo: Giờ vào',
            'note' =>
                !empty($resolvedAction['shift_name'])
                ? 'Ca: '
                    . $resolvedAction['shift_name']
                : (
                    !empty($resolvedAction['warning'])
                    ? $resolvedAction['warning']
                    : null
                ),
        ];
    }

    private function debugAttendance(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            $scannedAt = session('qr_scanned_at');

            Log::debug($message, array_merge([
                'session_id' => session()->getId(),
                'user_id' => auth()->id(),
                'has_qr_token' => session()->has('qr_token'),
                'has_qr_branch_id' => session()->has('qr_branch_id'),
                'has_qr_scanned_at' => session()->has('qr_scanned_at'),
                'has_face_verified_user_id' => session()->has('face_verified_user_id'),
                'has_face_verified_at' => session()->has('face_verified_at'),
                'qr_scanned_at' => $scannedAt instanceof \DateTimeInterface
                    ? $scannedAt->format(DATE_ATOM)
                    : $scannedAt,
                'request_at' => now()->toIso8601String(),
                'route' => request()->route()?->getName(),
            ], $context));
        }
    }

    private function getCheckoutLimit(
        Attendance $attendance,
        Carbon $now,
        bool $strictMode,
        $effectiveShift = null
    ): Carbon {
        $checkinAt = Carbon::parse(
            $attendance->checkin_at
        );

        /*
         * Có gắn ca:
         * hạn checkout luôn dựa vào ngày công và ca được gắn.
         */
        $shift = $effectiveShift
            ?? $attendance->shift;

        if ($shift) {
            $workDate = $attendance->work_date
                ? Carbon::parse(
                    $attendance->work_date
                )->startOfDay()
                : $checkinAt->copy()->startOfDay();

            $shiftEnd = $this->shiftDateTimeFor(
                $shift,
                $workDate,
                'end_at'
            );

            $deadline = app(
                ShiftScheduleService::class
            )->checkoutDeadline(
                $shift,
                $workDate
            );

            /*
             * Hạn chung là 08:50 sáng hôm sau.
             * Nếu ca đặc biệt kết thúc sau giờ đó,
             * dùng giờ kết thúc ca.
             */
            return $deadline->greaterThan(
                $shiftEnd
            )
                ? $deadline
                : $shiftEnd;
        }

        /*
         * Không có ca:
         * giữ logic cũ để không ảnh hưởng
         * chấm công không được phân ca.
         */
        if ($checkinAt->isSameDay($now)) {
            return $checkinAt
                ->copy()
                ->endOfDay();
        }

        $cutoff = $this->overnightCutoffTime();

        return $checkinAt
            ->copy()
            ->addDay()
            ->setTime(
                $cutoff['hour'],
                $cutoff['minute']
            );
    }

    private function checkinOpenBeforeMinutes($shift): int
    {
        return max(0, (int) ($shift->checkin_open_before_minutes ?? 60));
    }

    private function checkinCloseAfterMinutes($shift): int
    {
        return max(0, (int) ($shift->checkin_close_after_minutes ?? 240));
    }

    private function checkoutMinAfterCheckinMinutes($shift): int
    {
        return max(0, (int) ($shift?->checkout_min_after_checkin_minutes ?? 3));
    }

    private function attendanceStrictModeEnabled(): bool
    {
        return Setting::getBool('attendance_strict_mode', false);
    }

    private function overnightCutoffTime(): array
    {
        $value = (string) Setting::getValue('overnight_cutoff_time', '08:50');

        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            $value = '08:50';
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return ['hour' => 8, 'minute' => 50];
        }

        return ['hour' => $hour, 'minute' => $minute];
    }

    private function shiftDateTimeFor($shift, Carbon $baseDate, string $field): Carbon
    {
        $time = Carbon::parse($shift->{$field});

        $dateTime = $baseDate->copy()->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );

        if ($field === 'end_at') {
            $start = Carbon::parse($shift->start_at);
            $end = Carbon::parse($shift->end_at);

            if ($end->format('H:i:s') <= $start->format('H:i:s')) {
                $dateTime->addDay();
            }
        }

        return $dateTime;
    }

    private function distance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a =
            sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}