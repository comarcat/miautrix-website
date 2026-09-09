<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\EnsureMfaConfirmed;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Found in review: Filament's own default avatar provider (UiAvatarsProvider)
            // calls out to https://ui-avatars.com — a request `img-src 'self' data:` already
            // blocks, and there's no picture-upload feature anywhere in this app for a real
            // photo to replace it with. The starter kit's own pages never made that external
            // call either (Flux's <flux:avatar :initials="...">, rendered locally) — this
            // panel now matches that with the same local, initials-only badge.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Found in review: Filament's own default keeps you on the record's edit page
            // after a successful save (and lands a new record straight on ITS edit page too)
            // rather than returning to the list — reported as confusing ("after saving,
            // I should be back on the list"). A validation error never reaches this at all
            // (Livewire halts on validate() before the save step runs), so the existing
            // inline-under-each-field error display is untouched by this — it only changes
            // where a SUCCESSFUL save sends you. Applies to every resource, not just the one
            // it was found on.
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            // Found in review: this panel has no ->profile() page of its own, and its
            // default user menu has nowhere to manage the admin's OWN account — including
            // 2FA. That left literally no link, from inside the panel, back to the
            // password/2FA/passkey settings a super_admin might need to change (only the
            // one-way EnsureMfaConfirmed redirect got them there the first time). These two
            // items point at the same starter-kit settings pages (routes/settings.php,
            // never gated by EnsureMfaConfirmed) the MFA-required banner already links to.
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label('My profile')
                    ->icon(Heroicon::UserCircle)
                    ->url(fn () => route('profile.edit')),
                'security' => Action::make('security')
                    ->label('Security & 2FA')
                    ->icon(Heroicon::ShieldCheck)
                    ->url(fn () => route('security.edit'))
                    ->sort(0),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Mandatory MFA (E2-T5): blocks every authenticated panel page — not the
                // login page itself, which never has a user yet — until two_factor_confirmed_at
                // is set. Reuses the same middleware /admin's pre-Filament placeholder route
                // used in E2-T5, redirecting to the existing security settings page rather
                // than a Filament-native enrolment screen.
                EnsureMfaConfirmed::class,
            ]);
    }
}
