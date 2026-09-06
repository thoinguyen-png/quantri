<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardRatingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_my_ratings_shows_only_the_authenticated_staff_personal_rating_data(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $branch = Branch::create(['name' => 'Chi nhanh A']);
        $employee = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $otherEmployee = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);
        $otherAttendance = Attendance::create([
            'user_id' => $otherEmployee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);

        $employeeRating = CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_GOOD,
            'comment' => 'Phục vụ rất tốt.',
            'guest_browser_hash' => 'guest-good',
            'ip_hash' => 'ip-good',
            'user_agent_hash' => 'ua-good',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
        ]);

        $otherRating = CustomerRating::create([
            'employee_id' => $otherEmployee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $otherAttendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_BAD,
            'comment' => 'Nhân viên khác không nên thấy.',
            'guest_browser_hash' => 'guest-bad',
            'ip_hash' => 'ip-bad',
            'user_agent_hash' => 'ua-bad',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
        ]);

        CustomerRatingReward::create([
            'customer_rating_id' => $employeeRating->id,
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'business_date' => $now->toDateString(),
            'amount' => 50000,
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'settings_snapshot' => ['source' => 'test'],
        ]);

        CustomerRatingReward::create([
            'customer_rating_id' => $otherRating->id,
            'employee_id' => $otherEmployee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $otherAttendance->id,
            'business_date' => $now->toDateString(),
            'amount' => 99999,
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'settings_snapshot' => ['source' => 'test'],
        ]);

        $response = $this->actingAs($employee)
            ->get(route('my-ratings.index', [
                'user_id' => $otherEmployee->id,
                'employee_id' => $otherEmployee->id,
                'branch_id' => $branch->id,
            ]));

        $response->assertOk();
        $response->assertSee('Phục vụ rất tốt.');
        $response->assertDontSee('Nhân viên khác không nên thấy.');
        $response->assertSee('Dữ liệu cá nhân');
        $response->assertDontSee('99.999');
        $response->assertDontSee('guest-bad');
        $response->assertDontSee('ip-bad');
        $response->assertDontSee('ua-bad');
    }

    public function test_my_ratings_keeps_latest_summary_and_paginated_rating_history(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $branch = Branch::create(['name' => 'Chi nhanh B']);
        $employee = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);

        for ($i = 0; $i < 11; $i++) {
            CustomerRating::create([
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'attendance_id' => $attendance->id,
                'work_date' => $now->toDateString(),
                'business_date' => $now->toDateString(),
                'rating' => CustomerRating::RATING_GOOD,
                'comment' => 'Đánh giá ' . ($i + 1),
                'settings_snapshot' => ['source' => 'test'],
                'submitted_at' => $now->copy()->subMinutes($i),
                'guest_browser_hash' => 'guest-latest-' . $i,
            ]);
        }

        $response = $this->actingAs($employee)
            ->get(route('my-ratings.index'));

        $response->assertOk();
        $response->assertSee('Đánh giá 1');
        $response->assertSee('Đánh giá 10');
        $response->assertSee('Đánh giá 11');
        $response->assertSee('my-rating-history', false);
    }

    public function test_my_ratings_reward_progress_uses_business_date_and_backend_cap(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        Setting::setValue('max_rewarded_good_per_employee_per_business_date', 2);

        $branch = Branch::create(['name' => 'Chi nhanh C']);
        $employee = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);

        $todayRating = CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_GOOD,
            'comment' => 'Hôm nay',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
            'guest_browser_hash' => 'guest-today',
        ]);

        CustomerRatingReward::create([
            'customer_rating_id' => $todayRating->id,
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'business_date' => $now->toDateString(),
            'amount' => 50000,
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'settings_snapshot' => ['source' => 'test'],
        ]);

        $yesterday = $now->copy()->subDay();
        $yesterdayRating = CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'work_date' => $yesterday->toDateString(),
            'business_date' => $yesterday->toDateString(),
            'rating' => CustomerRating::RATING_GOOD,
            'comment' => 'Ngày trước',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $yesterday,
            'guest_browser_hash' => 'guest-yesterday',
        ]);

        CustomerRatingReward::create([
            'customer_rating_id' => $yesterdayRating->id,
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'business_date' => $yesterday->toDateString(),
            'amount' => 50000,
            'status' => CustomerRatingReward::STATUS_ELIGIBLE,
            'settings_snapshot' => ['source' => 'test'],
        ]);

        $response = $this->actingAs($employee)
            ->get(route('my-ratings.index'));

        $response->assertOk();
        $response->assertSee('1/2');
        $response->assertSee('50.000đ');
    }

    public function test_new_home_shows_rating_cards_and_attendance_dashboard_keeps_the_old_timekeeping_view(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $branch = Branch::create(['name' => 'Chi nhanh D']);
        $employee = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);

        CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'attendance_id' => $attendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_GOOD,
            'comment' => 'Khong render o dashboard',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
            'guest_browser_hash' => 'guest-dashboard-hidden',
        ]);

        $response = $this->actingAs($employee)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($employee->name);
        $response->assertSee('Trang tin');
        $response->assertSee(
            'data-home-rating-dashboard',
            false
        );
        $response->assertSee(
            'data-home-personal-list',
            false
        );
        $response->assertSee(
            'data-home-branch-list',
            false
        );
        $response->assertDontSee('Khong render o dashboard');
        $response->assertDontSee('data-rating-feed', false);

        $attendanceResponse = $this->actingAs($employee)
            ->get(route('attendance.dashboard'));

        $attendanceResponse->assertOk();
        $attendanceResponse->assertSee('Bảng Công');
    }

    public function test_home_rating_summary_uses_auth_user_and_branch_scope_without_sensitive_data(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $employee = User::factory()->create(['role' => 'staff', 'branch_id' => $branchA->id, 'rating_qr_enabled' => true]);
        $colleague = User::factory()->create(['role' => 'staff', 'branch_id' => $branchA->id, 'rating_qr_enabled' => true]);
        $otherBranchEmployee = User::factory()->create(['role' => 'staff', 'branch_id' => $branchB->id, 'rating_qr_enabled' => true]);

        $employeeAttendance = Attendance::create([
            'user_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);
        $colleagueAttendance = Attendance::create([
            'user_id' => $colleague->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);
        $otherAttendance = Attendance::create([
            'user_id' => $otherBranchEmployee->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now,
            'status' => 'checked_in',
        ]);

        CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branchA->id,
            'attendance_id' => $employeeAttendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_GOOD,
            'comment' => 'Ca nhan A',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
            'guest_browser_hash' => 'guest-home-a',
            'ip_hash' => 'ip-home-a',
            'user_agent_hash' => 'ua-home-a',
        ]);

        CustomerRating::create([
            'employee_id' => $colleague->id,
            'branch_id' => $branchA->id,
            'attendance_id' => $colleagueAttendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_AVERAGE,
            'comment' => 'Cung chi nhanh',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now->copy()->subMinute(),
            'guest_browser_hash' => 'guest-home-colleague',
        ]);

        CustomerRating::create([
            'employee_id' => $otherBranchEmployee->id,
            'branch_id' => $branchB->id,
            'attendance_id' => $otherAttendance->id,
            'work_date' => $now->toDateString(),
            'business_date' => $now->toDateString(),
            'rating' => CustomerRating::RATING_BAD,
            'comment' => 'Khac chi nhanh',
            'settings_snapshot' => ['source' => 'test'],
            'submitted_at' => $now,
            'guest_browser_hash' => 'guest-home-other',
        ]);

        $response = $this->actingAs($employee)
            ->getJson(route('dashboard.rating-summary', ['branch_id' => $branchB->id]));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control', ''));
        $response->assertJsonPath('personal.counts.good', 1);

        $content = $response->getContent();
        $this->assertStringContainsString('Ca nhan A', $content);
        $this->assertStringContainsString('Cung chi nhanh', $content);
        $this->assertStringNotContainsString('Khac chi nhanh', $content);
        $this->assertStringNotContainsString('guest-home-a', $content);
        $this->assertStringNotContainsString('ip-home-a', $content);
        $this->assertStringNotContainsString('ua-home-a', $content);
    }
}
