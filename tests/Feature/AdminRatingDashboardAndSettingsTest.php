<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\RatingQrService;
use App\Services\RatingSubmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRatingDashboardAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_shows_global_rating_data_without_sensitive_audit_fields(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $staffA = User::factory()->create(['name' => 'Nhan su A', 'role' => 'staff', 'branch_id' => $branchA->id, 'rating_qr_enabled' => true]);
        $staffB = User::factory()->create(['name' => 'Nhan su B', 'role' => 'cashier', 'branch_id' => $branchB->id, 'rating_qr_enabled' => true]);

        $good = $this->rating($staffA, $branchA, CustomerRating::RATING_GOOD, 'Tot toan he thong', $now, 'guest-a');
        $bad = $this->rating($staffB, $branchB, CustomerRating::RATING_BAD, 'Can xu ly toan he thong', $now->copy()->subMinute(), 'guest-b');
        $this->reward($good, CustomerRatingReward::STATUS_ELIGIBLE, 50000);
        $this->reward($bad, CustomerRatingReward::STATUS_REJECTED, 0);

        $response = $this->actingAs($admin)->get(route('attendance.dashboard'));

        $response->assertOk();
        $response->assertSee('Thống kê QR đánh giá toàn hệ thống');
        $response->assertSee('Tot toan he thong');
        $response->assertSee('Can xu ly toan he thong');
        $response->assertSee('Chi nhanh A');
        $response->assertSee('Chi nhanh B');
        $response->assertSee('1 Tốt');
        $response->assertSee('1 Tệ');
        $response->assertSee('50.000đ');
        $response->assertSee(route('rating-qrs.show', $staffA, absolute: false), false);
        $response->assertSee(route('rating-qrs.download', $staffB, absolute: false), false);
        $response->assertSee('data-admin-list-view', false);
        $response->assertSee('data-admin-detail-view hidden', false);

        $response->assertDontSee('guest-a');
        $response->assertDontSee('ip-audit');
        $response->assertDontSee('ua-audit');
        $response->assertDontSee('/qr-rating/', false);
        $response->assertDontSee('Tên khách');
        $response->assertDontSee('Mã khách');
        $response->assertDontSee('Số phòng');
    }

    public function test_only_admin_can_access_settings_and_manager_does_not_see_admin_dashboard(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
        $this->actingAs($manager)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Thống kê QR đánh giá toàn hệ thống');
    }

    public function test_admin_rating_settings_validate_store_actor_and_only_affect_new_rating_snapshots(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        [$employeeOne, $rawTokenOne] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employeeOne, $now);
        Setting::setValue('good_reward_amount', 50000);

        $oldRating = app(RatingSubmissionService::class)
            ->submit($rawTokenOne, str_pad('browser-one', 64, '-'), CustomerRating::RATING_GOOD, 'Old snapshot', $now);

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'good_reward_amount' => 90000,
            'max_rewarded_good_per_employee_per_business_date' => 5,
            'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => 7,
        ]))->assertRedirect(route('settings.edit'));

        $this->assertSame(50000, $oldRating->fresh()->settings_snapshot['good_reward_amount']);
        $this->assertSame(50000, $oldRating->reward->fresh()->settings_snapshot['good_reward_amount']);
        $this->assertSame($admin->id, Setting::where('key', 'good_reward_amount')->value('updated_by'));

        [$employeeTwo, $rawTokenTwo] = $this->employeeWithRatingQr($employeeOne->branch);
        $this->activeNormalAttendance($employeeTwo, $now);

        $newRating = app(RatingSubmissionService::class)
            ->submit($rawTokenTwo, str_pad('browser-two', 64, '-'), CustomerRating::RATING_GOOD, 'New snapshot', $now);

        $this->assertSame(90000, $newRating->settings_snapshot['good_reward_amount']);
        $this->assertSame(90000, $newRating->reward->settings_snapshot['good_reward_amount']);

        $this->actingAs($admin)->from(route('settings.edit'))->put(route('settings.update'), $this->settingsPayload([
            'good_reward_amount' => -1,
        ]))->assertSessionHasErrors('good_reward_amount');
    }

    public function test_admin_qr_disable_and_regenerate_revoke_public_tokens(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        [$employee, $oldToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->actingAs($admin)
            ->patchJson(route('rating-qrs.disable', $employee))
            ->assertOk()
            ->assertJson(['rating_qr_enabled' => false]);

        auth()->guard()->logout();
        $this->flushSession();

        $this->get(route('qr-rating.show', ['token' => $oldToken]))
            ->assertRedirect(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]));

        $this->get(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]))
            ->assertOk()
            ->assertSee('ĐÁNH GIÁ DỊCH VỤ');

        $this->withCookie('guest_rating_token', str_pad('old-token-after-regenerate', 64, '-'))
            ->post(route('qr-rating.store', ['token' => $oldToken]), [
                'rating' => 'good',
                'comment' => null,
            ])
            ->assertSessionHas('qr_error_modal');

        $this->actingAs($admin)
            ->postJson(route('rating-qrs.regenerate', $employee))
            ->assertOk()
            ->assertJson(['rating_qr_enabled' => true]);

        $newRawToken = app(RatingQrService::class)->rawToken(app(RatingQrService::class)->activeTokenForUser($employee->fresh()));

        auth()->guard()->logout();
        $this->flushSession();

        $this->get(route('qr-rating.show', ['token' => $oldToken]))
            ->assertRedirect(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]));

        $this->get(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]))
            ->assertOk()
            ->assertSee('ĐÁNH GIÁ DỊCH VỤ');

        $this->get(route('qr-rating.show', ['token' => $newRawToken]))
            ->assertRedirect(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]));
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'probation_required_workdays' => 3,
            'overnight_cutoff_time' => '08:50',
            'attendance_supplement_limit_mode' => 'last_3_days',
            'max_leave_request_days' => 3,
            'good_reward_amount' => 0,
            'max_rewarded_good_per_employee_per_business_date' => 3,
            'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => 3,
        ], $overrides);
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
        $shift = Shift::create([
            'name' => 'Ca thuong ' . uniqid(),
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
        ]);

        ShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $now->toDateString(),
        ]);

        return Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now->copy()->setTime(8, 0),
            'status' => 'checked_in',
        ]);
    }

    private function rating(User $employee, Branch $branch, string $rating, string $comment, Carbon $submittedAt, string $guestHash): CustomerRating
    {
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $submittedAt->toDateString(),
            'checkin_at' => $submittedAt->copy()->subHour(),
            'status' => 'checked_in',
        ]);

        return CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'work_date' => $submittedAt->toDateString(),
            'business_date' => $submittedAt->toDateString(),
            'rating' => $rating,
            'comment' => $comment,
            'guest_browser_hash' => $guestHash,
            'ip_hash' => 'ip-audit',
            'user_agent_hash' => 'ua-audit',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $submittedAt,
        ]);
    }

    private function reward(CustomerRating $rating, string $status, int $amount): CustomerRatingReward
    {
        return CustomerRatingReward::create([
            'customer_rating_id' => $rating->id,
            'employee_id' => $rating->employee_id,
            'branch_id' => $rating->branch_id,
            'attendance_id' => $rating->attendance_id,
            'business_date' => $rating->business_date,
            'amount' => $amount,
            'status' => $status,
            'settings_snapshot' => ['source' => 'test'],
        ]);
    }
}
