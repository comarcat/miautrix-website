<?php

namespace App\Actions\Cache;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            return response($cached, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            Cache::put($key, $response->getContent(), self::TTL_SECONDS);
        }

        return $response;
    }

    /**
     * 'public-page:' + the path (no leading slash — Request::path() already strips it) and,
     * when present, the query string — this is the "route + query string" key the acceptance
     * criteria and InvalidatePublicPageCache both need to agree on.
     */
    public static function keyFor(Request $request): string
    {
        $query = $request->getQueryString();

        return 'public-page:' . $request->path() . ($query ? '?' . $query : '');
    }
}
