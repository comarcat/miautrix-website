<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test for a real production report: the admin panel's avatar and the
 * starter-kit's own account menu showed two inconsistent things for the same account — a
 * local "SA" initials badge on one side, and Filament's default avatar provider (an external
 * request to ui-avatars.com, which img-src 'self' data: already blocks) on the other, with no
 * picture-upload feature anywhere to replace either with a real photo. Both now render the
 * same local initials badge.
 */
class InitialsAvatarProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_data_uri_not_an_external_request(): void
    {
        $user = User::factory()->create(['name' => 'Site Administrator']);

        $url = (new InitialsAvatarProvider)->get($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $url));
        $this->assertStringContainsString('>SA<', $svg);
    }

    public function test_a_name_with_xml_special_characters_produces_valid_escaped_markup(): void
    {
        $user = User::factory()->create(['name' => '<script>&Admin']);

        $url = (new InitialsAvatarProvider)->get($user);
        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $url));

        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'Generated SVG must be well-formed XML.');
    }

    public function test_the_admin_dashboard_never_requests_the_external_avatar_service(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('ui-avatars.com');
    }
}
