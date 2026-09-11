<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase2\Concerns\TogglesSiteFlags;
use Tests\TestCase;

/**
 * E4-T9 (Phase 2, p2-step-34) — the click-to-play YouTube facade renders no <iframe> up
 * front; the /life-scoped CSP addition appears only under the flag and only on /life*; every
 * other route's CSP (and /life* with the flag off) stays byte-identical to pre-Phase-2.
 */
class YoutubeCspScopeTest extends TestCase
{
    use RefreshDatabase;
    use TogglesSiteFlags;

    private const PRE_PHASE2_PUBLIC_CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-eval'; "
        . "style-src 'self' 'nonce-%s'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self';";

    public function test_a_life_post_with_a_shortcode_renders_the_facade_with_no_iframe(): void
    {
        $article = Article::factory()->create([
            'channel' => 'life',
            'body' => '<p>Check this out: [youtube:dQw4w9WgXcQ]</p>',
        ]);

        $html = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get(route('life.show', $article->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $html);
        $this->assertStringContainsString('aria-label="Play video"', $html);
        $this->assertDoesNotMatchRegularExpression('/<iframe\b/i', $html);
    }

    public function test_life_scoped_csp_adds_youtube_sources_when_the_flag_is_on(): void
    {
        $article = Article::factory()->create(['channel' => 'life']);

        $response = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get(route('life.show', $article->slug))
            ->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $csp);
        $this->assertStringContainsString('https://i.ytimg.com', $csp);
    }

    public function test_the_blog_and_home_csp_stay_byte_identical_with_no_youtube_sources(): void
    {
        $expectedShape = '/^' . str_replace(
            'nonce\\-%s',
            "nonce-[^']+",
            preg_quote(self::PRE_PHASE2_PUBLIC_CSP, '/'),
        ) . '$/';

        foreach ([route('home'), route('blog.index')] as $url) {
            $response = $this->get($url)->assertOk();
            $csp = (string) $response->headers->get('Content-Security-Policy');

            $this->assertStringNotContainsString('youtube', $csp);
            $this->assertStringNotContainsString('ytimg', $csp);
            $this->assertMatchesRegularExpression($expectedShape, $csp);
        }
    }

    public function test_the_flag_defaults_to_true(): void
    {
        $this->assertTrue(config('site.csp.youtube_on_life'));
    }

    public function test_life_scoped_csp_carries_no_youtube_sources_when_the_flag_is_off(): void
    {
        $this->withSiteFlag('site.csp.youtube_on_life', false);
        $article = Article::factory()->create(['channel' => 'life']);

        $response = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get(route('life.show', $article->slug))
            ->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('youtube', $csp);
        $this->assertStringNotContainsString('i.ytimg.com', $csp);
    }
}
