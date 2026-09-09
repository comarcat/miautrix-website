<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\Profile;
use App\Models\SocialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production request: "add to the social profiles, a field to
 * check if it should appear on the footer of the website, and we have to create a new
 * section to publish all social profiles there with their logos near to the links".
 */
class SocialProfilesFooterAndConnectTest extends TestCase
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

    private function makeIconMedia(int $socialProfileId): Media
    {
        return Media::create([
            'model_type' => SocialProfile::class,
            'model_id' => $socialProfileId,
            'collection_name' => 'default',
            'name' => 'icon',
            'file_name' => 'linkedin-icon.png',
            'mime_type' => 'image/png',
            'disk' => 'private-media',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    public function test_the_footer_shows_only_social_profiles_marked_show_in_footer(): void
    {
        $profile = $this->profile();

        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/example',
            'show_in_footer' => true,
            'sort_order' => 0,
        ]);
        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'Mastodon',
            'url' => 'https://mastodon.social/@example',
            'show_in_footer' => false,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('LinkedIn');
        $response->assertDontSee('Mastodon');
    }

    public function test_the_footer_shows_a_social_profiles_icon_when_one_is_attached(): void
    {
        $profile = $this->profile();
        $socialProfile = SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/example',
            'show_in_footer' => true,
            'sort_order' => 0,
        ]);
        $icon = $this->makeIconMedia($socialProfile->id);
        $socialProfile->update(['icon_media_id' => $icon->id]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('media.show', [$icon, $icon->file_name]), false);
    }

    public function test_the_connect_page_shows_every_social_profile_regardless_of_show_in_footer(): void
    {
        $profile = $this->profile();

        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/example',
            'show_in_footer' => true,
            'sort_order' => 0,
        ]);
        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'Mastodon',
            'url' => 'https://mastodon.social/@example',
            'show_in_footer' => false,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('connect'));

        $response->assertOk();
        $response->assertSee('LinkedIn');
        $response->assertSee('Mastodon');
    }

    public function test_the_connect_page_shows_an_empty_state_when_no_social_profiles_exist(): void
    {
        $this->profile();

        $response = $this->get(route('connect'));

        $response->assertOk();
        $response->assertSee('No social profiles published yet.');
    }

    public function test_the_connect_page_is_linked_from_the_main_navigation(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('connect'), false);
    }
}
