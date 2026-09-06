<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\RatingQrToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingQrCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_and_manager_have_valid_rating_qr(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh 1']);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        $this->actingAs($staff)
            ->getJson(route('rating-qrs.show', $staff))
            ->assertOk()
            ->assertJsonPath('user_id', $staff->id)
            ->assertJsonPath('rating_qr_enabled', true)
            ->assertJsonPath('public_code', $staff->fresh()->public_rating_code);

        $this->actingAs($manager)
            ->getJson(route('rating-qrs.show', $manager))
            ->assertOk()
            ->assertJsonPath('user_id', $manager->id)
            ->assertJsonPath('rating_qr_enabled', true);

        $this->assertSame(2, RatingQrToken::where('enabled', true)->whereNull('revoked_at')->count());
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/', $staff->fresh()->public_rating_code);
        $this->assertStringEndsWith('/' . $staff->fresh()->public_rating_code, app(\App\Services\RatingQrService::class)->publicUrlForUser($staff->fresh()));
    }

    public function test_admin_does_not_have_personal_rating_qr(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'rating_qr_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->getJson(route('rating-qrs.show', $admin))
            ->assertForbidden();

        $this->assertSame(0, RatingQrToken::where('user_id', $admin->id)->count());
    }

    public function test_manager_cannot_view_rating_qr_for_staff_in_another_branch(): void
    {
        $branchA = Branch::create(['name' => 'Chi nhanh A']);
        $branchB = Branch::create(['name' => 'Chi nhanh B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branchB->id]);

        $this->actingAs($manager)
            ->getJson(route('rating-qrs.show', $staff))
            ->assertForbidden();

        $this->assertSame(0, RatingQrToken::where('user_id', $staff->id)->count());
    }

    public function test_admin_regenerating_rating_qr_revokes_old_token(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);

        $this->actingAs($admin)
            ->getJson(route('rating-qrs.show', $staff))
            ->assertOk();

        $oldToken = RatingQrToken::where('user_id', $staff->id)->firstOrFail();
        $publicCode = $staff->fresh()->public_rating_code;

        $this->actingAs($admin)
            ->postJson(route('rating-qrs.regenerate', $staff))
            ->assertOk()
            ->assertJsonPath('user_id', $staff->id);

        $newToken = RatingQrToken::where('user_id', $staff->id)
            ->where('enabled', true)
            ->whereNull('revoked_at')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($oldToken->id, $newToken->id);
        $this->assertNotSame($oldToken->token_hash, $newToken->token_hash);
        $this->assertSame($publicCode, $staff->fresh()->public_rating_code);
        $this->assertFalse((bool) $oldToken->fresh()->enabled);
        $this->assertNotNull($oldToken->fresh()->revoked_at);
    }

    public function test_staff_cannot_download_another_users_rating_qr(): void
    {
        $branch = Branch::create(['name' => 'Chi nhanh']);
        $viewer = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $target = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);

        $this->actingAs($viewer)
            ->get(route('rating-qrs.download', $target))
            ->assertForbidden();
    }
}
