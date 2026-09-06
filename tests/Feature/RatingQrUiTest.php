<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\RatingQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingQrUiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_staff_cashier_and_manager_see_personal_rating_qr_when_enabled(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh']);

        foreach (['staff', 'cashier', 'manager'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'branch_id' => $branch->id,
                'rating_qr_enabled' => true,
            ]);

            $this->actingAs($user)
                ->get(route('users.me'))
                ->assertOk()
                ->assertSee('data-rating-qr-panel', false)
                ->assertSee(route('rating-qrs.show', $user, absolute: false), false)
                ->assertSee(route('rating-qrs.download', $user, absolute: false), false)
                ->assertDontSee('/qr-rating/', false);
        }
    }

    public function test_admin_does_not_have_personal_rating_qr_on_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'rating_qr_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('users.me'))
            ->assertOk()
            ->assertDontSee('Mã QR đánh giá của tôi');
    }

    public function test_manager_users_list_shows_rating_qr_for_same_branch_staff_only(): void
    {
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $sameBranchStaff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branchA->id,
            'rating_qr_enabled' => true,
        ]);
        $otherBranchStaff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branchB->id,
            'rating_qr_enabled' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($sameBranchStaff->name)
            ->assertSee('data-rating-qr-panel', false)
            ->assertSee(route('rating-qrs.download', $sameBranchStaff, absolute: false), false)
            ->assertDontSee($otherBranchStaff->name)
            ->assertDontSee(route('rating-qrs.download', $otherBranchStaff, absolute: false), false);
    }

    public function test_manager_cannot_download_rating_qr_for_other_branch(): void
    {
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $otherBranchStaff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branchB->id,
            'rating_qr_enabled' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('rating-qrs.download', $otherBranchStaff))
            ->assertForbidden();
    }

    public function test_legacy_public_token_redirects_to_short_url_and_old_post_after_regenerate_is_rejected(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        [$employee, $oldToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        auth()->guard()->logout();
        $this->flushSession();

        $this->get(route('qr-rating.show', ['token' => $oldToken]))
            ->assertRedirect(route('qr-rating.short.show', ['publicRatingCode' => $employee->fresh()->public_rating_code]));

        $this->actingAs($admin)
            ->postJson(route('rating-qrs.regenerate', $employee))
            ->assertOk();

        auth()->guard()->logout();
        $this->flushSession();

        $this->withCookie('guest_rating_token', str_pad('old-token-browser', 64, '-'))
            ->post(route('qr-rating.store', ['token' => $oldToken]), [
                'rating' => 'good',
                'comment' => null,
            ])
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(0, CustomerRating::count());
    }

    public function test_unauthorized_user_cannot_call_show_or_download_endpoints(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $viewer = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $target = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'rating_qr_enabled' => true,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('rating-qrs.show', $target))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('rating-qrs.download', $target))
            ->assertForbidden();
    }

    public function test_qr_download_works_for_mobile_and_desktop_user_agents(): void
    {
        [$employee] = $this->employeeWithRatingQr();

        foreach ([
            'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ] as $userAgent) {
            $this->actingAs($employee)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get(route('rating-qrs.download', $employee))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/svg+xml')
                ->assertHeader('Content-Disposition', 'attachment; filename="rating-qr.svg"');
        }
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
}
