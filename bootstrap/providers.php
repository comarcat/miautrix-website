<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;

// laravel/telescope's own provider (and App\Providers\TelescopeServiceProvider, which
// configures it) are deliberately NOT listed here — they're excluded from package
// auto-discovery (composer.json's extra.laravel.dont-discover) and registered conditionally,
// local-only, in AppServiceProvider::register() instead. Listing either here would register
// Telescope (and its /telescope routes) in every environment, including production.
return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
];
