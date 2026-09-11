<?php

namespace App\Actions\Cache;

use App\Http\Middleware\ResolveTheme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * E5-T2 (§9 step 26) — database-driven page cache keyed by route + query string (Non-Goal
 * #2: no Redis, so this rides Laravel's own `cache` table via CACHE_STORE=database).
 *
 * Doubles as route middleware: registered under the 'cache.public' alias in bootstrap/app.php
 * and attached only to the actual public content GET routes in routes/web.php — never
 * blanket-applied to the whole web group, so /theme, the Livewire contact-form endpoint, and
 * streamed file downloads (media/document) are never at risk of being cached by accident.
 *
 * A cache HIT genuinely short-circuits before the controller runs (no DB query happens on a
 * cached request) — that's what acceptance criterion 1 actually needs verified: the second
 * response must still reflect what was true when it was cached, even if the underlying row
 * has since changed, which a response-time assertion alone couldn't prove.
 *
 * BUG FIXED (found in production review): the cache key originally did not vary by the
 * `miautrix_theme` cookie, so once any page was cached under one theme, every visitor of
 * every theme got served that same frozen HTML — the theme switcher's redirect appeared to
 * do nothing, because the redirected-to GET was answered entirely from cache before
 * ResolveTheme's cookie read (and the new data-theme attribute) ever had a chance to render.
 * The key now includes the resolved theme, so each theme gets its own cache entry per route.
 *
 * BUG FIXED (Phase 2 production regression, found live after the E6-T5 deploy — Alpine.js
 * itself silently never initialized anywhere on the public site): app.blade.php mounts
 * `<livewire:terminal />` unconditionally on every public page (E5-T3), and Livewire bundles
 * Alpine.js inside its own auto-injected script tag — but that injection is driven by
 * Livewire's `RequestHandled` listener checking whether an actual Livewire component rendered
 * THIS request (via a `dehydrate()` lifecycle hook). A cache HIT here never invokes the
 * controller at all, so the terminal widget never mounts, `dehydrate()` never fires, and
 * Livewire silently skips injecting its script and style tags — taking Alpine.js down with
 * it site-wide, since Alpine ships inside that same bundle, not as its own app.js import.
 * `Livewire::forceAssetInjection()` is the documented escape hatch for exactly this ("assets
 * needed even though no component technically rendered this request" — the same mechanism
 * SupportNavigate uses for its own x-persist elements); calling it here makes the listener
 * inject Livewire's script/style into the returned response regardless of the cached HTML's
 * own (necessarily stale) content, on every hit.
 */
class CachePublicPage
{
    public const TTL_SECONDS = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $key = self::keyFor($request);
        $cached = Cache::get($key);

        if ($cached !== null) {
            Livewire::forceAssetInjection();

            return response($cached, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            Cache::put($key, $response->getContent(), self::TTL_SECONDS);
        }

        return $response;
    }

    /**
     * 'public-page:' + host + theme + the path (no leading slash — Request::path() already
     * strips it) and, when present, the query string.
     *
     * E3-T6 (§9 step 22): the host segment was added after production showed `www.` and the
     * apex sharing one entry per theme and serving each other's frozen HTML — including the
     * wrong host's absolute URLs and whichever host's snapshot of published rows was cached
     * first (reported as the Matrix theme showing no projects on one hostname). Keying by
     * host isolates the two. The theme segment still matches InvalidatePublicPageCache,
     * which forgets both themes because it cannot know which one a cached visitor was on.
     */
    public static function keyFor(Request $request): string
    {
        $query = $request->getQueryString();
        $theme = $request->cookie(ResolveTheme::COOKIE_NAME) === 'matrix' ? 'matrix' : 'technical';

        return 'public-page:' . $request->getHost() . ':' . $theme . ':' . $request->path() . ($query ? '?' . $query : '');
    }
}
