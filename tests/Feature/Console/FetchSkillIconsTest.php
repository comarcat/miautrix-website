<?php

namespace Tests\Feature\Console;

use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSkillIconsTest extends TestCase
{
    use RefreshDatabase;

    // A tiny real 4x4 PNG — small enough to inline, real enough for GD to decode.
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkEpFjgAEmBiSAmwMADKgARK9yomwAAAAASUVORK5CYII=';

    public function test_it_attaches_an_icon_to_a_matching_skill(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Test',
            'headline' => 'Test',
            'bio' => 'Test.',
        ]);
        $category = SkillCategory::create(['name' => 'Infrastructure', 'sort_order' => 0]);
        $skill = Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'VMware',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);

        $this->artisan('app:fetch-skill-icons')->assertSuccessful();

        $this->assertNotNull($skill->fresh()->icon_media_id);
    }

    public function test_it_skips_gracefully_when_no_matching_skill_exists(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        // No Skill rows at all — every lookup misses, nothing should error.
        $this->artisan('app:fetch-skill-icons')->assertSuccessful();
    }
}
