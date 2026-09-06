<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardRatingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_dashboard_does_not_render_rating_dashboard(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-06-25 10:00:00')
        );

        $branch = Branch::create([
            'name' => 'Chi nhanh A',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('attendance.dashboard'));

        $response->assertOk();

        /*
         * Dashboard Manager hiện chỉ giữ phần chấm công.
         * Khối phản hồi/đánh giá khách hàng đã được loại khỏi màn hình này.
         */
        $response->assertDontSee(
            'Phản hồi khách hàng trong chi nhánh'
        );
    }

    public function test_manager_cannot_download_qr_outside_branch_scope(): void
    {
        $branchA = Branch::create([
            'name' => 'Chi nhanh A',
        ]);

        $branchB = Branch::create([
            'name' => 'Chi nhanh B',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'branch_id' => $branchA->id,
        ]);

        $staffB = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branchB->id,
            'rating_qr_enabled' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('rating-qrs.download', $staffB))
            ->assertForbidden();
    }
}