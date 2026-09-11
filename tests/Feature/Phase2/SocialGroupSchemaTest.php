<?php

namespace Tests\Feature\Phase2;

use App\Models\Profile;
use App\Models\SocialProfile;
use App\Models\SocialProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E2-T6 (Phase 2, p2-step-14) — `social_profile_groups` are ordered /connect sections and
 * `social_profiles.group_id` is a nullable `nullOnDelete` FK: deleting a group ungroups its
 * profiles, it never deletes them.
 */
class SocialGroupSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function socialProfile(?int $groupId = null): SocialProfile
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Engineer',
            'bio' => 'Bio.',
        ]);

        return SocialProfile::create([
            'profile_id' => $profile->id,
            'group_id' => $groupId,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/ada',
            'sort_order' => 0,
        ]);
    }

    public function test_the_schema_has_the_groups_table_and_the_nullable_group_fk(): void
    {
        $this->assertTrue(Schema::hasTable('social_profile_groups'));
        $this->assertTrue(Schema::hasColumns('social_profile_groups', [
            'name', 'heading', 'intro_text', 'sort_order', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumn('social_profiles', 'group_id'));

        // group_id must be nullable — an ungrouped profile is valid.
        $profile = $this->socialProfile();
        $this->assertNull($profile->fresh()->group_id);
    }

    public function test_deleting_a_group_nulls_group_id_and_keeps_the_profiles(): void
    {
        $group = SocialProfileGroup::factory()->create();
        $profile = $this->socialProfile($group->id);

        $this->assertSame($group->id, $profile->fresh()->group_id);

        $group->delete();

        $this->assertDatabaseHas('social_profiles', ['id' => $profile->id, 'group_id' => null]);
        $this->assertNotNull($profile->fresh());
    }

    public function test_the_relations_round_trip(): void
    {
        $group = SocialProfileGroup::factory()->create();
        $profile = $this->socialProfile($group->id);

        $this->assertTrue($group->socialProfiles->contains($profile));
        $this->assertTrue($profile->group->is($group));
    }
}
