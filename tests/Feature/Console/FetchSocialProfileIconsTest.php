<?php

namespace Tests\Feature\Console;

use App\Models\Profile;
use App\Models\SocialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSocialProfileIconsTest extends TestCase
{
    use RefreshDatabase;

    // A tiny real 4x4 PNG — small enough to inline, real enough for GD to decode.
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkEpFjgAEmBiSAmwMADKgARK9yomwAAAAASUVORK5CYII=';

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Test',
            'headline' => 'Test',
            'bio' => 'Test.',
        ]);
    }

    public function test_it_attaches_an_icon_to_a_known_platform_regardless_of_casing(): void
    {
        Http::fake(fn () => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']));

        $profile = $this->profile();
        $socialProfile = SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'linkedin',
            'url' => 'https://www.linkedin.com/in/example',
            'sort_order' => 0,
        ]);

        $this->artisan('app:fetch-social-profile-icons')->assertSuccessful();

        $this->assertNotNull($socialProfile->fresh()->icon_media_id);
    }

    public function test_it_leaves_an_unknown_platform_without_an_icon(): void
    {
        Http::fake(fn () => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']));

        $profile = $this->profile();
        $socialProfile = SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'MyNicheSelfHostedForum',
            'url' => 'https://forum.example.com/@me',
            'sort_order' => 0,
        ]);

        $this->artisan('app:fetch-social-profile-icons')->assertSuccessful();

        $this->assertNull($socialProfile->fresh()->icon_media_id);
    }

    public function test_it_does_nothing_when_no_social_profiles_exist(): void
    {
        Http::fake(fn () => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']));

        $this->artisan('app:fetch-social-profile-icons')->assertSuccessful();
    }
}
