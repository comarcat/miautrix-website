<?php

namespace App\Providers\Filament;

use App\Filament\Support\BrandAvatarProvider;
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
use Illuminate\Support\HtmlString;
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
            // Found in review: "I like the /dashboard UI, make the admin match it" — that
            // starter-kit layout (resources/views/layouts/app/sidebar.blade.php) hardcodes
            // <html class="dark"> with no toggle at all, and leans on Flux's neutral zinc
            // palette rather than a bright accent. Forcing dark mode (no switcher) and
            // swapping the accent from Amber to Zinc gets the panel's overall feel — dark,
            // neutral, understated — much closer to that without a full custom Filament
            // theme rebuild (a separate, much larger undertaking: Filament's own compiled
            // CSS, fonts, and spacing are a different Tailwind build entirely from
            // resources/css/authenticated.css).
            ->darkMode(isForced: true)
            ->colors([
                'primary' => Color::Zinc,
            ])
            // Found in review: "there are only icons on the admin top menu no text, please
            // return the text plus the icons" — a plain ->brandLogo(url) replaces Filament's
            // default text-only brand entirely with just the image, no label alongside it.
            // Passing HTML (brandLogo() accepts Htmlable, not just a URL string) keeps both,
            // matching the public header's own logo+"miautrix" text lockup
            // (resources/views/layouts/app.blade.php).
            ->brandLogo(new HtmlString(
                '<span style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600">'
                . '<img src="' . asset('images/brand/miautrix-logo.png') . '" alt="" style="height:2rem;width:2rem;object-fit:contain">'
                . '<span>miautrix</span>'
                . '</span>',
            ))
            ->brandLogoHeight('2rem')
            // Found in review: "use that image as ... profile picture for the admin" — the
            // real brand artwork, served locally (img-src 'self'), replacing the initials
            // badge this used to generate to avoid Filament's default UiAvatarsProvider (an
            // external request to ui-avatars.com that img-src 'self' data: already blocks).
            ->defaultAvatarProvider(BrandAvatarProvider::class)
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
