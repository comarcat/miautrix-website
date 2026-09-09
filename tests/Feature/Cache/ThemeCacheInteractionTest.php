<?php

namespace Tests\Feature\Cache;

use App\Http\Middleware\ResolveTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production bug found in review: the database page cache
 * (E5-T2) did not vary its key by the `miautrix_theme` cookie, so once a `cache.public`
 * route was cached under one theme, every visitor of every theme was served that exact same
 * frozen HTML — the theme switcher's redirect appeared to do nothing, since the redirected-to
 * GET was answered entirely from cache before ResolveTheme's cookie read ever mattered.
 *
 * Exercised against a real cached route (home, not a throwaway test route) — home is
 * attached to the 'cache.public' middleware in routes/web.php, which is exactly the
 * combination (a real cached page + a real theme cookie) the bug required to reproduce.
 */
class ThemeCacheInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_themes_on_a_cached_route_actually_changes_the_rendered_response(): void
    {
        $technical = $this->get(route('home'));
        $technical->assertOk();
        $technical->assertSee('data-theme="technical"', false);

        // Simulates the theme switcher having already set the cookie (POST /theme) — the bug
        // was that this next GET, on a route already cached under the technical theme, still
        // came back as data-theme="technical" instead of "matrix".
        $matrix = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')->get(route('home'));
        $matrix->assertOk();
        $matrix->assertSee('data-theme="matrix"', false);

        // And switching back must not be poisoned by the matrix entry either. Explicitly
        // re-asserting the technical cookie (not just omitting it) — withCookie() persists
        // across requests within a test, so this is what actually proves the earlier matrix
        // request didn't clobber the technical cache entry, not just an artifact of Laravel's
        // test cookie jar still carrying 'matrix' from the previous call.
        $technicalAgain = $this->withCookie(ResolveTheme::COOKIE_NAME, 'technical')->get(route('home'));
        $technicalAgain->assertSee('data-theme="technical"', false);
    }
}
