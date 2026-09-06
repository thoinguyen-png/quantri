<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\Setting;
use App\Models\ShiftAssignment;
use App\Services\EmploymentStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function create()
    {
        return view('attendance.checkin');
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

    public function store(Request $request, EmploymentStatusService $employmentStatus)
    {
        $user = auth()->user();

        if (!$user || !trim((string) $user->face_descriptor)) {
            return back()->withErrors([
                'face' => 'Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.',
            ]);
        }

        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'face_verified' => ['required', 'in:1'],
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
        $faceSessionLimitSeconds = 120;

        if (
            !$faceVerifiedAt ||
            (int) $faceVerifiedUserId !== (int) $user->id ||
            $now->diffInSeconds(Carbon::parse($faceVerifiedAt)) > $faceSessionLimitSeconds
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

        if ($now->diffInSeconds(Carbon::parse($scannedAt)) > $qrSessionLimitSeconds) {
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

        $qrToken = QrToken::where('token', $sessionQrToken)->first();
        $qrBranchId = session('qr_branch_id') ?: $qrToken?->branch_id;

        $this->debugAttendance('Attendance store QR context', [
            'user_id' => $user->id,
            'role' => $user->role,
            'qr_token_exists_in_database' => (bool) $qrToken,
            'qr_branch_id' => $qrBranchId,
        ]);

        $latitude = (float) $request->latitude;
        $longitude = (float) $request->longitude;
        $branches = Branch::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($branches->isEmpty()) {
            return back()->withErrors([
                'gps' => 'Chưa có chi nhánh để kiểm tra GPS.',
            ]);
        }

        $nearest = $branches
            ->map(function (Branch $branch) use ($latitude, $longitude) {
                return [
                    'branch' => $branch,
                    'distance' => $this->distance(
                        $latitude,
                        $longitude,
                        (float) $branch->latitude,
                        (float) $branch->longitude
                    ),
                ];
            })
            ->sortBy('distance')
            ->first();

        $branch = $nearest['branch'];
        $distance = $nearest['distance'];
        $allowedRadius = (float) ($branch->gps_radius ?: 20);

        $this->debugAttendance('Attendance store GPS checked', [
            'user_id' => $user->id,
            'role' => $user->role,
            'branch_id' => $branch->id,
            'branch_latitude' => $branch->latitude,
            'branch_longitude' => $branch->longitude,
            'request_latitude' => $latitude,
            'request_longitude' => $longitude,
            'distance' => round($distance, 2),
            'allowed_radius' => $allowedRadius,
        ]);

        if ($distance > $allowedRadius) {
            return back()->withErrors([
                'gps' => 'Bạn đang ở ngoài phạm vi cho phép.',
            ]);
        }

        $openAttendance = Attendance::with('shift')
            ->where('user_id', $user->id)
            ->whereNull('checkout_at')
            ->latest('checkin_at')
            ->first();

        $markedMissingCheckout = false;

        if ($openAttendance) {
            $checkoutLimit = $this->getCheckoutLimit($openAttendance, $now);

            if ($now->lessThanOrEqualTo($checkoutLimit)) {
                $workedMinutes = Carbon::parse($openAttendance->checkin_at)
                    ->diffInMinutes($now);

                $overtimeMinutes = 0;
                $overtimeHours = 0;

                if ($openAttendance->shift) {
                    $shiftBaseDate = $openAttendance->work_date
                        ? Carbon::parse($openAttendance->work_date)
                        : Carbon::parse($openAttendance->checkin_at);
                    $shiftEnd = $this->shiftDateTimeFor(
                        $openAttendance->shift,
                        $shiftBaseDate,
                        'end_at'
                    );

                    if ($now->greaterThan($shiftEnd)) {
                        $overtimeMinutes = $shiftEnd->diffInMinutes($now);
                        $overtimeHours = round($overtimeMinutes / 60, 2);
                    }
                } else {
                    $overtimeMinutes = $workedMinutes;
                    $overtimeHours = round($overtimeMinutes / 60, 2);
                }

                $updateData = [
                    'checkout_at' => $now,
                    'checkout_latitude' => $latitude,
                    'checkout_longitude' => $longitude,
                    'worked_minutes' => $workedMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'status' => 'completed',
                ];

                if (Schema::hasColumn('attendances', 'overtime_hours')) {
                    $updateData['overtime_hours'] = $overtimeHours;
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

                return redirect()
                    ->route('dashboard')
                    ->with('attendance_modal', [
                        'type' => 'success',
                        'title' => 'Checkout thành công',
                        'message' => 'Ca làm của bạn đã được ghi nhận. Bạn có thể xem lại trong lịch sử chấm công.',
                    ]);
            }

            $openAttendance->update([
                'status' => 'missing_checkout',
            ]);

            $markedMissingCheckout = true;
        }

        $assignment = ShiftAssignment::with('shift')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->latest('updated_at')
            ->first();

        $this->debugAttendance('Attendance store assignment checked', [
            'user_id' => $user->id,
            'role' => $user->role,
            'assignment_exists' => (bool) $assignment,
            'shift_id' => $assignment?->shift_id,
            'attendance_strict_mode' => $strictMode,
        ]);

        $lateMinutes = 0;
        $shiftId = null;
        $workDate = $now->toDateString();

        if ($assignment && $assignment->shift) {
            $shiftId = $assignment->shift_id;
            $workDate = $assignment->work_date?->toDateString() ?? $workDate;
            $shiftStart = $this->shiftDateTimeFor($assignment->shift, $now, 'start_at');
            $earliestCheckinAt = $shiftStart->copy()->subHour();

            if ($strictMode && $now->lessThan($earliestCheckinAt)) {
                return back()->withErrors([
                    'attendance' => 'Bạn chỉ được chấm công sớm tối đa 1 tiếng trước ca.',
                ]);
            }

            $allowedLateTime = $shiftStart->copy()->addMinutes($assignment->shift->late_after_minutes);

            if ($now->greaterThan($allowedLateTime)) {
                $lateMinutes = $allowedLateTime->diffInMinutes($now);
            }
        }

        $createData = [
            'user_id' => $user->id,
            'shift_id' => $shiftId,
            'work_date' => $workDate,
            'checkin_at' => $now,
            'checkin_latitude' => $latitude,
            'checkin_longitude' => $longitude,
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => 0,
            'worked_minutes' => 0,
            'status' => 'checked_in',
        ];

        if (Schema::hasColumn('attendances', 'overtime_hours')) {
            $createData['overtime_hours'] = 0;
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

        return redirect()
            ->route('dashboard')
            ->with('attendance_modal', [
                'type' => 'success',
                'title' => 'Checkin thành công',
                'message' => $markedMissingCheckout
                    ? 'Bạn đã checkin ca mới. Ca trước đó bị thiếu checkout và đã được ghi nhận để xử lý bổ sung.'
                    : 'Bạn đã bắt đầu ca làm. Chúc bạn một ngày làm việc hiệu quả.',
            ]);
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

    private function debugAttendance(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            Log::debug($message, $context);
        }
    }

    private function getCheckoutLimit(Attendance $attendance, Carbon $now): Carbon
    {
        $checkinAt = Carbon::parse($attendance->checkin_at);
        $cutoff = $this->overnightCutoffTime();

        if ($checkinAt->isSameDay($now)) {
            return $checkinAt->copy()->endOfDay();
        }

        return $checkinAt->copy()
            ->addDay()
            ->setTime($cutoff['hour'], $cutoff['minute']);
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
