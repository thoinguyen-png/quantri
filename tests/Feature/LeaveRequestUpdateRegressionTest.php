<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LeaveRequestUpdateRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_pending_leave_is_not_auto_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'staff']);

        $leave = $this->leaveRequest($employee, [
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get(route('leave-requests.index'))
            ->assertOk();

        $leave->refresh();
        $this->assertSame('pending', $leave->status);
        $this->assertNull($leave->reviewed_at);
    }

    public function test_admin_can_approve_pending_off_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'staff']);
        $leave = $this->leaveRequest($employee);

        $this->actingAs($admin)
            ->patch(route('leave-requests.approve', $leave), [
                'review_note' => 'Admin duyet OFF.',
            ])
            ->assertRedirect(route('leave-requests.index', absolute: false));

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);
    }

    public function test_admin_can_reject_pending_off_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'staff']);
        $leave = $this->leaveRequest($employee);

        $this->actingAs($admin)
            ->patch(route('leave-requests.reject', $leave), [
                'review_note' => 'Khong phu hop lich lam viec.',
            ])
            ->assertRedirect(route('leave-requests.index', absolute: false));

        $leave->refresh();
        $this->assertSame('rejected', $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
    }

    public function test_leave_edit_update_routes_are_not_added(): void
    {
        $this->assertFalse(Route::has('leave-requests.edit'));
        $this->assertFalse(Route::has('leave-requests.update'));
    }

    private function leaveRequest(User $employee, array $overrides = []): LeaveRequest
    {
        $start = Carbon::parse('2026-08-10');

        $leave = LeaveRequest::create([
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
            'user_id' => $employee->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'total_days' => 1,
            'leave_type' => 'nghi_phep',
            'reason' => 'Xin nghi phep theo lich ca nhan.',
            'status' => 'pending',
        ]);

        if ($overrides) {
            $leave->forceFill($overrides)->save();
        }

        return $leave->fresh();
    }
}
