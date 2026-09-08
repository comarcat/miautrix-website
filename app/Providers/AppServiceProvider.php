<?php

namespace App\Providers;

use App\Contracts\AnalyticsProviderInterface;
use App\Support\Analytics\NullAnalyticsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeServiceProvider as PackageTelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Non-Goal #12: no analytics vendor wired in v1. A concrete need is what would
        // justify swapping this binding for a real provider.
        $this->app->bind(AnalyticsProviderInterface::class, NullAnalyticsProvider::class);

        $this->registerTelescopeIfLocal();
    }

    /**
     * Telescope is local-only (§9 step 13): excluded from package auto-discovery
     * (composer.json's extra.laravel.dont-discover) and from bootstrap/providers.php,
     * registered here only when the environment actually is local. In any other
     * environment neither provider exists, so Telescope's routes (including /telescope
     * itself) are never registered at all — a request to them 404s because there is
     * nothing to match, not because of an auth check that could be misconfigured.
     */
    private function registerTelescopeIfLocal(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        $this->app->register(PackageTelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
