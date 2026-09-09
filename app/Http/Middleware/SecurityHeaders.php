<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * E5-T3 (§9 step 27) — appended globally in bootstrap/app.php (not `web(append:)`), so it
 * covers every response regardless of middleware group: the public site, the dashboard, and
 * Filament's admin panel, which builds its own separate middleware stack in
 * AdminPanelProvider and would otherwise never see it.
 *
 * CSP is scoped to what this app actually serves — every asset is self-hosted (Vite-built
 * CSS/JS, self-hosted fonts per §7, no third-party embeds per Non-Goal #12) — so `'self'`
 * covers script/style/font/img/connect without needing any external host allowlisted.
 *
 * 'unsafe-eval' and 'unsafe-inline' on script-src are both real, deliberate trade-offs, found
 * missing one at a time in production review — this app's own views have no inline <script>
 * (grepped to confirm) other than `<script type="application/ld+json">`, which script-src
 * does not gate at all (a data block, never executed), so neither is needed for anything this
 * app wrote. Both are needed for Filament's own bundled admin UI:
 * - 'unsafe-eval': Alpine.js (bundled in Livewire, used throughout Filament for every
 *   `x-data`/`x-bind`/`x-on` expression, including ones Filament generates at runtime like
 *   `filamentSchema(...)`) evaluates directive expressions via the `Function` constructor by
 *   default. Without it, every one of those throws an EvalError, silently swallowed: the
 *   admin login button spins forever (Livewire's processing state can't be read) and the
 *   password-reveal toggle can't bind `type="password"` at all (shows the raw value).
 * - 'unsafe-inline': Filament ships its own inline bootstrap `<script>` tags per-page (FOUC
 *   prevention for dark-mode/sidebar-collapsed state, evaluated before Alpine loads) — a
 *   different one on the login page than on an authenticated panel page, so allowlisting by
 *   hash isn't practical (it would need updating for every Filament page and every Filament
 *   version). Without it, that bootstrap script never runs and the panel's own layout breaks
 *   (found via the authenticated settings page rendering with its sidebar collapsed onto/
 *   overlapping the main content).
 * Alpine ships a CSP-safe build that avoids the eval need, but Filament's own internals were
 * not written against that restricted evaluator (they call named JS functions dynamically,
 * which the CSP-safe build does not support) — adopting it would mean reworking Filament's
 * own bundled assets, not just this app's code. A `style="..."` attribute does still exist on
 * one starter-kit page, hence 'unsafe-inline' on style-src too (already present, unrelated to
 * the two script-src additions above).
 * HSTS is only sent over an actual HTTPS connection — sending it over plain HTTP achieves
 * nothing (browsers ignore it per spec) and would just be noise in local dev. Production sits
 * behind Cloudflare terminating TLS and proxying to nginx over plain HTTP, so
 * `Request::secure()` alone is always false there — checked via X-Forwarded-Proto too
 * (security-auditor finding: without this, HSTS silently never sends in production at all,
 * a fail-open on exactly the header meant to prevent a downgrade).
 */
class SecurityHeaders
{
    private const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-eval' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self';";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::CSP);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure() || $request->header('X-Forwarded-Proto') === 'https') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
