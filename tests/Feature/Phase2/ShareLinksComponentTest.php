<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * E2-T2 (Phase 2, p2-step-10) — `<x-share-links>` emits exactly six plain share-intent links
 * (Facebook, X, LinkedIn, WhatsApp, Reddit, email) with every parameter URL-encoded, loads
 * no third-party SDK, and nonces its one progressive-enhancement script.
 *
 * The component itself bakes in SecurityHeaders::NONCE_PLACEHOLDER, not a live Vite nonce —
 * this component renders on pages CachePublicPage caches for up to an hour, so a live nonce
 * baked in here would go stale the moment it's served from a cache hit. SecurityHeaders
 * substitutes the real, current-request nonce for the placeholder on every response on its
 * way out (see that middleware's own docblock); HeadersTest exercises that substitution
 * end-to-end over real HTTP. This test renders the component directly via Blade::render(),
 * bypassing the HTTP middleware stack entirely, so it only asserts the placeholder is present.
 */
class ShareLinksComponentTest extends TestCase
{
    private function render(): string
    {
        return Blade::render(
            '<x-share-links :url="$url" :title="$title" :summary="$summary" />',
            [
                'url' => 'https://miautrix.tech/blog/a-post',
                'title' => 'A post about things & stuff',
                'summary' => 'Short summary here.',
            ],
        );
    }

    public function test_it_emits_exactly_six_encoded_share_links(): void
    {
        $html = $this->render();

        foreach (['facebook', 'x', 'linkedin', 'whatsapp', 'reddit', 'email'] as $network) {
            $this->assertStringContainsString("data-share-network=\"{$network}\"", $html);
        }
        $this->assertSame(6, substr_count($html, 'data-share-network='));

        // rawurlencode() output — ":" -> %3A, "/" -> %2F, " " -> %20, "&" -> %26
        $this->assertStringContainsString('https%3A%2F%2Fmiautrix.tech%2Fblog%2Fa-post', $html);
        $this->assertStringContainsString('A%20post%20about%20things%20%26%20stuff', $html);
        $this->assertStringNotContainsString('url=https://miautrix.tech', $html);
    }

    public function test_it_loads_no_third_party_sdk_script(): void
    {
        $html = $this->render();

        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src=/i', $html);
        $this->assertStringNotContainsString('connect.facebook.net', $html);
        $this->assertStringNotContainsString('platform.twitter.com', $html);
    }

    public function test_the_progressive_enhancement_script_carries_the_csp_nonce_placeholder(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<script nonce="' . SecurityHeaders::NONCE_PLACEHOLDER . '"', $html);
    }
}
