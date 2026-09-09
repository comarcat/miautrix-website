<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
 * BUG FIXED (found in a real Exploita security-headers scan, graded C, CSP flagged
 * "misconfigured"/critical): 'unsafe-eval' and 'unsafe-inline' on script-src used to be sent
 * on EVERY response, public site included — the intent was to scope both to Filament's admin
 * UI only (see PANEL_CSP's docblock below) and drop them from PUBLIC_CSP entirely. Split in
 * two: PUBLIC_CSP (everything but /admin) and PANEL_CSP (/admin only — Filament's own panel
 * path, see AdminPanelProvider::path()).
 *
 * BUG FOUND AND FIXED (real production regression from the change above, caught testing the
 * live contact form after a later fix): dropping 'unsafe-eval' from PUBLIC_CSP's script-src
 * broke wire:submit on /contact outright — console showed "Livewire Expression Error:
 * Evaluating a string as JavaScript violates ... 'unsafe-eval' is not an allowed source ...
 * Expression: 'submit'". The original reasoning ("no x-data/Alpine usage" on the public site,
 * confirmed by grepping for literal `x-data`/`x-on`/`x-bind` attributes) was true but beside
 * the point: Livewire itself is built on Alpine internally, and evaluates every `wire:*`
 * directive's expression (`wire:submit="submit"`, `wire:model="name"`, etc.) through Alpine's
 * `Function`-based evaluator regardless of whether the page ever writes a literal `x-`
 * attribute — the exact same requirement PANEL_CSP already carries for Filament, for the
 * identical underlying reason. 'unsafe-eval' is back on PUBLIC_CSP's script-src; 'unsafe-
 * inline' is not needed there (the error was specifically about eval, and the public site
 * genuinely has no inline <script> that would need it).
 *
 * BUG FIXED (found in a follow-up secscanner.app scan, still "critical" on CSP): PUBLIC_CSP's
 * style-src carried 'unsafe-inline', needed for Livewire's own auto-injected
 * `<!-- Livewire Styles --><style>...</style>` block (the `[wire\:loading]` display:none
 * rules) — every public page that mounts a Livewire component (e.g. /contact's ContactForm)
 * emits this. `Vite::useCspNonce()` generates one nonce per request; Livewire's own
 * FrontendAssets already reads `Vite::cspNonce()` and stamps that same value onto its
 * `<style>` tag automatically (no Livewire config needed) — swapping 'unsafe-inline' for
 * 'nonce-{that value}' in style-src closes this without breaking that block. Livewire's
 * *script* tag is a same-origin `<script src="...">` (an external file, not inline), so it
 * needed no such nonce — only the 'unsafe-eval' fix above.
 *
 * HSTS is only sent over an actual HTTPS connection — sending it over plain HTTP achieves
 * nothing (browsers ignore it per spec) and would just be noise in local dev. Production sits
 * behind Cloudflare terminating TLS and proxying to nginx over plain HTTP, so
 * `Request::secure()` alone is always false there — checked via X-Forwarded-Proto too
 * (security-auditor finding: without this, HSTS silently never sends in production at all,
 * a fail-open on exactly the header meant to prevent a downgrade).
 *
 * The rest of the headers below were the scan's other findings:
 * - X-Frame-Options / frame-ancestors already covered clickjacking via CSP alone in modern
 *   browsers, but the legacy header was still flagged "missing" (high) — cheap to add for
 *   browsers that don't honor frame-ancestors.
 * - Permissions-Policy / Cross-Origin-Opener-Policy / Cross-Origin-Resource-Policy /
 *   Cross-Origin-Embedder-Policy: all safe to set unconditionally, since (per Non-Goal #12
 *   above) nothing on this site is embedded cross-origin, embeds anything cross-origin, or
 *   needs a third-party browser feature (camera/mic/geolocation/etc — the contact form is a
 *   plain text form, nothing here uses any of them).
 * - X-DNS-Prefetch-Control: off — nothing on the site benefits from prefetching visited-link
 *   domains, so there's no reason to let it leak browsing patterns to the resolver.
 * - X-Content-Type-Options and Referrer-Policy were flagged "misconfigured" with a literal
 *   duplicated value ("nosniff, nosniff") — root cause was nginx's vhost (infra/provision.sh)
 *   *also* adding both via `add_header`, on top of this middleware setting them for every
 *   response already (nginx's `add_header` appends rather than replaces, since the upstream
 *   PHP-FPM response already carries its own copy) — removed from nginx, this middleware is
 *   now the single source for both, on every response including Filament's panel.
 *
 * BUG FIXED (secscanner.app follow-up scan): X-XSS-Protection used to be sent as `0`, on the
 * theory that explicitly disabling the legacy filter was better than omitting the header —
 * that theory doesn't hold up against a scanner that flags the header's mere *presence* as
 * "deprecated" regardless of value, and no browser shipping today honors any value of this
 * header at all (Chrome/Edge dropped their XSS Auditor in 2019, Firefox never had one) — so
 * sending `0` accomplishes nothing a browser will ever read. Removed entirely rather than
 * argued over; CSP (already hardened above) is the real, effective replacement.
 */
class SecurityHeaders
{
    private const PUBLIC_CSP_TEMPLATE = "default-src 'self'; "
        . "script-src 'self' 'unsafe-eval'; "
        . "style-src 'self' 'nonce-%s'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self';";

    /**
     * Relaxed only where Filament's own bundled admin UI actually needs it:
     * - 'unsafe-eval': Alpine.js (bundled in Livewire, used throughout Filament for every
     *   `x-data`/`x-bind`/`x-on` expression, including ones Filament generates at runtime like
     *   `filamentSchema(...)`) evaluates directive expressions via the `Function` constructor
     *   by default. Without it, every one of those throws an EvalError, silently swallowed:
     *   the admin login button spins forever (Livewire's processing state can't be read) and
     *   the password-reveal toggle can't bind `type="password"` at all (shows the raw value).
     * - 'unsafe-inline': Filament ships its own inline bootstrap `<script>` tags per-page
     *   (FOUC prevention for dark-mode/sidebar-collapsed state, evaluated before Alpine loads)
     *   — a different one on the login page than on an authenticated panel page, so
     *   allowlisting by hash isn't practical (it would need updating for every Filament page
     *   and every Filament version). Without it, that bootstrap script never runs and the
     *   panel's own layout breaks (found via the authenticated settings page rendering with
     *   its sidebar collapsed onto/overlapping the main content).
     * Alpine ships a CSP-safe build that avoids the eval need, but Filament's own internals
     * were not written against that restricted evaluator (they call named JS functions
     * dynamically, which the CSP-safe build does not support) — adopting it would mean
     * reworking Filament's own bundled assets, not just this app's code.
     */
    private const PANEL_CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-eval' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self';";

    private const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), '
        . 'usb=(), browsing-topics=(), attribution-reporting=()';

    public function handle(Request $request, Closure $next): Response
    {
        // Generated before $next() runs so it's available to Vite's own @vite() output and
        // Livewire's FrontendAssets (which already reads Vite::cspNonce() on its own — see
        // this class's own docblock) while the view renders; read back below to put the same
        // value into the header.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $isAdmin = $request->is('admin*');

        $response->headers->set(
            'Content-Security-Policy',
            $isAdmin ? self::PANEL_CSP : sprintf(self::PUBLIC_CSP_TEMPLATE, $nonce),
        );

        // Found in review: robots.txt used to Disallow: /admin, which a secscanner.app scan
        // flagged as revealing the panel's location for no real benefit (Disallow only asks
        // crawlers not to visit — it doesn't stop indexing, and publishes the path to anyone
        // who reads the file, crawler or not). Removed that entry (public/robots.txt) AND
        // added this — strictly better than either: it actively tells any crawler that does
        // reach /admin (by any other means) not to index what it finds, without publishing
        // the path anywhere at all.
        if ($isAdmin) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->remove('X-XSS-Protection');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');

        if ($request->secure() || $request->header('X-Forwarded-Proto') === 'https') {
            // 'preload' opts this domain's HSTS into browsers' own hardcoded preload list —
            // eliminating the plain-HTTP window on a visitor's very first-ever visit, before
            // any HSTS header could have reached them. Only the header token is added here;
            // actually taking effect requires a separate, semi-irreversible step (submitting
            // https://hstspreload.org — removal from a shipped browser takes months), which
            // is the site owner's call to make deliberately, not something to do silently as
            // a side effect of a header fix.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
