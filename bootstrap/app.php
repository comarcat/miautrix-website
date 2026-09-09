<?php

use App\Actions\Cache\CachePublicPage;
use App\Http\Middleware\ResolveTheme;
use App\Http\Middleware\SecurityHeaders;
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
        // Production sits behind Cloudflare, which terminates TLS and proxies to nginx over
        // plain HTTP (nginx has no cert of its own — see infra/provision.sh's vhost, port 80
        // only). Without this, Laravel never learns the original request was HTTPS: it
        // generates route()/url()/asset() links (and Livewire's own AJAX endpoint URL) as
        // http://, which the browser then refuses to reach at all under CSP's connect-src
        // 'self' (a real bug found in production review — the admin login button hung
        // forever because Livewire's own update request was blocked as cross-origin, an
        // http:// URL on an https:// page). '*' is safe here: the LXC sits on a private
        // subnet (10.11.1.31) with no direct public route — Cloudflare is the only path in.
        $middleware->trustProxies(at: '*');

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

        // Security headers (E5-T3, §9 step 27) — appended to the true global stack, not
        // web(append:), specifically so it also covers Filament's admin panel, which builds
        // its own separate middleware array in AdminPanelProvider and would never see a
        // web-group-scoped addition.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
