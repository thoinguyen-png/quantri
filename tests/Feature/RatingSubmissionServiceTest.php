<?php

namespace Tests\Feature;

use App\Exceptions\RatingSubmissionException;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\RatingQrToken;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\GuestBrowserTokenService;
use App\Services\RatingQrService;
use App\Services\RatingSubmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RatingSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_or_old_qr_token_cannot_submit_rating(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        app(RatingQrService::class)->disableForUser($employee, User::factory()->create(['role' => 'admin']));
        $this->expectRatingException('qr_inactive', fn () => $this->submit($rawToken, 'guest-a', 'good', Carbon::parse('2026-06-25 10:00:00')));

        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));
        app(RatingQrService::class)->regenerateForUser($employee, User::factory()->create(['role' => 'admin']));

        $this->expectRatingException('qr_inactive', fn () => $this->submit($rawToken, 'guest-b', 'good', Carbon::parse('2026-06-25 10:00:00')));
    }

    public function test_employee_without_checkin_cannot_receive_rating(): void
    {
        [, $rawToken] = $this->employeeWithRatingQr();

        $this->expectRatingException('attendance_not_active', fn () => $this->submit($rawToken, 'guest', 'good', Carbon::parse('2026-06-25 10:00:00')));
    }

    public function test_normal_shift_active_attendance_can_receive_rating_after_scheduled_end(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 22:00:00'));

        $rating = $this->submit($rawToken, 'guest-after-end', 'good', Carbon::parse('2026-06-25 22:00:00'));

        $this->assertSame(CustomerRating::RATING_GOOD, $rating->rating);
    }

    public function test_checked_out_normal_shift_cannot_receive_rating(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));
        $attendance->update([
            'checkout_at' => Carbon::parse('2026-06-25 17:00:00'),
            'status' => 'completed',
        ]);

        $this->expectRatingException('attendance_not_active', fn () => $this->submit($rawToken, 'guest-checked-out', 'good', Carbon::parse('2026-06-25 18:00:00')));
    }

    public function test_same_browser_employee_attendance_second_rating_is_rejected(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'guest', 'bad', Carbon::parse('2026-06-25 10:00:00'));

        $this->expectRatingException('duplicate_rating_today', fn () => $this->submit($rawToken, 'guest', 'good', Carbon::parse('2026-06-25 10:05:00')));
        $this->assertSame(1, CustomerRating::count());
    }

    public function test_browser_distinct_employee_limit_is_enforced_by_branch_and_business_date(): void
    {
        Setting::setValue('max_distinct_employees_per_guest_browser_per_branch_per_business_date', 1);
        [$employeeA, $rawTokenA] = $this->employeeWithRatingQr();
        [$employeeB, $rawTokenB] = $this->employeeWithRatingQr($employeeA->branch);
        $now = Carbon::parse('2026-06-25 10:00:00');
        $this->activeNormalAttendance($employeeA, $now);
        $this->activeNormalAttendance($employeeB, $now);

        $this->submit($rawTokenA, 'same-browser', 'bad', $now);

        $this->expectRatingException('browser_employee_limit_reached', fn () => $this->submit($rawTokenB, 'same-browser', 'bad', $now));
    }

    public function test_bad_and_average_do_not_create_reward(): void
    {
        [$employeeA, $rawTokenA] = $this->employeeWithRatingQr();
        [$employeeB, $rawTokenB] = $this->employeeWithRatingQr($employeeA->branch);
        $now = Carbon::parse('2026-06-25 10:00:00');
        $this->activeNormalAttendance($employeeA, $now);
        $this->activeNormalAttendance($employeeB, $now);

        $this->submit($rawTokenA, 'guest-a', 'bad', $now);
        $this->submit($rawTokenB, 'guest-b', 'average', $now);

        $this->assertSame(0, CustomerRatingReward::count());
    }

    public function test_good_rating_creates_reward_with_amount_snapshot(): void
    {
        Setting::setValue('good_reward_amount', 50000);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $rating = $this->submit($rawToken, 'guest', 'good', Carbon::parse('2026-06-25 10:00:00'))->fresh('reward');

        $this->assertSame(CustomerRatingReward::STATUS_ELIGIBLE, $rating->reward->status);
        $this->assertSame(50000, $rating->reward->amount);
        $this->assertSame(50000, $rating->settings_snapshot['good_reward_amount']);
        $this->assertSame(50000, $rating->reward->settings_snapshot['good_reward_amount']);
    }

    public function test_good_rating_over_daily_cap_still_stores_rating_but_rejects_reward(): void
    {
        Setting::setValue('good_reward_amount', 50000);
        Setting::setValue('max_rewarded_good_per_employee_per_business_date', 1);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'guest-1', 'good', Carbon::parse('2026-06-25 10:00:00'));
        $second = $this->submit($rawToken, 'guest-2', 'good', Carbon::parse('2026-06-25 10:05:00'), '127.0.0.2')->fresh('reward');

        $this->assertSame(2, CustomerRating::count());
        $this->assertSame(CustomerRatingReward::STATUS_REJECTED, $second->reward->status);
        $this->assertSame('daily_cap_reached', $second->reward->reason_code);
        $this->assertSame(0, $second->reward->amount);
    }

    public function test_two_good_requests_do_not_exceed_daily_reward_cap_or_duplicate_rewards(): void
    {
        Setting::setValue('max_rewarded_good_per_employee_per_business_date', 1);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'guest-1', 'good', Carbon::parse('2026-06-25 10:00:00'));
        $this->submit($rawToken, 'guest-2', 'good', Carbon::parse('2026-06-25 10:00:01'), '127.0.0.2');

        $this->assertSame(2, CustomerRatingReward::count());
        $this->assertSame(1, CustomerRatingReward::where('status', CustomerRatingReward::STATUS_ELIGIBLE)->count());
        $this->assertSame(1, CustomerRatingReward::where('status', CustomerRatingReward::STATUS_REJECTED)->where('reason_code', 'daily_cap_reached')->count());
    }

    public function test_setting_changes_do_not_mutate_existing_snapshots(): void
    {
        Setting::setValue('good_reward_amount', 50000);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $rating = $this->submit($rawToken, 'guest', 'good', Carbon::parse('2026-06-25 10:00:00'))->fresh('reward');

        Setting::setValue('good_reward_amount', 90000);

        $this->assertSame(50000, $rating->fresh()->settings_snapshot['good_reward_amount']);
        $this->assertSame(50000, $rating->reward->fresh()->settings_snapshot['good_reward_amount']);
    }

    public function test_overnight_shift_uses_work_date_as_business_date(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $workDate = Carbon::parse('2026-06-25');
        $shift = Shift::create([
            'name' => 'Ca qua ngay',
            'start_at' => '17:00:00',
            'end_at' => '01:00:00',
        ]);
        ShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => $workDate->copy()->setTime(17, 0),
            'status' => 'checked_in',
        ]);

        $rating = $this->submit($rawToken, 'guest', 'good', Carbon::parse('2026-06-26 00:30:00'));

        $this->assertSame('2026-06-25', $rating->business_date->toDateString());
        $this->assertSame('2026-06-25', $rating->settings_snapshot['business_date']);
    }

    public function test_clear_good_rating_still_auto_rewards(): void
    {
        Setting::setValue('good_reward_amount', 50000);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $rating = $this->submit($rawToken, 'clear-good', 'good', Carbon::parse('2026-06-25 10:00:00'), '10.0.0.1')->fresh('reward');

        $this->assertSame(CustomerRating::RISK_CLEAR, $rating->risk_status);
        $this->assertNull($rating->risk_reasons);
        $this->assertSame(CustomerRatingReward::STATUS_ELIGIBLE, $rating->reward->status);
        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_same_ip_same_employee_second_review_within_15_minutes_is_pending_review(): void
    {
        Setting::setValue('good_reward_amount', 50000);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'ip-employee-first', 'good', Carbon::parse('2026-06-25 10:00:00'), '10.0.0.10');
        $second = $this->submit($rawToken, 'ip-employee-second', 'good', Carbon::parse('2026-06-25 10:10:00'), '10.0.0.10')->fresh('reward');

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $second->risk_status);
        $this->assertContains('duplicate_ip_employee_15m', $second->risk_reasons);
        $this->assertNotNull($second->risk_checked_at);
        $this->assertSame(CustomerRatingReward::STATUS_RISK_REVIEW, $second->reward->status);
        $this->assertSame('fraud_review_required', $second->reward->reason_code);
        $this->assertSame(50000, $second->reward->amount);
        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_same_ip_same_employee_third_review_within_60_minutes_has_repeated_reason(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'repeat-first', 'average', Carbon::parse('2026-06-25 10:00:00'), '10.0.0.20');
        $this->submit($rawToken, 'repeat-second', 'average', Carbon::parse('2026-06-25 10:20:00'), '10.0.0.20');
        $third = $this->submit($rawToken, 'repeat-third', 'average', Carbon::parse('2026-06-25 10:50:00'), '10.0.0.20');

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $third->risk_status);
        $this->assertContains('repeated_ip_employee_60m', $third->risk_reasons);
        $this->assertSame(0, CustomerRatingReward::count());
    }

    public function test_same_ip_rating_fifth_distinct_employee_within_30_minutes_is_pending_review(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh fraud']);
        $ip = '10.0.0.30';

        for ($index = 1; $index <= 5; $index++) {
            [$employee, $rawToken] = $this->employeeWithRatingQr($branch);
            $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));
            $rating = $this->submit($rawToken, 'many-employee-' . $index, 'average', Carbon::parse('2026-06-25 10:0' . $index . ':00'), $ip);
        }

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $rating->risk_status);
        $this->assertContains('many_employees_same_ip_30m', $rating->risk_reasons);
    }

    public function test_fifth_good_rating_for_employee_within_15_minutes_is_pending_review(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        foreach ([1, 2, 3, 4] as $index) {
            $this->submit($rawToken, 'burst-good-' . $index, 'good', Carbon::parse('2026-06-25 10:0' . $index . ':00'), '10.0.1.' . $index);
        }

        $fifth = $this->submit($rawToken, 'burst-good-5', 'good', Carbon::parse('2026-06-25 10:05:00'), '10.0.1.5')->fresh('reward');

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $fifth->risk_status);
        $this->assertContains('burst_good_rating_15m', $fifth->risk_reasons);
        $this->assertSame(CustomerRatingReward::STATUS_RISK_REVIEW, $fifth->reward->status);
        $this->assertSame(3, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_pending_review_can_store_multiple_risk_reason_codes(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        foreach ([1, 2, 3, 4] as $index) {
            $this->submit($rawToken, 'multi-reason-' . $index, 'good', Carbon::parse('2026-06-25 10:0' . $index . ':00'), '10.0.2.1');
        }

        $fifth = $this->submit($rawToken, 'multi-reason-5', 'good', Carbon::parse('2026-06-25 10:05:00'), '10.0.2.1');

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $fifth->risk_status);
        $this->assertContains('duplicate_ip_employee_15m', $fifth->risk_reasons);
        $this->assertContains('repeated_ip_employee_60m', $fifth->risk_reasons);
        $this->assertContains('burst_good_rating_15m', $fifth->risk_reasons);
    }

    public function test_pending_review_average_is_stored_without_reward(): void
    {
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, Carbon::parse('2026-06-25 10:00:00'));

        $this->submit($rawToken, 'average-first', 'average', Carbon::parse('2026-06-25 10:00:00'), '10.0.3.1');
        $second = $this->submit($rawToken, 'average-second', 'average', Carbon::parse('2026-06-25 10:10:00'), '10.0.3.1');

        $this->assertSame(CustomerRating::RISK_PENDING_REVIEW, $second->risk_status);
        $this->assertContains('duplicate_ip_employee_15m', $second->risk_reasons);
        $this->assertSame(0, CustomerRatingReward::count());
    }

    private function employeeWithRatingQr(?Branch $branch = null): array
    {
        $branch ??= Branch::create(['name' => 'Chi nhanh ' . uniqid()]);
        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'rating_qr_enabled' => true,
        ]);
        $token = app(RatingQrService::class)->getOrCreateForUser($employee);

        return [$employee->fresh('branch'), app(RatingQrService::class)->rawToken($token)];
    }

    private function activeNormalAttendance(User $employee, Carbon $now): Attendance
    {
        $workDate = $now->copy()->startOfDay();
        $shift = Shift::create([
            'name' => 'Ca thuong ' . uniqid(),
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
        ]);
        ShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        return Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'checkin_at' => $now->copy()->setTime(8, 0),
            'status' => 'checked_in',
        ]);
    }

    private function submit(string $rawToken, string $guestToken, string $rating, Carbon $now, string $ipAddress = '127.0.0.1'): CustomerRating
    {
        return app(RatingSubmissionService::class)->submit(
            qrToken: $rawToken,
            guestBrowserToken: $guestToken,
            rating: $rating,
            comment: 'Noi dung danh gia',
            now: $now,
            ipAddress: $ipAddress,
            userAgent: 'Feature test'
        );
    }

    private function expectRatingException(string $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected RatingSubmissionException was not thrown.');
        } catch (RatingSubmissionException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }
}
