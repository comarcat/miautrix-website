<?php

namespace App\Support\Mail;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Phase 2 (E5-T1, §9 step 35) — overrides the SMTP mailer's runtime config from the
 * `Setting` rows an admin saves through the "Mail settings" Filament Page, so sending mail
 * never needs a redeploy. Called from AppServiceProvider::boot() — i.e. on every request AND
 * every artisan command, `migrate` included, so the `settings` table may not exist yet (a
 * brand-new checkout, before its own first migration runs). Guarded on both sides: skip
 * silently when the table is absent, and swallow (rather than crash boot on) any DB error —
 * a bad DB connection at boot must never take `php artisan` itself down.
 *
 * A no-op when `mail.host` has never been set (a plain checkout, or before the admin ever
 * opens the page) — `.env`'s own MAIL_* values keep driving the `smtp` mailer exactly as
 * before Phase 2.
 */
class ConfiguresMailFromSettings
{
    public function configure(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $host = Setting::get('mail.host');
        } catch (Throwable) {
            return;
        }

        if ($host === null || $host === '') {
            return;
        }

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => Setting::get('mail.port'),
            'mail.mailers.smtp.username' => Setting::get('mail.username'),
            'mail.mailers.smtp.password' => Setting::getEncrypted('mail.password'),
            'mail.mailers.smtp.encryption' => Setting::get('mail.encryption'),
            'mail.from.address' => Setting::get('mail.from_address', config('mail.from.address')),
            'mail.from.name' => Setting::get('mail.from_name', config('mail.from.name')),
        ]);
    }
}
