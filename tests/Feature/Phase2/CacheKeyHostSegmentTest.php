<?php

namespace Tests\Feature\Phase2;

use App\Actions\Cache\CachePublicPage;
use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E3-T6 (Phase 2, p2-step-22) — the public page-cache key carries a request-host segment, so
 * `www.miautrix.tech` and the apex cannot serve each other's cached HTML, and invalidation
 * forgets an entry across every known host.
 */
class CacheKeyHostSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_key_has_the_host_theme_path_shape(): void
    {
        $request = Request::create('https://miautrix.tech/projects', 'GET');

        $this->assertSame(
            'public-page:miautrix.tech:technical:projects',
            CachePublicPage::keyFor($request),
        );

        $request->cookies->set('miautrix_theme', 'matrix');
        $this->assertSame(
            'public-page:miautrix.tech:matrix:projects',
            CachePublicPage::keyFor($request),
        );
    }

    public function test_www_and_apex_never_collide(): void
    {
        $apex = CachePublicPage::keyFor(Request::create('https://miautrix.tech/blog', 'GET'));
        $www = CachePublicPage::keyFor(Request::create('https://www.miautrix.tech/blog', 'GET'));

        $this->assertNotSame($apex, $www);
        $this->assertSame('public-page:miautrix.tech:technical:blog', $apex);
        $this->assertSame('public-page:www.miautrix.tech:technical:blog', $www);
    }

    public function test_invalidation_forgets_the_entry_across_every_known_host(): void
    {
        config(['app.url' => 'https://miautrix.tech']);
        $project = Project::factory()->create(['published' => true]);

        $keys = [
            'public-page:miautrix.tech:technical:projects',
            'public-page:www.miautrix.tech:technical:projects',
            'public-page:miautrix.tech:matrix:projects',
            'public-page:www.miautrix.tech:matrix:projects',
        ];
        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        app(InvalidatePublicPageCache::class)->forProject($project);

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "{$key} not forgotten");
        }
    }
}
