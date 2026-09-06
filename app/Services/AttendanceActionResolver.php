<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

class AttendanceActionResolver
{
    public const CHECKIN = 'checkin';

    public const CHECKOUT = 'checkout';

    public const CHECKIN_UNASSIGNED = 'checkin_unassigned';

    public const CHECKOUT_MISSING_CHECKIN =
    'checkout_missing_checkin';

    public const AMBIGUOUS = 'ambiguous';

    public const COMPLETED = 'completed';

    public const BLOCKED = 'blocked';

    public function __construct(
        private readonly ShiftScheduleService $schedule,
        private readonly AttendanceShiftResolver $shiftResolver,
    ) {}

    /**
     * Chỉ quyết định hành động.
     *
     * Service này không tự tạo hoặc cập nhật attendance.
     */
    public function resolveForUser(
        $user,
        Carbon $now,
        bool $strictMode,
        bool $lockForUpdate = false,
    ): array {
        if (!$user) {
            return $this->blocked('Không tìm thấy nhân sự.');
        }

        /*
         * Ưu tiên tuyệt đối bản chấm công đang mở.
         */
        $openAttendance = $this->findOpenAttendance(
            (int) $user->id,
            $now,
            $strictMode,
            $lockForUpdate,
        );

        if ($openAttendance) {
            $openWorkDate = $openAttendance->work_date
                ? Carbon::parse(
                    $openAttendance->work_date
                )->startOfDay()
                : Carbon::parse(
                    $openAttendance->checkin_at
                )->startOfDay();

            /*
             * Nếu phân ca của ngày công đã được đổi sau khi
             * attendance được tạo, phân ca hiện tại phải thắng
             * snapshot attendance.shift_id cũ.
             */
            $openAssignment = $this->assignmentForWorkDate(
                (int) $user->id,
                $openWorkDate,
                $lockForUpdate,
            );

            $effectiveShift = $openAssignment?->shift
                ?? $openAttendance->shift;

            return [
                'action' => self::CHECKOUT,
                'requires_confirmation' => false,
                'reason' => 'open_attendance_found',

                'attendance' => $openAttendance,
                'attendance_id' => $openAttendance->id,

                'assignment' => $openAssignment,
                'assignment_id' => $openAssignment?->id,

                'shift' => $effectiveShift,
                'shift_id' => $effectiveShift?->id,
                'shift_name' => $effectiveShift?->name,

                'work_date' =>
                $openWorkDate->toDateString(),

                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
            ];
        }

        /*
         * Không có giờ vào nhưng hiện tại đang ở
         * khoảng thời gian ra ca.
         */
        $missingCheckin = $this->findMissingCheckinAction(
            (int) $user->id,
            $now,
            $strictMode,
            $lockForUpdate,
        );

        if ($missingCheckin) {
            return $missingCheckin;
        }

        /*
         * Không có attendance đang mở và cũng không
         * phải thời điểm ra ca thì mới xử lý check-in.
         */
        $assignment = $this->shiftResolver->resolveForUser(
            $user,
            $now,
            $strictMode,
        );

        if (
            !$assignment?->shift ||
            !$assignment->work_date
        ) {
            return [
                'action' => self::CHECKIN_UNASSIGNED,
                'requires_confirmation' => false,
                'reason' => 'no_shift_assignment',

                'attendance' => null,
                'attendance_id' => null,

                'assignment' => null,
                'assignment_id' => null,

                'shift' => null,
                'shift_id' => null,
                'shift_name' => null,

                /*
         * Không có ca thì ngày công tạm lấy theo
         * ngày thực hiện check-in.
         */
                'work_date' => $now->toDateString(),

                'scheduled_start_at' => null,
                'scheduled_end_at' => null,

                'warning' =>
                'Bạn chưa được gắn ca. Vui lòng liên hệ quầy thu ngân hoặc quản lý để được gán ca.',
            ];
        }

        $existingAttendance =
            $this->attendanceForAssignment(
                (int) $user->id,
                $assignment,
                $lockForUpdate,
            );

        if ($existingAttendance?->checkout_at) {
            return [
                'action' => self::COMPLETED,
                'requires_confirmation' => false,
                'reason' => 'attendance_already_completed',

                'attendance' => $existingAttendance,
                'attendance_id' => $existingAttendance->id,

                'assignment' => $assignment,
                'assignment_id' => $assignment->id,

                'shift' => $assignment->shift,
                'shift_id' => $assignment->shift_id,
                'shift_name' => $assignment->shift->name,

                'work_date' => Carbon::parse(
                    $assignment->work_date
                )->toDateString(),

                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
            ];
        }

        return [
            'action' => self::CHECKIN,
            'requires_confirmation' => false,
            'reason' => 'shift_start_window',

            'attendance' => $existingAttendance,
            'attendance_id' => $existingAttendance?->id,

            'assignment' => $assignment,
            'assignment_id' => $assignment->id,

            'shift' => $assignment->shift,
            'shift_id' => $assignment->shift_id,
            'shift_name' => $assignment->shift->name,

            'work_date' => Carbon::parse(
                $assignment->work_date
            )->toDateString(),

            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
        ];
    }

    /**
     * Tìm attendance có giờ vào nhưng chưa có giờ ra.
     */
    private function findOpenAttendance(
        int $userId,
        Carbon $now,
        bool $strictMode,
        bool $lockForUpdate,
    ): ?Attendance {
        /*
         * Ưu tiên attendance mở cũ nhất còn trong hạn checkout.
         *
         * Quy tắc bắt buộc:
         * checkin ngày 03 thì mọi lần quét trước 08:50 ngày 04
         * phải được hiểu là checkout của ngày công 03.
         *
         * Không để lịch ca ngày mới chiếm thao tác quét khi
         * attendance ngày trước vẫn còn mở và chưa quá hạn.
         */
        $query = Attendance::with('shift')
            ->where('user_id', $userId)
            ->whereNotNull('checkin_at')
            ->whereNull('checkout_at')
            ->whereIn('work_date', [
                $now->toDateString(),
                $now->copy()->subDay()->toDateString(),
            ])
            ->orderBy('work_date')
            ->orderBy('checkin_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query
            ->get()
            ->first(
                fn (Attendance $attendance): bool =>
                    $this->canCheckoutAttendanceAt(
                        $attendance,
                        $now,
                        $strictMode,
                    )
            );
    }

    /**
     * Bản hôm nay có thể checkout trong ngày.
     *
     * Bản hôm qua có thể checkout đến hạn 08:50,
     * trừ khi cửa sổ check-in của ca hôm nay đã mở
     * và ca hôm qua đã kết thúc.
     */
    private function canCheckoutAttendanceAt(
        Attendance $attendance,
        Carbon $now,
        bool $strictMode,
    ): bool {
        $workDate = $attendance->work_date
            ? Carbon::parse(
                $attendance->work_date
            )->startOfDay()
            : Carbon::parse(
                $attendance->checkin_at
            )->startOfDay();

        if ($workDate->isSameDay($now)) {
            if (!$attendance->shift) {
                return true;
            }

            $window = $this->shiftWindow(
                $attendance->shift,
                $workDate,
                $strictMode,
            );

            return $now->lessThanOrEqualTo(
                $window['checkout_close_at']
            );
        }

        if (
            !$workDate->isSameDay(
                $now->copy()->subDay()
            )
        ) {
            return false;
        }

        if (!$attendance->shift) {
            return $now->lessThanOrEqualTo(
                $this->overnightCutoffFor($workDate)
            );
        }

        $window = $this->shiftWindow(
            $attendance->shift,
            $workDate,
            $strictMode,
        );

        /*
 * Mọi ca của ngày hôm trước đều được checkout
 * đến hạn chung 08:50 sáng hôm sau.
 *
 * Không chỉ áp dụng cho ca qua đêm.
 */
        return $now->lessThanOrEqualTo(
            $window['checkout_close_at']
        );
    }

    /**
     * Phát hiện trường hợp quên chấm công vào
     * nhưng đang chấm công về.
     */
    private function findMissingCheckinAction(
        int $userId,
        Carbon $now,
        bool $strictMode,
        bool $lockForUpdate,
    ): ?array {
        $assignments = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->whereIn('work_date', [
                $now->toDateString(),
                $now->copy()->subDay()->toDateString(),
            ])
            ->orderByDesc('work_date')
            ->latest('updated_at')
            ->get();

        $candidates = $assignments
            ->filter(function (
                ShiftAssignment $assignment
            ) {
                if (
                    !$assignment->shift ||
                    !$assignment->work_date
                ) {
                    return false;
                }

                return true;
            })
            ->map(function (
                ShiftAssignment $assignment
            ) use (
                $userId,
                $now,
                $strictMode,
                $lockForUpdate,
            ) {
                $window = $this->shiftWindow(
                    $assignment->shift,
                    Carbon::parse(
                        $assignment->work_date
                    ),
                    $strictMode,
                );

                /*
 * Chỉ nhận diện checkout thiếu check-in
 * từ đúng giờ kết thúc ca.
 *
 * Trước giờ kết thúc, dù đã qua nửa đêm,
 * thao tác vẫn là check-in trễ.
 */
                if (
                    $now->lessThan($window['scheduled_end_at'])
                    || $now->greaterThan($window['checkout_close_at'])
                ) {
                    return null;
                }

                $attendance =
                    $this->attendanceForAssignment(
                        $userId,
                        $assignment,
                        $lockForUpdate,
                    );

                /*
                 * Có giờ vào chưa giờ ra đã được
                 * findOpenAttendance xử lý trước.
                 */
                if (
                    $attendance?->checkin_at &&
                    !$attendance->checkout_at
                ) {
                    return null;
                }

                return [
                    'assignment' => $assignment,
                    'attendance' => $attendance,
                    'window' => $window,

                    /*
                     * Khi có nhiều ca, ưu tiên ca có
                     * giờ kết thúc gần hiện tại nhất.
                     */
                    'distance_to_end' => abs(
                        $now->diffInSeconds(
                            $window['scheduled_end_at'],
                            false,
                        )
                    ),
                ];
            })
            ->filter()
            ->sortBy('distance_to_end')
            ->values();

        $candidate = $candidates->first();

        if (!$candidate) {
            return null;
        }

        /** @var ShiftAssignment $assignment */
        $assignment = $candidate['assignment'];

        /** @var Attendance|null $attendance */
        $attendance = $candidate['attendance'];

        $window = $candidate['window'];

        /*
         * Đã ghi nhận checkout rồi thì không tạo thêm.
         */
        if ($attendance?->checkout_at) {
            return [
                'action' => self::COMPLETED,
                'requires_confirmation' => false,
                'reason' => 'checkout_already_recorded',

                'attendance' => $attendance,
                'attendance_id' => $attendance->id,

                'assignment' => $assignment,
                'assignment_id' => $assignment->id,

                'shift' => $assignment->shift,
                'shift_id' => $assignment->shift_id,
                'shift_name' => $assignment->shift->name,

                'work_date' => Carbon::parse(
                    $assignment->work_date
                )->toDateString(),

                'scheduled_start_at' =>
                $window['scheduled_start_at'],

                'scheduled_end_at' =>
                $window['scheduled_end_at'],
            ];
        }

        $isPreviousOvernightShift =
            Carbon::parse($assignment->work_date)
            ->isSameDay(
                $now->copy()->subDay()
            )
            && $window['crosses_midnight'];

        return [
            /*
             * Ca hôm qua qua đêm:
             * biết chắc người dùng đang ở cuối ca.
             *
             * Ca trong ngày:
             * vẫn có thể là check-in muộn nên phải hỏi.
             */
            'action' => $isPreviousOvernightShift
                ? self::CHECKOUT_MISSING_CHECKIN
                : self::AMBIGUOUS,

            'requires_confirmation' => true,

            'reason' => $isPreviousOvernightShift
                ? 'previous_overnight_shift_missing_checkin'
                : 'same_day_shift_missing_checkin',

            'attendance' => $attendance,
            'attendance_id' => $attendance?->id,

            'assignment' => $assignment,
            'assignment_id' => $assignment->id,

            'shift' => $assignment->shift,
            'shift_id' => $assignment->shift_id,
            'shift_name' => $assignment->shift->name,

            /*
             * Luôn là ngày bắt đầu ca.
             */
            'work_date' => Carbon::parse(
                $assignment->work_date
            )->toDateString(),

            'scheduled_start_at' =>
            $window['scheduled_start_at'],

            'scheduled_end_at' =>
            $window['scheduled_end_at'],

            'checkout_open_at' =>
            $window['checkout_open_at'],

            'checkout_close_at' =>
            $window['checkout_close_at'],

            'crosses_midnight' =>
            $window['crosses_midnight'],
        ];
    }

    private function attendanceForAssignment(
        int $userId,
        ShiftAssignment $assignment,
        bool $lockForUpdate,
    ): ?Attendance {
        /*
         * Một nhân sự chỉ có một attendance chính cho một ngày công.
         *
         * Không lọc theo attendance.shift_id vì đây chỉ là snapshot
         * tại thời điểm attendance được tạo. Nếu quản lý đổi ca của
         * ngày đó, lọc theo shift_id cũ có thể khiến resolver tưởng
         * chưa có attendance và tạo luồng chấm công trùng.
         */
        $query = Attendance::with('shift')
            ->where('user_id', $userId)
            ->whereDate(
                'work_date',
                Carbon::parse(
                    $assignment->work_date
                )->toDateString(),
            )
            ->latest('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Lấy phân ca hiện tại của đúng ngày công.
     *
     * Ưu tiên phân ca theo ngày. Nếu không có thì dùng
     * phân ca mặc định work_date = null gần nhất đã tồn tại
     * trước ngày công đó.
     */
    private function assignmentForWorkDate(
        int $userId,
        Carbon $workDate,
        bool $lockForUpdate,
    ): ?ShiftAssignment {
        $datedQuery = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->whereDate(
                'work_date',
                $workDate->toDateString(),
            )
            ->latest('updated_at')
            ->latest('id');

        if ($lockForUpdate) {
            $datedQuery->lockForUpdate();
        }

        $datedAssignment = $datedQuery->first();

        if ($datedAssignment) {
            return $datedAssignment;
        }

        $defaultQuery = ShiftAssignment::with('shift')
            ->where('user_id', $userId)
            ->whereNull('work_date')
            ->where(
                'created_at',
                '<=',
                $workDate
                    ->copy()
                    ->endOfDay()
            )
            ->latest('created_at')
            ->latest('id');

        if ($lockForUpdate) {
            $defaultQuery->lockForUpdate();
        }

        return $defaultQuery->first();
    }

    /**
     * Chuyển giờ ca thành datetime đầy đủ.
     *
     * Ví dụ:
     * 21/07 16:00 → 22/07 06:00
     */
    private function shiftWindow(
        $shift,
        Carbon $workDate,
        bool $strictMode,
    ): array {
        $segments = $this->schedule
            ->scheduledSegments(
                $shift,
                $workDate,
            );

        $first = $segments->first();
        $last = $segments->last();

        $scheduledStartAt =
            $first['start_at']->copy();

        $scheduledEndAt =
            $last['end_at']->copy();

        $crossesMidnight =
            !$scheduledEndAt->isSameDay(
                $workDate
            );

        /*
         * Bắt đầu nhận diện quên check-in
         * từ 120 phút trước lúc hết ca.
         */
        $checkoutOpenAt =
            $scheduledEndAt
            ->copy()
            ->subMinutes(
                $this->checkoutOpenBeforeMinutes()
            );

        /*
 * Quy tắc chung:
 * mọi ca đều được checkout đến 08:50 sáng hôm sau.
 *
 * ShiftScheduleService đã chịu trách nhiệm tạo đúng
 * deadline cho cả ca trong ngày và ca qua đêm.
 */
        $configuredDeadline =
            $this->schedule->checkoutDeadline(
                $shift,
                $workDate,
            );

        /*
 * Không bao giờ để hạn checkout sớm hơn
 * giờ kết thúc ca thực tế.
 */
        $checkoutCloseAt =
            $configuredDeadline->greaterThan(
                $scheduledEndAt
            )
            ? $configuredDeadline
            : $scheduledEndAt->copy();

        return [
            'scheduled_start_at' =>
            $scheduledStartAt,

            'scheduled_end_at' =>
            $scheduledEndAt,

            'checkout_open_at' =>
            $checkoutOpenAt,

            'checkout_close_at' =>
            $checkoutCloseAt,

            'crosses_midnight' =>
            $crossesMidnight,
        ];
    }

    private function checkoutOpenBeforeMinutes(): int
    {
        return max(
            0,
            (int) Setting::getValue(
                'attendance_checkout_open_before_minutes',
                '120',
            ),
        );
    }


    private function overnightCutoffFor(
        Carbon $workDate
    ): Carbon {
        $value = (string) Setting::getValue(
            'overnight_cutoff_time',
            '08:50',
        );

        if (
            !preg_match(
                '/^\d{2}:\d{2}$/',
                $value
            )
        ) {
            $value = '08:50';
        }

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $value),
        );

        if (
            $hour < 0 ||
            $hour > 23 ||
            $minute < 0 ||
            $minute > 59
        ) {
            $hour = 8;
            $minute = 50;
        }

        return $workDate
            ->copy()
            ->addDay()
            ->setTime($hour, $minute);
    }

    private function blocked(
        string $message
    ): array {
        return [
            'action' => self::BLOCKED,
            'requires_confirmation' => false,
            'reason' => 'blocked',
            'message' => $message,

            'attendance' => null,
            'attendance_id' => null,

            'assignment' => null,
            'assignment_id' => null,

            'shift' => null,
            'shift_id' => null,
            'shift_name' => null,

            'work_date' => null,

            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
        ];
    }
}