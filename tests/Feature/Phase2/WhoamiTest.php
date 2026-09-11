<?php

namespace Tests\Feature\Phase2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E5-T4 (Phase 2, p2-step-38) — GET /whoami returns an IP-derived payload (null geo fields
 * without the GeoLite2 .mmdb, no `mac` key ever), and Permissions-Policy offers
 * geolocation=(self) on public routes while /admin keeps geolocation=().
 */
class WhoamiTest extends TestCase
{
    use RefreshDatabase;

    public function test_whoami_returns_the_ip_and_null_geo_fields_without_the_mmdb(): void
    {
        $response = $this->get('/whoami');

        $response->assertOk();
        $response->assertJson([
            'isp' => null,
            'city' => null,
            'region' => null,
            'country' => null,
            'timezone' => null,
            'screen' => null,
            'gps' => null,
        ]);
        $this->assertNotEmpty($response->json('ip'));
        $this->assertNotEmpty($response->json('ua'));
    }

    public function test_whoami_never_includes_a_mac_key(): void
    {
        $response = $this->get('/whoami');

        $response->assertOk();
        $this->assertArrayNotHasKey('mac', $response->json());
    }

    public function test_public_routes_send_geolocation_self_and_admin_keeps_it_blocked(): void
    {
        $public = $this->get('/');
        $this->assertStringContainsString('geolocation=(self)', (string) $public->headers->get('Permissions-Policy'));

        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $adminResponse = $this->actingAs($admin)->get('/admin');
        $this->assertStringContainsString('geolocation=()', (string) $adminResponse->headers->get('Permissions-Policy'));
    }

    public function test_whoami_is_not_cached_and_reflects_the_current_user_agent(): void
    {
        $response = $this->withHeader('User-Agent', 'PhaseTwoTestAgent/1.0')->get('/whoami');

        $response->assertOk();
        $response->assertJson(['ua' => 'PhaseTwoTestAgent/1.0']);
    }
}
