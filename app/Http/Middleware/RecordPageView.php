<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\Geo\GeoLocator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 (E5-T8, §9 step 42) — self-hosted page-view analytics (backlog item 14). Appended
 * to the GLOBAL middleware stack (bootstrap/app.php, next to SecurityHeaders) so it sees the
 * admin panel too — which is exactly what it needs to filter OUT.
 *
 * Entirely flag-gated (site.analytics.record_page_views, default OFF until E5-T9): the very
 * first line returns before anything else runs, so a plain checkout writes nothing and costs
 * nothing extra per request. Even with the flag on, every early-return below (non-GET,
 * /admin*, an asset path, a known bot UA) happens before any database write — a request that
 * doesn't matter for analytics never reaches the insert.
 */
class RecordPageView
{
    /**
     * @var list<string>
     */
    private const BOT_MARKERS = [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
        'whatsapp', 'telegrambot', 'discordbot', 'curl', 'wget', 'python-requests',
        'headlesschrome', 'ahrefsbot', 'semrushbot', 'mj12bot',
    ];

    /**
     * @var list<string>
     */
    private const ASSET_PREFIXES = ['build/', 'fonts/', 'images/', 'storage/'];

    /**
     * @var list<string>
     */
    private const ASSET_EXTENSIONS = [
        'css', 'js', 'map', 'ico', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'woff', 'woff2',
        'ttf', 'txt', 'xml', 'json',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('site.analytics.record_page_views')) {
            return $response;
        }

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if ($request->is('admin*')) {
            return $response;
        }

        $path = $request->path();

        if ($this->isAssetPath($path)) {
            return $response;
        }

        if ($this->isBot((string) $request->userAgent())) {
            return $response;
        }

        $this->record($request, $path);

        return $response;
    }

    private function isAssetPath(string $path): bool
    {
        foreach (self::ASSET_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::ASSET_EXTENSIONS, true);
    }

    private function isBot(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        if ($userAgent === '') {
            return true;
        }

        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($userAgent, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function device(string $userAgent): string
    {
        if ($this->isBot($userAgent)) {
            return 'bot';
        }

        return preg_match('/Mobi|Android|iPhone|iPad/i', $userAgent) === 1 ? 'mobile' : 'desktop';
    }

    private function channel(string $path): string
    {
        if ($path === 'life' || str_starts_with($path, 'life/')) {
            return 'life';
        }

        if ($path === '/' || $path === 'blog' || str_starts_with($path, 'blog/')) {
            return 'professional';
        }

        return 'other';
    }

    private function record(Request $request, string $path): void
    {
        $referrerHost = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST) ?: null;
        $ip = (string) $request->ip();

        PageView::create([
            'path' => $path,
            'referrer_host' => $referrerHost,
            'country' => app(GeoLocator::class)->country($ip),
            'device' => $this->device((string) $request->userAgent()),
            'channel' => $this->channel($path),
        ]);
    }
}
