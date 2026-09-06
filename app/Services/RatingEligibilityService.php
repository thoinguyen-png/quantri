<?php

namespace App\Services;

use App\Exceptions\RatingSubmissionException;
use App\Models\Attendance;
use App\Models\RatingQrToken;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

class RatingEligibilityService
{
    public function __construct(
        private readonly AttendanceShiftResolver $shiftResolver
    ) {
    }

    public function resolve(
        RatingQrToken $qrToken,
        Carbon $now
    ): array {
        $qrToken->loadMissing('user.branch');
        $employee = $qrToken->user;

        if (
            !$qrToken->enabled
            || $qrToken->revoked_at
            || !$employee
            || !$employee->rating_qr_enabled
        ) {
            throw new RatingSubmissionException(
                'qr_inactive',
                'QR danh gia khong con hieu luc.'
            );
        }

        if (
            !in_array(
                $employee->role,
                ['staff', 'cashier', 'manager'],
                true
            )
        ) {
            throw new RatingSubmissionException(
                'employee_not_allowed',
                'Nhan su nay khong co QR danh gia.'
            );
        }

        if (!$employee->branch_id) {
            throw new RatingSubmissionException(
                'employee_without_branch',
                'Nhan su chua co chi nhanh.'
            );
        }

        $strictMode = Setting::getBool(
            'attendance_strict_mode',
            false
        );

        $requireActiveAttendance =
            Setting::ratingRequiresActiveAttendance();

        $assignment = $this->shiftResolver
            ->resolveForUser(
                $employee,
                $now,
                $strictMode
            );

        $attendance = $this->activeAttendance(
            (int) $employee->id,
            $assignment,
            $now
        );

        /*
         * Ca hiện hành của đúng ngày công phải thắng
         * snapshot attendance.shift_id cũ.
         */
        $effectiveShift = $this->effectiveShift(
            $attendance,
            $assignment
        );

        if (!$requireActiveAttendance) {
            $workDate = $attendance?->work_date
                ? Carbon::parse(
                    $attendance->work_date
                )
                : (
                    $assignment?->work_date
                        ? Carbon::parse(
                            $assignment->work_date
                        )
                        : $now->copy()
                );

            return [
                'employee' => $employee,
                'branch_id' =>
                    (int) $employee->branch_id,
                'shift_id' => $effectiveShift?->id
                    ? (int) $effectiveShift->id
                    : null,
                'shift_assignment_id' =>
                    $assignment?->id,
                'attendance' => $attendance,

                /*
                 * Giữ key để tương thích với code gọi cũ.
                 * Luồng attendance_segments đã bị loại bỏ.
                 */
                'attendance_segment' => null,

                'work_date' =>
                    $workDate->toDateString(),
                'business_date' =>
                    $workDate->toDateString(),
            ];
        }

        /*
         * Điều kiện nhận đánh giá hiện chỉ dựa trên
         * attendance cha:
         *
         * - đã checkin;
         * - chưa checkout;
         * - có ca hiệu lực cho ngày công.
         *
         * Không còn kiểm tra attendance_segments.
         */
        if (
            !$attendance
            || !$effectiveShift
            || !$attendance->checkin_at
            || $attendance->checkout_at
        ) {
            throw new RatingSubmissionException(
                'attendance_not_active',
                'Nhan su chua check-in hoac da ket thuc ca.'
            );
        }

        $workDate = Carbon::parse(
            $attendance->work_date
                ?? $attendance->checkin_at
                    ->toDateString()
        );

        return [
            'employee' => $employee,
            'branch_id' =>
                (int) $employee->branch_id,
            'shift_id' =>
                (int) $effectiveShift->id,
            'shift_assignment_id' =>
                $assignment?->id,
            'attendance' => $attendance,
            'attendance_segment' => null,
            'work_date' =>
                $workDate->toDateString(),
            'business_date' =>
                $workDate->toDateString(),
        ];
    }

    private function activeAttendance(
        int $employeeId,
        ?ShiftAssignment $assignment,
        Carbon $now
    ): ?Attendance {
        $baseQuery = Attendance::with('shift')
            ->where('user_id', $employeeId)
            ->whereNotNull('checkin_at')
            ->whereNull('checkout_at');

        /*
         * Nếu resolver đã xác định được ngày công,
         * tìm attendance theo user + work_date.
         *
         * Không lọc theo attendance.shift_id vì shift_id
         * chỉ là snapshot cũ và có thể lệch sau khi đổi ca.
         */
        if ($assignment?->work_date) {
            $attendance = (clone $baseQuery)
                ->whereDate(
                    'work_date',
                    Carbon::parse(
                        $assignment->work_date
                    )->toDateString()
                )
                ->latest('id')
                ->first();

            if ($attendance) {
                return $attendance;
            }
        }

        /*
         * Fallback cho attendance đang mở của hôm nay
         * hoặc ngày hôm trước, bao gồm ca qua đêm.
         */
        return $baseQuery
            ->whereIn('work_date', [
                $now->toDateString(),
                $now->copy()
                    ->subDay()
                    ->toDateString(),
            ])
            ->latest('work_date')
            ->latest('id')
            ->first();
    }

    private function effectiveShift(
        ?Attendance $attendance,
        ?ShiftAssignment $assignment
    ): ?Shift {
        if (
            $assignment?->shift
            && $assignment->work_date
            && $attendance
        ) {
            $attendanceWorkDate = null;

            if ($attendance->work_date) {
                $attendanceWorkDate = Carbon::parse(
                    $attendance->work_date
                )->toDateString();
            } elseif ($attendance->checkin_at) {
                $attendanceWorkDate = Carbon::parse(
                    $attendance->checkin_at
                )->toDateString();
            }

            $assignmentWorkDate = Carbon::parse(
                $assignment->work_date
            )->toDateString();

            if (
                $attendanceWorkDate ===
                $assignmentWorkDate
            ) {
                return $assignment->shift;
            }
        }

        if (!$attendance && $assignment?->shift) {
            return $assignment->shift;
        }

        return $attendance?->shift
            ?? $assignment?->shift;
    }
}