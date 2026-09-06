<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_seeder_creates_all_17_positions_and_maps_existing_users(): void
    {
        $branch = Branch::create(['name' => 'Cơ sở Test']);
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branch->id, 'position_id' => null]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id, 'position_id' => null]);
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $branch->id, 'position_id' => null]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id, 'position_id' => null]);

        $this->seed(PositionSeeder::class);

        $this->assertSame(17, Position::count());

        $this->assertDatabaseHas('positions', [
            'code' => 'tong_quan_ly',
            'name' => 'Tổng quản lý',
            'name_en' => 'General Manager',
            'system_role' => 'manager',
        ]);

        $this->assertDatabaseHas('positions', [
            'code' => 'bep',
            'name' => 'Bếp',
            'name_en' => 'Chef',
            'system_role' => 'staff',
        ]);

        $this->assertDatabaseHas('positions', [
            'code' => 'thu_ngan',
            'name' => 'Thu ngân',
            'name_en' => 'Cashier',
            'system_role' => 'cashier',
        ]);

        $this->assertDatabaseHas('positions', [
            'code' => 'giam_doc',
            'name' => 'Giám đốc',
            'name_en' => 'Director',
            'system_role' => 'admin',
        ]);

        $admin->refresh();
        $manager->refresh();
        $cashier->refresh();
        $staff->refresh();

        $this->assertNotNull($admin->position_id);
        $this->assertSame('admin', $admin->position->code);

        $this->assertNotNull($manager->position_id);
        $this->assertSame('tong_quan_ly', $manager->position->code);

        $this->assertNotNull($cashier->position_id);
        $this->assertSame('thu_ngan', $cashier->position->code);

        $this->assertNotNull($staff->position_id);
        $this->assertSame('phuc_vu', $staff->position->code);
    }

    public function test_admin_can_view_positions_list(): void
    {
        $this->seed(PositionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('positions.index'));

        $response->assertOk()
            ->assertSee('Danh Mục Chức Vụ')
            ->assertSee('General Manager')
            ->assertSee('Tổng quản lý')
            ->assertSee('PR Manager');
    }

    public function test_manager_cannot_access_positions_management(): void
    {
        $this->seed(PositionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('positions.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_new_position(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('positions.store'), [
            'name' => 'Pha chế',
            'name_en' => 'Bartender',
            'code' => 'pha_che',
            'system_role' => 'staff',
            'sort_order' => 20,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('positions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('positions', [
            'name' => 'Pha chế',
            'name_en' => 'Bartender',
            'code' => 'pha_che',
            'system_role' => 'staff',
        ]);
    }

    public function test_admin_can_update_position_and_syncs_users_role(): void
    {
        $this->seed(PositionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $pos = Position::where('code', 'to_pho')->first();

        $user = User::factory()->create([
            'position_id' => $pos->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($admin)->put(route('positions.update', $pos), [
            'name' => 'Tổ phó ca đêm',
            'name_en' => 'Night Shift Leader',
            'code' => 'to_pho',
            'system_role' => 'manager',
            'sort_order' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('positions.index'))
            ->assertSessionHas('success');

        $pos->refresh();
        $this->assertSame('Tổ phó ca đêm', $pos->name);
        $this->assertSame('Night Shift Leader', $pos->name_en);
        $this->assertSame('manager', $pos->system_role);

        // Verify user role automatically updated
        $user->refresh();
        $this->assertSame('manager', $user->role);
    }

    public function test_admin_cannot_delete_position_in_use(): void
    {
        $this->seed(PositionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $pos = Position::where('code', 'bep')->first();

        User::factory()->create([
            'position_id' => $pos->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($admin)->delete(route('positions.destroy', $pos));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('positions', ['id' => $pos->id]);
    }

    public function test_user_saving_auto_syncs_role_from_position(): void
    {
        $this->seed(PositionSeeder::class);
        $managerPos = Position::where('code', 'tong_quan_ly')->first();
        $staffPos = Position::where('code', 'bep')->first();

        $user = new User([
            'name' => 'Test Auto Role',
            'email' => 'auto-role@example.com',
            'password' => 'password123',
            'start_work_date' => now()->toDateString(),
            'position_id' => $managerPos->id,
        ]);
        $user->save();

        $this->assertSame('manager', $user->fresh()->role);

        // Update position to Chef
        $user->position_id = $staffPos->id;
        $user->save();

        $this->assertSame('staff', $user->fresh()->role);
    }
}
