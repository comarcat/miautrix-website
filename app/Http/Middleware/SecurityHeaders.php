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
 * covers script/style/font/img/connect without needing any external host allowlisted. No
 * inline <script> exists anywhere in the app's own views (grepped to confirm) other than
 * `<script type="application/ld+json">`, which CSP's script-src does not gate at all (it is
 * a data block, never executed) — so script-src stays 'self' with no 'unsafe-inline'. A
 * `style="..."` attribute does still exist on one starter-kit page, hence
 * 'unsafe-inline' on style-src only (inline style injection is a materially lower-severity
 * risk than inline script, and there is no first-party CSP nonce plumbing to avoid it here).
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
        . "script-src 'self'; "
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
