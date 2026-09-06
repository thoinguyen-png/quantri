<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\StaffCardExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_branch_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activeBranch = Branch::create(['name' => 'Chi nhánh A', 'is_active' => true]);
        $inactiveBranch = Branch::create(['name' => 'Chi nhánh B', 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('branches.index'));

        $response->assertOk();
        $response->assertSee('Chi nhánh A');
        $response->assertSee('Chi nhánh B');
        $response->assertSee('Đang hoạt động');
        $response->assertSee('Ngưng hoạt động');
    }

    public function test_non_admin_cannot_access_branch_crud(): void
    {
        $branch = Branch::create(['name' => 'Chi nhánh test']);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);

        $this->actingAs($staff)->get(route('branches.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('branches.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('branches.store'), ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->get(route('branches.edit', $branch))->assertForbidden();
    }

    public function test_admin_can_create_branch_with_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('branches.store'), [
            'name' => 'Cơ sở Quận 1',
            'address' => '123 Đồng Khởi, Q1',
            'latitude' => 10.776889,
            'longitude' => 106.700806,
            'gps_radius' => 150,
            'is_active' => '1',
            'staff_card_logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', [
            'name' => 'Cơ sở Quận 1',
            'address' => '123 Đồng Khởi, Q1',
            'gps_radius' => 150,
            'is_active' => 1,
        ]);

        $branch = Branch::where('name', 'Cơ sở Quận 1')->first();
        $this->assertNotNull($branch->staff_card_logo_path);
        Storage::disk('public')->assertExists($branch->staff_card_logo_path);
        $this->assertTrue($branch->hasStaffCardLogo());
    }

    public function test_admin_can_update_branch_and_replace_or_remove_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create([
            'name' => 'Cơ sở Cũ',
            'address' => 'Địa chỉ cũ',
            'is_active' => true,
        ]);

        // Upload first logo
        $this->actingAs($admin)->put(route('branches.update', $branch), [
            'name' => 'Cơ sở Mới',
            'address' => 'Địa chỉ mới',
            'is_active' => '1',
            'staff_card_logo' => UploadedFile::fake()->image('first.png'),
        ])->assertRedirect(route('branches.index'));

        $branch->refresh();
        $this->assertSame('Cơ sở Mới', $branch->name);
        $this->assertNotNull($branch->staff_card_logo_path);
        $firstPath = $branch->staff_card_logo_path;
        Storage::disk('public')->assertExists($firstPath);

        // Remove custom logo
        $this->actingAs($admin)->put(route('branches.update', $branch), [
            'name' => 'Cơ sở Mới',
            'remove_logo' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('branches.index'));

        $branch->refresh();
        $this->assertNull($branch->staff_card_logo_path);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_admin_can_toggle_branch_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Cơ sở Test', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('branches.toggle-status', $branch))
            ->assertRedirect();

        $this->assertFalse($branch->fresh()->is_active);

        $this->actingAs($admin)->patch(route('branches.toggle-status', $branch))
            ->assertRedirect();

        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_inactive_branch_is_hidden_from_active_scope_and_staff_card_export(): void
    {
        $activeBranch = Branch::create(['name' => 'Cơ sở Hoạt Động', 'is_active' => true]);
        $inactiveBranch = Branch::create(['name' => 'Cơ sở Đã Đóng Cửa', 'is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin']);

        $activeBranches = Branch::active()->pluck('id')->all();
        $this->assertContains($activeBranch->id, $activeBranches);
        $this->assertNotContains($inactiveBranch->id, $activeBranches);

        /** @var StaffCardExportService $exportService */
        $exportService = app(StaffCardExportService::class);
        $exportableBranches = $exportService->branchesFor($admin)->pluck('id')->all();

        $this->assertContains($activeBranch->id, $exportableBranches);
        $this->assertNotContains($inactiveBranch->id, $exportableBranches);
    }

    public function test_cannot_delete_branch_with_users_but_can_delete_empty_branch(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $busyBranch = Branch::create(['name' => 'Cơ sở có người', 'is_active' => true]);
        User::factory()->create(['branch_id' => $busyBranch->id]);

        $this->actingAs($admin)->delete(route('branches.destroy', $busyBranch))
            ->assertRedirect();
        $this->assertDatabaseHas('branches', ['id' => $busyBranch->id]);

        $emptyBranch = Branch::create(['name' => 'Cơ sở trống', 'is_active' => true]);
        $this->actingAs($admin)->delete(route('branches.destroy', $emptyBranch))
            ->assertRedirect();
        $this->assertDatabaseMissing('branches', ['id' => $emptyBranch->id]);
    }

    public function test_deactivating_branch_automatically_transitions_staff_to_resigned_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Chi nhánh X', 'is_active' => true]);

        $staff1 = User::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'chinh_thuc',
            'is_active' => true,
        ]);
        $staff2 = User::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'thu_viec',
            'is_active' => true,
        ]);

        // Toggle status to inactive
        $this->actingAs($admin)->patch(route('branches.toggle-status', $branch))
            ->assertRedirect();

        $this->assertFalse($branch->fresh()->is_active);

        $staff1->refresh();
        $staff2->refresh();

        $this->assertSame('da_nghi', $staff1->status);
        $this->assertSame('resigned', $staff1->employment_status);
        $this->assertFalse((bool) $staff1->is_active);
        $this->assertSame(today()->toDateString(), $staff1->resigned_at?->toDateString());

        $this->assertSame('da_nghi', $staff2->status);
        $this->assertSame('resigned', $staff2->employment_status);
        $this->assertFalse((bool) $staff2->is_active);
        $this->assertSame(today()->toDateString(), $staff2->resigned_at?->toDateString());
    }
}
