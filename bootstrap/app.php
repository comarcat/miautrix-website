<?php

use App\Actions\Cache\CachePublicPage;
use App\Http\Middleware\ResolveTheme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolveTheme shares $theme with every web-group view (E4-T2, §9 step 20) — the
        // public pages, dashboard, and settings pages all resolve through this group.
        // Filament's admin panel builds its own separate middleware stack in
        // AdminPanelProvider and never touches $theme, so it is untouched here.
        $middleware->web(append: [ResolveTheme::class]);

        // Database-driven page cache (E5-T2, §9 step 26) — an alias, not appended to the web
        // group, so it is attached explicitly per-route in routes/web.php to the actual
        // public content pages only. Never applied blanket-wide: /theme, the Livewire
        // contact-form endpoint, and streamed file downloads must never be cached.
        $middleware->alias(['cache.public' => CachePublicPage::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
