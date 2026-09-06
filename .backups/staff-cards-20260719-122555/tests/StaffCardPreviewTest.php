<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\RatingQrService;
use App\Services\StaffCardPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCardPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_both_cards_with_only_last_four_citizen_digits(): void
    {
        $branch = Branch::create(['name' => 'Co so A']);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'name' => 'Nhan vien mau',
            'citizen_id' => '079-1234 5678 9999',
            'rating_qr_enabled' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('users.staff-card.preview', $employee));

        $response
            ->assertOk()
            ->assertSee('80 × 30 mm')
            ->assertSee('85 × 140 mm')
            ->assertSee('9999')
            ->assertDontSee('079-1234 5678 9999')
            ->assertDontSee('079123456789999');
    }

    public function test_manager_can_preview_only_staff_in_own_branch(): void
    {
        $ownBranch = Branch::create(['name' => 'Co so A']);
        $otherBranch = Branch::create(['name' => 'Co so B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $ownBranch->id]);
        $ownEmployee = User::factory()->create(['role' => 'staff', 'branch_id' => $ownBranch->id]);
        $otherEmployee = User::factory()->create(['role' => 'staff', 'branch_id' => $otherBranch->id]);

        $this->actingAs($manager)
            ->get(route('users.staff-card.preview', $ownEmployee))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('users.staff-card.preview', $otherEmployee))
            ->assertForbidden();
    }

    public function test_missing_or_short_citizen_id_uses_placeholder(): void
    {
        $branch = Branch::create(['name' => 'Co so A']);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'citizen_id' => '12-A',
        ]);

        $this->actingAs($admin)
            ->get(route('users.staff-card.preview', $employee))
            ->assertOk()
            ->assertSee('----');
    }

    public function test_missing_logo_avatar_position_and_citizen_id_have_safe_fallbacks(): void
    {
        $branch = Branch::create(['name' => 'Co so A']);
        $employee = User::factory()->create([
            'name' => '',
            'role' => 'unknown',
            'branch_id' => $branch->id,
            'citizen_id' => null,
            'face_image_path' => null,
            'rating_qr_enabled' => false,
        ]);

        $card = app(StaffCardPreviewService::class)->dataFor($employee);

        $this->assertSame('Chưa có họ tên', $card['name']);
        $this->assertSame('Nhân sự', $card['position']);
        $this->assertNull($card['avatar_url']);
        $this->assertSame('----', $card['citizen_last4']);
        $this->assertStringContainsString('icons/logoMaxSim.png', $card['logo_url']);
        $this->assertNull($card['qr_svg_url']);
    }

    public function test_preview_and_pdf_qr_use_public_rating_code_without_citizen_id(): void
    {
        $branch = Branch::create(['name' => 'Co so A']);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'citizen_id' => '079-1234-5678',
            'rating_qr_enabled' => true,
        ]);
        $ratingQr = app(RatingQrService::class);
        $token = $ratingQr->getOrCreateForUser($employee, $admin);
        $publicUrl = $ratingQr->publicUrl($token);

        $this->actingAs($admin)
            ->get(route('users.staff-card.preview', $employee))
            ->assertOk()
            ->assertSee(route('rating-qrs.svg', $employee, absolute: false), false)
            ->assertSee('5678')
            ->assertDontSee('079-1234-5678')
            ->assertDontSee('07912345678');

        $svgResponse = $this->actingAs($admin)
            ->get(route('rating-qrs.svg', $employee))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertStringContainsString('<svg', $svgResponse->getContent());
        $this->assertStringEndsWith('/' . $employee->fresh()->public_rating_code, $publicUrl);
        $this->assertStringNotContainsString('079-1234-5678', $publicUrl);
        $this->assertStringNotContainsString('07912345678', $publicUrl);
        $this->get(route('qr-rating.short.show', $employee->fresh()->public_rating_code))
            ->assertOk()
            ->assertDontSee('079-1234-5678')
            ->assertDontSee('07912345678');

        $pdfCard = app(StaffCardPreviewService::class)->dataFor(
            $employee->fresh()->load(['branch', 'activeRatingQrToken']),
            true,
        );
        $pdfSvg = base64_decode(explode(',', $pdfCard['qr_svg_url'], 2)[1], true);

        $this->assertIsString($pdfSvg);
        $this->assertStringContainsString('<svg', $pdfSvg);
        $this->assertStringNotContainsString('079-1234-5678', $pdfSvg);
        $this->assertStringNotContainsString('07912345678', $pdfSvg);
    }
}
