<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
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

    /**
     * Regression test for a real production report: a confirmed super_admin, once inside
     * the panel, had no way to reach their own account/2FA settings again — the panel had no
     * ->profile() page and the default user menu carried no link back to it. These two items
     * point at the starter-kit's own settings pages (never gated by EnsureMfaConfirmed), the
     * same ones the MFA-required banner already links to.
     */
    public function test_a_confirmed_super_admin_sees_profile_and_security_links_in_the_user_menu(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee(route('profile.edit'), false);
        $response->assertSee(route('security.edit'), false);
    }

    /**
     * Regression test for a real production report: after a successful save, Filament's
     * own default keeps you on the record's edit page (and a new record lands straight on
     * ITS edit page) rather than returning you to the list — reported as "after saving, I
     * should be back on the list". Panel-level, so every resource gets it, not just the one
     * it was found on. A validation error never reaches this: Livewire halts on validate()
     * before the save step runs, so the existing inline-under-each-field error display is
     * unaffected — this only changes where a SUCCESSFUL save sends you.
     */
    public function test_saving_a_resource_redirects_to_its_list_page_not_back_to_the_record(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('index', $panel->getResourceCreatePageRedirect());
        $this->assertSame('index', $panel->getResourceEditPageRedirect());
    }

    /**
     * Regression test for a real production request: "I like the /dashboard UI, make the
     * admin match it" — that layout (resources/views/layouts/app/sidebar.blade.php)
     * hardcodes <html class="dark"> with no toggle at all. The panel's dark mode is now
     * forced the same way (no light/dark switcher), matching that always-dark feel.
     */
    public function test_the_panel_forces_dark_mode_with_no_switcher(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasDarkMode());
        $this->assertTrue($panel->hasDarkModeForced());
    }

    /**
     * Regression test for a real production report: "there are only icons on the admin top
     * menu no text, please return the text plus the icons" — a plain ->brandLogo(url)
     * replaces Filament's default text-only brand entirely with just the image, no label
     * alongside it. brandLogo() accepts Htmlable, not just a URL string, so it now renders
     * both the logo image and a "miautrix" text label together.
     */
    public function test_the_brand_logo_includes_the_site_name_alongside_the_image(): void
    {
        $panel = Filament::getPanel('admin');
        $brandLogo = (string) $panel->getBrandLogo();

        $this->assertStringContainsString('miautrix', $brandLogo);
        $this->assertStringContainsString('<img', $brandLogo);
        $this->assertStringContainsString('images/brand/miautrix-logo.png', $brandLogo);
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
