<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production bug found in review: the retained starter-kit
 * pages (dashboard, settings, confirm-password, login, etc.) use Flux Blade components
 * (<flux:sidebar>, <flux:main>, ...) that render Flux's own custom elements, which need
 * Flux's own compiled CSS to lay out at all. resources/css/app.css (the public design
 * system) never imports Flux's CSS on purpose (a naming collision on --color-accent), so
 * these pages need their own dedicated bundle — without it, the sidebar renders full-width
 * and overlaps the main content as the page scrolls, making it hard or impossible to
 * reliably reach controls like a settings page's Save button.
 */
class AuthenticatedLayoutAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_layout_loads_the_dedicated_flux_stylesheet_not_the_public_one(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk();

        $html = $response->getContent();
        preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $html, $styleMatches);
        $stylesheets = $styleMatches[1];

        // The dashboard must load authenticated.css's compiled stylesheet, and must NOT load
        // app.css's (the public design system's) — both bundles share resources/js/app.js as a
        // JS entry point, so a bare substring check against "app-" would also (wrongly) match
        // that legitimate <script> tag; checking the <link rel="stylesheet"> hrefs specifically
        // avoids that false positive.
        $this->assertNotEmpty($stylesheets, 'Expected at least one stylesheet link on the dashboard.');
        $this->assertTrue(
            collect($stylesheets)->contains(fn (string $href) => str_contains($href, '/build/assets/authenticated-')),
            'Expected the dashboard to load the authenticated.css bundle.',
        );
        $this->assertFalse(
            collect($stylesheets)->contains(fn (string $href) => str_contains($href, '/build/assets/app-')),
            'The dashboard must not load the public app.css bundle.',
        );
    }

    public function test_the_compiled_authenticated_stylesheet_actually_contains_fluxs_own_css(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'Run `npm run build` before this test.');

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertArrayHasKey('resources/css/authenticated.css', $manifest);

        $cssPath = public_path('build/' . $manifest['resources/css/authenticated.css']['file']);
        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        // --flux-timeline-* etc. are custom properties only Flux's own dist/flux.css
        // defines — present only if that file was actually imported into this bundle.
        $this->assertStringContainsString('--flux-timeline', $css);
    }
}
