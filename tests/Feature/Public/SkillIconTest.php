<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production request: "the icons that I mentioned is for the
 * skills like Windows Server -> Icon of Windows Server, Sharepoint -> Icon of the logo of
 * SharePoint" — the /skills page had no way to show a per-skill icon at all (Skill had no
 * icon column).
 */
class SkillIconTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);
    }

    public function test_the_skills_page_shows_an_icon_when_one_is_attached(): void
    {
        $profile = $this->profile();
        $category = SkillCategory::create(['name' => 'Infrastructure', 'sort_order' => 0]);

        $skill = Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'Windows Server',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);
        $icon = Media::create([
            'model_type' => Skill::class,
            'model_id' => $skill->id,
            'collection_name' => 'default',
            'name' => 'icon',
            'file_name' => 'windows-server-icon.png',
            'mime_type' => 'image/png',
            'disk' => 'private-media',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
        $skill->update(['icon_media_id' => $icon->id]);

        $response = $this->get(route('skills'));

        $response->assertOk();
        $response->assertSee(route('media.show', [$icon, $icon->file_name]), false);
    }

    public function test_a_skill_with_no_icon_renders_without_one(): void
    {
        $profile = $this->profile();
        $category = SkillCategory::create(['name' => 'Infrastructure', 'sort_order' => 0]);

        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'Generic Practice',
            'proficiency' => 'advanced',
            'sort_order' => 0,
        ]);

        $response = $this->get(route('skills'));

        $response->assertOk();
        $response->assertSee('Generic Practice');
    }
}
