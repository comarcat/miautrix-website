<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * E5-T3 — security response headers (§9 step 27, acceptance 1). Tested against a real page
 * load (home), not a bare synthetic route — the epic's own Pitfalls section warns against a
 * CSP so strict it blocks the site's own self-hosted assets, so this also asserts the actual
 * asset tags on the page are same-origin (never an external CDN), which is what makes
 * `script-src 'self'` / `style-src 'self'` correct rather than merely present.
 */
class HeadersTest extends TestCase
{
    public function test_the_home_page_carries_the_core_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->assertHeader('X-XSS-Protection', '0');
        $response->assertHeader('X-DNS-Prefetch-Control', 'off');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        // The public site has no inline <script> of its own and no Alpine/x-data usage at
        // all (grepped to confirm) — 'unsafe-eval'/'unsafe-inline' are only needed by
        // Filament's own bundled admin UI (see SecurityHeaders::PANEL_CSP's own docblock),
        // so the public policy must NOT carry either. Regression test for a real Exploita
        // security-headers scan that graded this "misconfigured"/critical: the same relaxed
        // policy used to be sent site-wide, defeating CSP's purpose on the pages that need
        // it least.
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_the_admin_login_page_also_carries_the_security_headers(): void
    {
        // Filament's panel builds its own separate middleware stack (AdminPanelProvider) —
        // this proves the global append in bootstrap/app.php reaches it too, not just the
        // web-group routes.
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // Unlike the public policy above, the admin panel's own bundled Alpine/Livewire UI
        // genuinely needs both (see SecurityHeaders::PANEL_CSP's own docblock) — without
        // them the login button spins forever and the password-reveal toggle breaks.
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval' 'unsafe-inline'", $csp);
    }

    public function test_the_csp_does_not_block_the_pages_own_self_hosted_assets(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = $response->getContent();

        // Every <script src="..."> and <link rel="stylesheet" href="..."> on the page must
        // be same-origin (relative, or the app's own URL) — an external CDN reference here
        // would be silently blocked by script-src/style-src 'self', which is exactly the
        // failure mode the epic's Pitfalls section warns about.
        preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $scriptMatches);
        preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $html, $styleMatches);

        $appUrl = config('app.url');
        $externalUrls = array_filter(
            [...$scriptMatches[1], ...$styleMatches[1]],
            fn (string $url) => str_starts_with($url, 'http') && ! str_starts_with($url, $appUrl),
        );

        $this->assertSame([], array_values($externalUrls), 'Every script/stylesheet on the page must be same-origin under this CSP.');
    }

    public function test_hsts_is_only_sent_over_an_actual_https_connection(): void
    {
        $plain = $this->get('/');
        $plain->assertHeaderMissing('Strict-Transport-Security');

        // Passing a fully-qualified https:// URL is what makes Request::secure() true in a
        // Laravel test (it sets the HTTPS server var from the URL's own scheme).
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $secure = $this->get("https://{$appHost}/");
        $secure->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_is_also_sent_when_terminated_by_a_proxy_over_plain_http(): void
    {
        // Production sits behind Cloudflare, which terminates TLS and proxies to nginx over
        // plain HTTP — Request::secure() alone is always false there. This is the exact
        // security-auditor finding this test guards against regressing: without checking
        // X-Forwarded-Proto too, HSTS would silently never send in production at all.
        $response = $this->get('/', ['X-Forwarded-Proto' => 'https']);

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_urls_generated_behind_the_proxy_use_https_not_the_plain_http_origin_connection(): void
    {
        // Regression test for a real production bug: without trustProxies() configured in
        // bootstrap/app.php, Laravel never learned the original request was HTTPS and
        // generated route()/url() links (including Livewire's own AJAX endpoint URL) as
        // http://, which the browser then refused to reach at all under connect-src 'self'
        // (a scheme mismatch on an https:// page) — the admin login button hung forever as
        // a direct result. bootstrap/app.php's trustProxies(at: '*') is what fixes this.
        //
        // Asserted against /contact, not home: it's the one public page that actually mounts
        // a Livewire component (ContactForm) and so is the only one that renders
        // data-update-uri at all — home has no Livewire component on it, so this assertion
        // would vacuously pass there regardless of whether the proxy fix works.
        $response = $this->get('/contact', ['X-Forwarded-Proto' => 'https']);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://', false);
        $response->assertSee('data-update-uri="https://', false);
    }
}
