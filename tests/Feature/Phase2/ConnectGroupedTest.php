<?php

namespace Tests\Feature\Phase2;

use App\Models\Profile;
use App\Models\SocialProfile;
use App\Models\SocialProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E2-T8 (Phase 2, p2-step-16) — /connect renders one section per SocialProfileGroup in
 * sort_order with a trailing "Other" section for ungrouped profiles, and any group/profile
 * save busts the cached page for every seeded theme. The footer stays show_in_footer-only.
 */
class ConnectGroupedTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Engineer',
            'bio' => 'Bio.',
        ]);
    }

    private function socialProfile(Profile $profile, string $platform, ?int $groupId = null): SocialProfile
    {
        return SocialProfile::create([
            'profile_id' => $profile->id,
            'group_id' => $groupId,
            'platform' => $platform,
            'url' => 'https://example.test/' . strtolower($platform),
            'show_in_footer' => false,
            'sort_order' => 0,
        ]);
    }

    public function test_it_renders_one_section_per_group_in_sort_order_with_heading_and_intro(): void
    {
        $profile = $this->profile();

        $work = SocialProfileGroup::factory()->create([
            'heading' => 'Professional', 'intro_text' => 'Where I work in the open.', 'sort_order' => 0,
        ]);
        $play = SocialProfileGroup::factory()->create([
            'heading' => 'Personal', 'intro_text' => 'The rest of me.', 'sort_order' => 1,
        ]);

        $this->socialProfile($profile, 'LinkedIn', $work->id);
        $this->socialProfile($profile, 'Twitch', $play->id);

        $response = $this->get(route('connect'));

        $response->assertOk();
        $response->assertSeeInOrder(['Professional', 'Where I work in the open.', 'LinkedIn', 'Personal', 'The rest of me.', 'Twitch']);
        $response->assertSeeInOrder(['data-social-group="' . $work->id . '"', 'LinkedIn'], false);
    }

    public function test_an_ungrouped_profile_lands_in_a_trailing_other_section(): void
    {
        $profile = $this->profile();
        $group = SocialProfileGroup::factory()->create(['heading' => 'Professional', 'sort_order' => 0]);

        $this->socialProfile($profile, 'LinkedIn', $group->id);
        $this->socialProfile($profile, 'Mastodon', null);

        $response = $this->get(route('connect'));

        $response->assertOk();
        $response->assertSeeInOrder(['Professional', 'Other', 'Mastodon']);
        $response->assertSee('data-social-group="other"', false);
    }

    public function test_saving_a_group_or_a_profile_forgets_the_connect_cache_for_every_seeded_theme(): void
    {
        $profile = $this->profile();

        foreach (['technical', 'matrix'] as $theme) {
            Cache::put("public-page:127.0.0.1:{$theme}:connect", 'stale', 600);
        }
        SocialProfileGroup::factory()->create();
        foreach (['technical', 'matrix'] as $theme) {
            $this->assertFalse(Cache::has("public-page:127.0.0.1:{$theme}:connect"), "group save left {$theme} stale");
        }

        foreach (['technical', 'matrix'] as $theme) {
            Cache::put("public-page:127.0.0.1:{$theme}:connect", 'stale', 600);
        }
        $this->socialProfile($profile, 'LinkedIn', null);
        foreach (['technical', 'matrix'] as $theme) {
            $this->assertFalse(Cache::has("public-page:127.0.0.1:{$theme}:connect"), "profile save left {$theme} stale");
        }
    }

    public function test_the_footer_still_shows_only_show_in_footer_profiles(): void
    {
        $profile = $this->profile();
        $group = SocialProfileGroup::factory()->create();

        SocialProfile::create([
            'profile_id' => $profile->id, 'group_id' => $group->id, 'platform' => 'LinkedIn',
            'url' => 'https://example.test/li', 'show_in_footer' => true, 'sort_order' => 0,
        ]);
        SocialProfile::create([
            'profile_id' => $profile->id, 'group_id' => $group->id, 'platform' => 'Mastodon',
            'url' => 'https://example.test/m', 'show_in_footer' => false, 'sort_order' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('LinkedIn');
        $response->assertDontSee('Mastodon');
    }
}
