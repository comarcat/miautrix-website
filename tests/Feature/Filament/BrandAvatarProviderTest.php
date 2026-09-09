<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\BrandAvatarProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test for a real production request: "use that image as ... profile picture for
 * the admin" — the admin panel's avatar now shows the real miautrix brand artwork instead of
 * the local initials badge it briefly used (added to avoid Filament's default
 * UiAvatarsProvider, an external request to ui-avatars.com that img-src 'self' data: already
 * blocks). Still served locally, so that CSP reasoning still holds.
 */
class BrandAvatarProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_local_brand_image_not_an_external_request(): void
    {
        $user = User::factory()->create(['name' => 'Site Administrator']);

        $url = (new BrandAvatarProvider)->get($user);

        $this->assertStringContainsString('/images/brand/miautrix-avatar.png', $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);
        $this->assertStringStartsWith(config('app.url'), $url);
    }

    public function test_the_admin_dashboard_never_requests_the_external_avatar_service(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('ui-avatars.com');
        $response->assertSee('/images/brand/miautrix-avatar.png', false);
    }
}
