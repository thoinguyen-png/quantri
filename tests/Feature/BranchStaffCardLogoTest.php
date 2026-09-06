<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchStaffCardLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_replace_logo_for_any_branch(): void
    {
        Storage::fake('public');
        $first = Branch::create(['name' => 'Co so A']);
        $second = Branch::create(['name' => 'Co so B']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertStringContainsString('icons/logoSantori.png', $first->staff_card_logo_url);

        $this->actingAs($admin)->post(
            route('branches.staff-card-logo.store', $second),
            ['staff_card_logo' => UploadedFile::fake()->image('first.png')]
        )->assertRedirect();

        $firstPath = $second->fresh()->staff_card_logo_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($admin)->post(
            route('branches.staff-card-logo.store', $second),
            ['staff_card_logo' => UploadedFile::fake()->image('second.jpg')]
        )->assertRedirect();

        $secondPath = $second->fresh()->staff_card_logo_path;
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertNull($first->fresh()->staff_card_logo_path);

        $this->actingAs($admin)
            ->delete(route('branches.staff-card-logo.destroy', $second))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($secondPath);
        $this->assertStringContainsString('icons/logoSantori.png', $second->fresh()->staff_card_logo_url);
    }

    public function test_manager_can_only_update_logo_for_own_branch(): void
    {
        Storage::fake('public');
        $ownBranch = Branch::create(['name' => 'Co so A']);
        $otherBranch = Branch::create(['name' => 'Co so B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $ownBranch->id]);
        $file = UploadedFile::fake()->image('logo.jpg');

        $this->actingAs($manager)
            ->post(route('branches.staff-card-logo.store', $ownBranch), ['staff_card_logo' => $file])
            ->assertRedirect();

        $this->assertNotNull($ownBranch->fresh()->staff_card_logo_path);

        $this->actingAs($manager)
            ->post(route('branches.staff-card-logo.store', $otherBranch), ['staff_card_logo' => $file])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('branches.staff-card-logo.destroy', $otherBranch))
            ->assertForbidden();
    }

    public function test_logo_validation_accepts_eight_mb_and_rejects_files_over_ten_mb(): void
    {
        Storage::fake('public');
        $branch = Branch::create(['name' => 'Co so A']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('branches.staff-card-logo.store', $branch), [
                'staff_card_logo' => UploadedFile::fake()->create('logo.gif', 100, 'image/gif'),
            ])
            ->assertSessionHasErrors('staff_card_logo');

        $this->actingAs($admin)
            ->post(route('branches.staff-card-logo.store', $branch), [
                'staff_card_logo' => UploadedFile::fake()->create('logo.png', 8192, 'image/png'),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors('staff_card_logo');

        Storage::disk('public')->assertExists($branch->fresh()->staff_card_logo_path);

        $this->actingAs($admin)
            ->post(route('branches.staff-card-logo.store', $branch), [
                'staff_card_logo' => UploadedFile::fake()->create('logo.png', 10241, 'image/png'),
            ])
            ->assertSessionHasErrors([
                'staff_card_logo' => 'Logo không được vượt quá 10 MB.',
            ]);
    }

    public function test_replacing_logo_never_deletes_a_file_from_another_branch_directory(): void
    {
        Storage::fake('public');
        $first = Branch::create(['name' => 'Co so A']);
        $second = Branch::create(['name' => 'Co so B']);
        $admin = User::factory()->create(['role' => 'admin']);
        $otherBranchPath = 'staff-card-logos/' . $second->id . '/keep.png';

        Storage::disk('public')->put($otherBranchPath, 'other branch logo');
        $first->forceFill(['staff_card_logo_path' => $otherBranchPath])->saveOrFail();

        $this->actingAs($admin)
            ->post(route('branches.staff-card-logo.store', $first), [
                'staff_card_logo' => UploadedFile::fake()->image('replacement.webp'),
            ])
            ->assertRedirect();

        Storage::disk('public')->assertExists($otherBranchPath);
        Storage::disk('public')->assertExists($first->fresh()->staff_card_logo_path);
        $this->assertStringStartsWith(
            'staff-card-logos/' . $first->id . '/',
            $first->fresh()->staff_card_logo_path
        );
    }
}
