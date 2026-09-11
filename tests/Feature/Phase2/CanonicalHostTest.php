<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E3-T7 (Phase 2, p2-step-23) — `www.` and the apex resolve to the same theme from one
 * cookie, every page's `<link rel="canonical">` points at `config('site.canonical_host')`,
 * and the theme cookie is scoped to a `.`-prefixed registrable domain.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_and_apex_resolve_to_the_same_theme_from_one_cookie(): void
    {
        $apex = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('https://miautrix.tech/');
        $www = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('https://www.miautrix.tech/');

        $apex->assertOk()->assertSee('data-theme="matrix"', false);
        $www->assertOk()->assertSee('data-theme="matrix"', false);
    }

    public function test_the_canonical_link_host_is_always_the_configured_canonical_host(): void
    {
        $host = config('site.canonical_host');

        $this->get('https://www.miautrix.tech/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://' . $host . '/">', false);

        $this->get('https://miautrix.tech/blog')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://' . $host . '/blog">', false);
    }

    public function test_the_canonical_link_preserves_path_and_query(): void
    {
        $host = config('site.canonical_host');

        $this->get('https://www.miautrix.tech/projects?category=web')
            ->assertSee('<link rel="canonical" href="https://' . $host . '/projects?category=web">', false);
    }

    public function test_the_theme_cookie_is_scoped_to_the_registrable_domain(): void
    {
        $registrable = preg_replace('/^www\./i', '', (string) config('site.canonical_host'));

        $response = $this->from('https://miautrix.tech/')
            ->post(route('theme.set'), ['theme' => 'matrix']);

        $response->assertRedirect();
        $response->assertCookie(ResolveTheme::COOKIE_NAME, 'matrix');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ResolveTheme::COOKIE_NAME);

        $this->assertNotNull($cookie);
        $this->assertSame('.' . $registrable, $cookie->getDomain());
    }
}
