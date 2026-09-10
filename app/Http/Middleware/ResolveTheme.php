<?php

namespace App\Http\Middleware;

use App\Support\Theming\ThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * E4-T2 (§9 step 20) — reads the `miautrix_theme` cookie and shares the resolved theme
 * with every view as `$theme`, so `resources/views/layouts/app.blade.php` can render
 * `data-theme="{{ $theme ?? 'technical' }}"` correctly on the very first response — no
 * client-side toggle-after-paint, no flash.
 *
 * Only `'matrix'` is ever trusted from the cookie; any other value (absent, tampered,
 * a stale value from a future theme) resolves to `'technical'`, which itself still
 * respects `prefers-color-scheme` client-side via the plain CSS media query in app.css.
 * That fallback is what satisfies acceptance criterion 1 — this middleware does not need
 * to know anything about the system preference, since Theme A already handles it in CSS.
 *
 * Phase 2 (E3-T3, §9 step 19): when `config('site.themes.dynamic')` is ON, resolution is
 * delegated to ThemeResolver (DB rows: enabled + date window + single default). When OFF —
 * the default, and every pre-Phase-2 environment — the literal path below is untouched, so
 * output stays byte-identical.
 */
class ResolveTheme
{
    public const COOKIE_NAME = 'miautrix_theme';

    public function handle(Request $request, Closure $next): Response
    {
        if (config('site.themes.dynamic')) {
            $theme = app(ThemeResolver::class)->active($request)->key;
        } else {
            $theme = $request->cookie(self::COOKIE_NAME) === 'matrix' ? 'matrix' : 'technical';
        }

        View::share('theme', $theme);

        return $next($request);
    }
}
