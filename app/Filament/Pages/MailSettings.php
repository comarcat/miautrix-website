<?php

namespace App\Filament\Pages;

use App\Mail\TestMail;
use App\Models\Setting;
use App\Support\Mail\ConfiguresMailFromSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E5-T1 (§9 step 35) — admin-configurable outbound SMTP. Every field except the password is
 * plain-text in the DB (Setting::get/put); the password uses Setting::getEncrypted/
 * putEncrypted so it's never stored in cleartext. The password field itself is never
 * re-filled from the stored value — mount() leaves it blank, and save() only overwrites the
 * stored password when the admin actually types a new one, so "Save" with it left blank
 * doesn't wipe out a working credential.
 */
class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.mail-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mail settings';

    protected static ?string $title = 'Mail settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * BUG FIXED (found live after the Phase 2 deploy — reported as "mail configuration is
     * empty"): before any admin ever saves through this page, every Setting::get() call below
     * is null, so the form rendered completely blank even though .env's MAIL_* values are
     * actively driving outbound mail right now (see ConfiguresMailFromSettings's own no-op
     * fallback). That read as broken/unconfigured rather than "using the .env default,
     * nothing saved here yet." Each field now falls back to the currently-effective
     * config('mail.mailers.smtp.*') value, so the page shows what's actually in effect;
     * save() is unchanged and still only persists what the admin explicitly submits.
     */
    public function mount(): void
    {
        $this->getSchema('form')->fill([
            'host' => Setting::get('mail.host') ?? config('mail.mailers.smtp.host'),
            'port' => Setting::get('mail.port') ?? config('mail.mailers.smtp.port'),
            'username' => Setting::get('mail.username') ?? config('mail.mailers.smtp.username'),
            'password' => null,
            'encryption' => Setting::get('mail.encryption') ?? config('mail.mailers.smtp.encryption'),
            'from_address' => Setting::get('mail.from_address') ?? config('mail.from.address'),
            'from_name' => Setting::get('mail.from_name') ?? config('mail.from.name'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('host'),
                TextInput::make('port')->numeric(),
                TextInput::make('username'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->helperText('Leave blank to keep the currently stored password unchanged.'),
                Select::make('encryption')
                    ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']),
                TextInput::make('from_address')->email(),
                TextInput::make('from_name'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->getSchema('form')->getState();

        Setting::put('mail.host', $data['host'] ?? null);
        Setting::put('mail.port', $data['port'] !== null ? (string) $data['port'] : null);
        Setting::put('mail.username', $data['username'] ?? null);
        Setting::put('mail.encryption', $data['encryption'] ?? null);
        Setting::put('mail.from_address', $data['from_address'] ?? null);
        Setting::put('mail.from_name', $data['from_name'] ?? null);

        if (! empty($data['password'])) {
            Setting::putEncrypted('mail.password', $data['password']);
        }

        app(ConfiguresMailFromSettings::class)->configure();

        Notification::make()
            ->title('Mail settings saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label('Send test email')
                ->color('gray')
                ->action(fn () => $this->sendTestEmail()),
        ];
    }

    /**
     * Never echoes the stored password back anywhere — success/failure is the only signal.
     */
    public function sendTestEmail(): void
    {
        app(ConfiguresMailFromSettings::class)->configure();

        $to = Setting::get('mail.from_address', config('mail.from.address'));

        try {
            Mail::to($to)->send(new TestMail);

            Notification::make()
                ->title("Test email sent to {$to}")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Test email failed to send')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
