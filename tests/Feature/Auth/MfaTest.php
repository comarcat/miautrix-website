<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E2-T5 — Fortify + mandatory TOTP MFA, rate limiting, login audit (blueprint §9 step 10).
 */
class MfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_confirmed_mfa_requesting_admin_is_redirected_to_the_enrolment_screen(): void
    {
        $user = User::factory()->create(); // two_factor_confirmed_at null by default
        $this->assignSuperAdmin($user);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect(route('security.edit'));
    }

    public function test_a_user_with_confirmed_mfa_can_reach_admin(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->assignSuperAdmin($user);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    /**
     * Regression test for a real production bug found in review: an admin redirected here
     * by EnsureMfaConfirmed saw only account/password/2FA settings, with nothing explaining
     * why, and reported the admin panel's content management as entirely missing. The
     * redirect is correct — MFA is mandatory — but it needs to say so.
     */
    public function test_the_mfa_redirect_shows_a_banner_explaining_why_the_admin_panel_is_unreachable(): void
    {
        $user = User::factory()->create(); // two_factor_confirmed_at null by default
        $this->assignSuperAdmin($user);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertRedirect(route('security.edit'));

        $followed = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->followingRedirects()
            ->get('/admin');

        $followed->assertOk();
        $followed->assertSee('Two-factor authentication required');
    }

    public function test_visiting_security_settings_directly_shows_no_mfa_required_banner(): void
    {
        $user = User::factory()->create();
        $this->assignSuperAdmin($user);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();
        $response->assertDontSee('Two-factor authentication required');
    }

    /**
     * /admin is Filament's real panel as of E3-T1 — Filament's own Authenticate middleware
     * 403s any user that doesn't implement canAccessPanel() truthfully (User::canAccessPanel
     * checks hasRole('super_admin')), independently of and before EnsureMfaConfirmed ever
     * runs. These MFA tests care about the MFA gate specifically, so every user reaching
     * /admin here needs the role first, same as PolicyTest and PanelBootTest.
     */
    private function assignSuperAdmin(User $user): void
    {
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    }

    public function test_confirming_a_valid_totp_code_at_enrolment_sets_two_factor_confirmed_at_and_shows_recovery_codes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.enable'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $secret = decrypt($user->two_factor_secret);
        $validCode = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.confirm'), ['code' => $validCode])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $recoveryCodesResponse = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.recovery-codes'));

        $recoveryCodesResponse->assertOk();
        $storedCodes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
        $this->assertNotEmpty($storedCodes);
        $this->assertSame($storedCodes, $recoveryCodesResponse->json());
    }

    public function test_sixth_failed_login_for_the_same_email_and_ip_within_15_minutes_responds_429_and_logs_the_attempt(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertSame(6, AuditLog::where('action', 'login_failed')->count());
    }

    public function test_a_successful_login_writes_one_audit_log_row_with_login_success(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AuditLog::where('action', 'login_success')->where('user_id', $user->id)->count());
    }

    public function test_a_failed_login_writes_one_audit_log_row_with_login_failed(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertSame(1, AuditLog::where('action', 'login_failed')->count());
    }
}
