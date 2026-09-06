<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\QuickOnboardingBatch;
use App\Models\QuickOnboardingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_batch_and_add_more_without_creating_users(): void
    {
        $branch = Branch::create(['name' => 'Santori']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);
        $initialUserCount = User::count();

        $this->actingAs($admin)
            ->get(route('quick-onboarding.index'))
            ->assertOk()
            ->assertSee('Quick Onboarding');

        $this->actingAs($admin)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 20,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $this->assertSame(1, QuickOnboardingBatch::count());
        $this->assertSame(20, QuickOnboardingEntry::count());
        $this->assertSame($initialUserCount, User::count());

        $this->actingAs($admin)
            ->post(route('quick-onboarding.add-more'), [
                'quantity' => 5,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $this->assertSame(1, QuickOnboardingBatch::count());
        $this->assertSame(25, QuickOnboardingEntry::count());
        $this->assertSame($initialUserCount, User::count());
        $this->assertSame(25, QuickOnboardingEntry::distinct('token')->count('token'));
        $this->assertTrue(QuickOnboardingEntry::get()->every(fn($entry) => strlen($entry->token) === 72));

        $response = $this->actingAs($admin)->get(route('quick-onboarding.index'));

        $response
            ->assertOk()
            ->assertSee('data-entry-row=', false)
            ->assertSee('data-code="025"', false)
            ->assertSee('/quick-onboarding/invite/', false)
            ->assertSee('data-share-open', false)
            ->assertSee('data-share-quick', false);
    }

    public function test_manager_creates_invites_for_own_branch(): void
    {
        $branch = Branch::create(['name' => 'Santori 176']);
        $manager = User::factory()->create([
            'role' => 'manager',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 3,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $this->assertSame([$branch->id], QuickOnboardingEntry::pluck('branch_id')->unique()->values()->all());

        $this->actingAs($manager)
            ->get(route('quick-onboarding.index'))
            ->assertOk()
            ->assertSee('Santori 176');
    }

    public function test_bulk_update_changes_only_selected_entries_without_changing_tokens_or_batch(): void
    {
        $branchA = Branch::create(['name' => 'Santori']);
        $branchB = Branch::create(['name' => 'Santori 176']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($admin)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 4,
                'branch_id' => $branchA->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $batch = QuickOnboardingBatch::firstOrFail();
        $entries = QuickOnboardingEntry::oldest('id')->get();
        $selected = $entries->take(2);
        $originalTokens = $entries->pluck('token', 'id')->all();

        $this->actingAs($admin)
            ->post(route('quick-onboarding.bulk-update'), [
                'entry_ids' => $selected->pluck('id')->all(),
                'branch_id' => $branchB->id,
                'role' => 'manager',
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $this->assertSame(1, QuickOnboardingBatch::count());
        $this->assertSame($batch->id, QuickOnboardingBatch::first()->id);
        $this->assertSame(4, QuickOnboardingEntry::count());

        foreach ($selected as $entry) {
            $fresh = $entry->fresh();
            $this->assertSame($branchB->id, $fresh->branch_id);
            $this->assertSame('manager', $fresh->intended_role);
            $this->assertSame($originalTokens[$entry->id], $fresh->token);
            $this->assertStringContainsString($fresh->token, $fresh->invite_url);
        }

        foreach ($entries->skip(2) as $entry) {
            $fresh = $entry->fresh();
            $this->assertSame($branchA->id, $fresh->branch_id);
            $this->assertSame('staff', $fresh->intended_role);
            $this->assertSame($originalTokens[$entry->id], $fresh->token);
        }

        $this->actingAs($admin)
            ->get(route('quick-onboarding.index'))
            ->assertOk()
            ->assertSee('Santori 176')
            ->assertSee('Quan ly');
    }

    public function test_manager_cannot_bulk_update_to_other_branch_or_manager_role(): void
    {
        $branchA = Branch::create(['name' => 'Santori']);
        $branchB = Branch::create(['name' => 'Other Branch']);
        $manager = User::factory()->create([
            'role' => 'manager',
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($manager)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 2,
                'branch_id' => $branchA->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));

        $entryIds = QuickOnboardingEntry::pluck('id')->all();

        $this->actingAs($manager)
            ->from(route('quick-onboarding.index'))
            ->post(route('quick-onboarding.bulk-update'), [
                'entry_ids' => $entryIds,
                'branch_id' => $branchB->id,
                'role' => 'staff',
            ])
            ->assertRedirect(route('quick-onboarding.index'))
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($manager)
            ->from(route('quick-onboarding.index'))
            ->post(route('quick-onboarding.bulk-update'), [
                'entry_ids' => $entryIds,
                'branch_id' => $branchA->id,
                'role' => 'manager',
            ])
            ->assertRedirect(route('quick-onboarding.index'))
            ->assertSessionHasErrors('role');

        $this->assertTrue(QuickOnboardingEntry::get()->every(
            fn($entry) => $entry->branch_id === $branchA->id && $entry->intended_role === 'staff'
        ));
    }

    public function test_bulk_update_rejects_entries_outside_current_batch(): void
    {
        $branch = Branch::create(['name' => 'Santori']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 1,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));
        $currentEntry = QuickOnboardingEntry::firstOrFail();

        $this->actingAs($admin)
            ->post(route('quick-onboarding.store'), [
                'quantity' => 1,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('quick-onboarding.index'));
        $newEntry = QuickOnboardingEntry::latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('quick-onboarding.index'))
            ->post(route('quick-onboarding.bulk-update'), [
                'entry_ids' => [$currentEntry->id, $newEntry->id],
                'branch_id' => $branch->id,
                'role' => 'cashier',
            ])
            ->assertRedirect(route('quick-onboarding.index'))
            ->assertSessionHasErrors('entry_ids');

        $this->assertSame('staff', $currentEntry->fresh()->intended_role);
        $this->assertSame('staff', $newEntry->fresh()->intended_role);
    }

    public function test_staff_and_cashier_cannot_access_quick_onboarding(): void
    {
        foreach (['staff', 'cashier'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('quick-onboarding.index'))
                ->assertForbidden();
        }
    }

    public function test_quantity_is_validated(): void
    {
        $branch = Branch::create([
            'name' => 'Santori',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->from(route('quick-onboarding.index'))
            ->post(route('quick-onboarding.store'), [
                'quantity' => 0,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(
                route('quick-onboarding.index')
            )
            ->assertSessionHasErrors('quantity');
    }
}
