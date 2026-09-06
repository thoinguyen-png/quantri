<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RatingRiskReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_pending_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $ratingA = $this->pendingRating($branchA, CustomerRating::RATING_GOOD, ['duplicate_ip_employee_15m'], 'Can duyet A');
        $ratingB = $this->pendingRating($branchB, CustomerRating::RATING_BAD, ['many_employees_same_ip_30m'], 'Can duyet B');

        $this->actingAs($admin)
            ->get(route('ratings.risk-review.index'))
            ->assertOk()
            ->assertSee($ratingA->employee->name)
            ->assertSee($ratingB->employee->name)
            ->assertSee('Cùng mạng đánh giá cùng nhân sự liên tiếp trong 15 phút.')
            ->assertSee('Cùng mạng đánh giá quá nhiều nhân sự trong 30 phút.')
            ->assertDontSee($ratingA->ip_hash)
            ->assertSee(substr($ratingA->ip_hash, 0, 10));
    }

    public function test_manager_only_views_pending_reviews_in_own_branch(): void
    {
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $own = $this->pendingRating($branchA, CustomerRating::RATING_GOOD, ['duplicate_ip_employee_15m'], 'Trong chi nhanh');
        $other = $this->pendingRating($branchB, CustomerRating::RATING_GOOD, ['duplicate_ip_employee_15m'], 'Ngoai chi nhanh');

        $this->actingAs($manager)
            ->get(route('ratings.risk-review.index'))
            ->assertOk()
            ->assertSee($own->employee->name)
            ->assertDontSee($other->employee->name)
            ->assertDontSee('Ngoai chi nhanh');

        $this->actingAs($manager)
            ->get(route('ratings.risk-review.index', ['branch_id' => $branchB->id]))
            ->assertOk()
            ->assertSee($own->employee->name)
            ->assertDontSee($other->employee->name)
            ->assertDontSee('Ngoai chi nhanh');
    }

    public function test_risk_review_route_is_not_captured_by_short_public_qr_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/ratings/risk-review')
            ->assertOk()
            ->assertSee('Duyệt đánh giá nghi ngờ');
    }

    public function test_manager_cannot_approve_or_reject_other_branch_review(): void
    {
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $rating = $this->pendingRating($branchB, CustomerRating::RATING_GOOD);

        $this->actingAs($manager)
            ->post(route('ratings.risk-review.approve', $rating))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('ratings.risk-review.reject', $rating))
            ->assertForbidden();
    }

    public function test_staff_and_cashier_cannot_access_risk_review_page(): void
    {
        foreach (['staff', 'cashier'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('ratings.risk-review.index'))
                ->assertForbidden();
        }
    }

    public function test_approve_pending_good_with_quota_promotes_reward_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $rating = $this->pendingRating($branch, CustomerRating::RATING_GOOD, ['duplicate_ip_employee_15m'], 'Pending good', 50000, 3);

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.approve', $rating), ['review_note' => 'Hop le'])
            ->assertRedirect();

        $rating->refresh();
        $reward = $rating->reward()->first();

        $this->assertSame(CustomerRating::RISK_APPROVED_MANUAL, $rating->risk_status);
        $this->assertSame($admin->id, $rating->reviewed_by);
        $this->assertSame('Hop le', $rating->review_note);
        $this->assertSame(CustomerRatingReward::STATUS_ELIGIBLE, $reward->status);
        $this->assertSame(50000, $reward->amount);
        $this->assertNull($reward->reason_code);
        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.approve', $rating), ['review_note' => 'Bam lai'])
            ->assertRedirect();

        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
        $this->assertSame(1, CustomerRatingReward::count());
    }

    public function test_approve_pending_good_when_quota_full_rejects_reward_without_incrementing_counter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $rating = $this->pendingRating($branch, CustomerRating::RATING_GOOD, ['duplicate_ip_employee_15m'], 'Pending good', 50000, 1);
        DB::table('customer_rating_reward_counters')->insert([
            'employee_id' => $rating->employee_id,
            'business_date' => $rating->business_date->toDateString(),
            'rewarded_good_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.approve', $rating))
            ->assertRedirect();

        $rating->refresh();
        $reward = $rating->reward()->first();

        $this->assertSame(CustomerRating::RISK_APPROVED_MANUAL, $rating->risk_status);
        $this->assertSame(CustomerRatingReward::STATUS_REJECTED, $reward->status);
        $this->assertSame('reward_cap_reached_after_manual_review', $reward->reason_code);
        $this->assertSame(0, $reward->amount);
        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_approve_pending_average_only_approves_feedback_without_reward(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $rating = $this->pendingRating($branch, CustomerRating::RATING_AVERAGE);

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.approve', $rating))
            ->assertRedirect();

        $this->assertSame(CustomerRating::RISK_APPROVED_MANUAL, $rating->fresh()->risk_status);
        $this->assertSame(0, CustomerRatingReward::count());
    }

    public function test_reject_pending_good_keeps_feedback_and_rejects_risk_reward(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $rating = $this->pendingRating($branch, CustomerRating::RATING_GOOD);

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.reject', $rating), ['review_note' => 'Khong hop le'])
            ->assertRedirect();

        $rating->refresh();
        $reward = $rating->reward()->first();

        $this->assertDatabaseHas('customer_ratings', ['id' => $rating->id]);
        $this->assertSame(CustomerRating::RISK_REJECTED_MANUAL, $rating->risk_status);
        $this->assertSame('Khong hop le', $rating->review_note);
        $this->assertSame(CustomerRatingReward::STATUS_REJECTED, $reward->status);
        $this->assertSame('fraud_rejected_manual', $reward->reason_code);
        $this->assertSame(0, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));

        $this->actingAs($admin)
            ->post(route('ratings.risk-review.reject', $rating), ['review_note' => 'Bam lai'])
            ->assertRedirect();

        $this->assertSame(0, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
        $this->assertSame(1, CustomerRatingReward::count());
    }

    public function test_filters_for_reason_rating_date_and_branch_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $match = $this->pendingRating($branchA, CustomerRating::RATING_GOOD, ['burst_good_rating_15m'], 'Match filter');
        $this->pendingRating($branchB, CustomerRating::RATING_BAD, ['duplicate_ip_employee_15m'], 'No match');

        $this->actingAs($admin)
            ->get(route('ratings.risk-review.index', [
                'business_date' => $match->business_date->toDateString(),
                'rating' => CustomerRating::RATING_GOOD,
                'branch_id' => $branchA->id,
                'risk_reason' => 'burst_good_rating_15m',
                'reward_status' => CustomerRatingReward::STATUS_RISK_REVIEW,
            ]))
            ->assertOk()
            ->assertSee('Match filter')
            ->assertDontSee('No match');
    }

    private function pendingRating(
        Branch $branch,
        string $rating = CustomerRating::RATING_GOOD,
        array $reasons = ['duplicate_ip_employee_15m'],
        string $comment = 'Pending review',
        int $amount = 50000,
        int $maxRewarded = 3
    ): CustomerRating {
        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'rating_qr_enabled' => true,
        ]);
        $shift = Shift::create([
            'name' => 'Ca ' . uniqid(),
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
        ]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-06-25',
            'checkin_at' => Carbon::parse('2026-06-25 08:00:00'),
            'status' => 'checked_in',
        ]);
        $snapshot = [
            'good_reward_amount' => $amount,
            'max_rewarded_good_per_employee_per_business_date' => $maxRewarded,
            'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => 3,
            'business_date' => '2026-06-25',
            'attendance_id' => $attendance->id,
            'branch_id' => $branch->id,
            'reward_rejection_reason' => null,
        ];

        $customerRating = CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'shift_id' => $shift->id,
            'shift_assignment_id' => null,
            'attendance_id' => $attendance->id,
            'attendance_segment_id' => null,
            'rating_qr_token_id' => null,
            'work_date' => '2026-06-25',
            'business_date' => '2026-06-25',
            'rating' => $rating,
            'comment' => $comment,
            'guest_browser_hash' => hash('sha256', 'guest-' . uniqid()),
            'ip_hash' => hash('sha256', 'ip-' . uniqid()),
            'user_agent_hash' => hash('sha256', 'ua-' . uniqid()),
            'settings_snapshot' => $snapshot,
            'risk_status' => CustomerRating::RISK_PENDING_REVIEW,
            'risk_reasons' => $reasons,
            'risk_checked_at' => Carbon::parse('2026-06-25 10:00:00'),
            'submitted_at' => Carbon::parse('2026-06-25 10:00:00'),
        ]);

        if ($rating === CustomerRating::RATING_GOOD) {
            CustomerRatingReward::create([
                'customer_rating_id' => $customerRating->id,
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'attendance_id' => $attendance->id,
                'business_date' => '2026-06-25',
                'amount' => $amount,
                'status' => CustomerRatingReward::STATUS_RISK_REVIEW,
                'reason_code' => 'fraud_review_required',
                'settings_snapshot' => $snapshot + ['reward_rejection_reason' => 'fraud_review_required'],
            ]);
        }

        return $customerRating->fresh(['employee', 'branch', 'reward']);
    }
}
