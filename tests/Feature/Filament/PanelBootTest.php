<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E3-T1 — Filament install, panel config, mandatory MFA, Telescope local-only (§9 step 13).
 */
class PanelBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_renders_the_filament_login_form(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Email address');
        $response->assertSee('Password');
    }

    public function test_a_super_admin_with_unconfirmed_mfa_is_redirected_to_enrolment_before_reaching_the_dashboard(): void
    {
        $admin = User::factory()->create(); // two_factor_confirmed_at null by default
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertRedirect(route('security.edit'));
    }

    public function test_telescope_404s_outside_a_local_environment(): void
    {
        // APP_ENV=testing here (phpunit.xml), never local — TelescopeServiceProvider is only
        // registered when app()->environment('local') (AppServiceProvider), so /telescope
        // has no route at all to match in this environment.
        $this->assertFalse(app()->environment('local'));

        $response = $this->get('/telescope');

        $response->assertNotFound();
    }
}
