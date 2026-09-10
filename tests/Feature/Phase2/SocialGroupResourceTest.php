<?php

namespace Tests\Feature\Phase2;

use App\Filament\Resources\SocialProfileGroups\Pages\ListSocialProfileGroups;
use App\Filament\Resources\SocialProfileGroups\SocialProfileGroupResource;
use App\Filament\Resources\SocialProfiles\Pages\CreateSocialProfile;
use App\Models\SocialProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E2-T7 (Phase 2, p2-step-15) — the SocialProfileGroups admin resource is super_admin-only
 * and reorderable by `sort_order`, and the SocialProfile form offers a `group_id` Select that
 * creates a new group inline.
 */
class SocialGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_the_index_is_reachable_and_reorderable_for_the_super_admin(): void
    {
        SocialProfileGroup::factory()->create(['sort_order' => 1]);
        SocialProfileGroup::factory()->create(['sort_order' => 0]);

        $this->actingAs($this->superAdmin())
            ->get(SocialProfileGroupResource::getUrl('index'))
            ->assertOk();

        $a = SocialProfileGroup::factory()->create();
        $b = SocialProfileGroup::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(ListSocialProfileGroups::class)
            ->call('reorderTable', [$b->getKey(), $a->getKey()]);

        $this->assertTrue($b->refresh()->sort_order < $a->refresh()->sort_order);
    }

    public function test_a_non_super_admin_is_denied(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->get(SocialProfileGroupResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_the_social_profile_form_creates_a_group_inline(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateSocialProfile::class)
            ->assertFormFieldExists('group_id')
            ->callFormComponentAction('group_id', 'createOption', data: [
                'name' => 'Open Source',
                'heading' => 'Open-source work',
                'sort_order' => 0,
            ])
            ->assertHasNoFormComponentActionErrors();

        $this->assertDatabaseHas('social_profile_groups', [
            'name' => 'Open Source',
            'heading' => 'Open-source work',
        ]);
    }
}
