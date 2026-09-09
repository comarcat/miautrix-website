<?php

namespace App\Providers;

use App\Contracts\AnalyticsProviderInterface;
use App\Models\Article;
use App\Models\Project;
use App\Models\Setting;
use App\Observers\ArticleObserver;
use App\Observers\ProjectObserver;
use App\Observers\SettingObserver;
use App\Support\Analytics\NullAnalyticsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
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

        // resources/views/feed.xml.blade.php responds to view('feed') (E4-T6, §9 step 24) —
        // this task's own verify command calls view('feed', ...) directly, and the route
        // needs the same name to set the RSS content-type on the response rather than on
        // the view itself. Registered before 'blade.php' so the more specific extension
        // wins the finder's file_exists() probe for a name that also happens to end .xml.
        View::addExtension('xml.blade.php', 'blade');

        // Invalidates a Project's cached detail AND index page the instant `published`
        // changes, regardless of what triggered the save (E5-T2, §9 step 26).
        Project::observe(ProjectObserver::class);

        // Same for Article — found missing entirely in production review (see
        // ArticleObserver's own docblock).
        Article::observe(ArticleObserver::class);

        // The home page now reads its hero text from Settings — busts the home page cache
        // on any Setting save/delete (see SettingObserver's own docblock).
        Setting::observe(SettingObserver::class);
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
