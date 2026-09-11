<?php

namespace Tests\Feature\Phase2;

use App\Filament\Pages\MailSettings;
use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\Mail\ConfiguresMailFromSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E5-T1 (Phase 2, p2-step-35) — the admin-configurable SMTP settings page persists every
 * field (the password encrypted), ConfiguresMailFromSettings makes them take effect (falling
 * back to .env when nothing is saved), and "Send test email" never echoes the password back.
 */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_saving_the_page_persists_every_field_with_the_password_encrypted(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(MailSettings::class)
            ->fillForm([
                'host' => 'smtp.example.test',
                'port' => 2525,
                'username' => 'no-reply',
                'password' => 'correct-horse-battery-staple',
                'encryption' => 'tls',
                'from_address' => 'no-reply@miautrix.tech',
                'from_name' => 'miautrix',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('smtp.example.test', Setting::get('mail.host'));
        $this->assertSame('2525', Setting::get('mail.port'));
        $this->assertSame('no-reply', Setting::get('mail.username'));
        $this->assertSame('tls', Setting::get('mail.encryption'));
        $this->assertSame('no-reply@miautrix.tech', Setting::get('mail.from_address'));
        $this->assertSame('miautrix', Setting::get('mail.from_name'));

        // Encrypted at rest — the raw DB value is never the plaintext password.
        $this->assertNotSame('correct-horse-battery-staple', Setting::get('mail.password'));
        $this->assertSame('correct-horse-battery-staple', Setting::getEncrypted('mail.password'));
    }

    public function test_configuring_from_settings_overrides_config_and_falls_back_to_env_when_unset(): void
    {
        $defaultHost = config('mail.mailers.smtp.host');
        $defaultFrom = config('mail.from.address');

        (new ConfiguresMailFromSettings)->configure();
        $this->assertSame($defaultHost, config('mail.mailers.smtp.host'));
        $this->assertSame($defaultFrom, config('mail.from.address'));

        Setting::put('mail.host', 'smtp.saved.test');
        Setting::put('mail.from_address', 'saved@miautrix.tech');

        (new ConfiguresMailFromSettings)->configure();

        $this->assertSame('smtp.saved.test', config('mail.mailers.smtp.host'));
        $this->assertSame('saved@miautrix.tech', config('mail.from.address'));
    }

    public function test_send_test_email_dispatches_test_mail_to_the_from_address_and_flashes_success(): void
    {
        Mail::fake();
        Setting::put('mail.from_address', 'no-reply@miautrix.tech');

        Livewire::actingAs($this->superAdmin())
            ->test(MailSettings::class)
            ->callAction('sendTestEmail');

        Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('no-reply@miautrix.tech'));
    }

    public function test_send_test_email_never_echoes_the_stored_password(): void
    {
        Mail::fake();
        Setting::put('mail.from_address', 'no-reply@miautrix.tech');
        Setting::putEncrypted('mail.password', 'super-secret-password');

        $html = Livewire::actingAs($this->superAdmin())
            ->test(MailSettings::class)
            ->callAction('sendTestEmail')
            ->html();

        $this->assertStringNotContainsString('super-secret-password', $html);
    }

    /**
     * Regression test for a real production bug found live after the Phase 2 deploy, reported
     * as "the admin mail configuration is empty": the page's own Blade view
     * (resources/views/filament/pages/mail-settings.blade.php) was a bare skeleton that never
     * rendered {{ $this->form }} at all, so the "Mail settings" page showed nothing but its
     * title and the "Send test email" header action — no form, no fields, nothing to fill in
     * or save. Every other test in this file drives the Livewire component directly via
     * Livewire::test(MailSettings::class), which never touches the page's own view file, so
     * none of them could have caught this. This one requests the real page URL instead.
     */
    public function test_the_page_actually_renders_the_form_fields_not_just_the_component(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(MailSettings::getUrl());

        $response->assertOk();
        $response->assertSee('wire:submit', false);
        foreach (['host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name'] as $field) {
            $response->assertSee('wire:model="data.' . $field . '"', false);
        }
    }
}
