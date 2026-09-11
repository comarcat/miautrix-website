<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E4-T8 (Phase 2, p2-step-33) — /life is entirely theme-gated: 200 + published channel='life'
 * articles only under a shows_life_blog theme (matrix by default), 404 otherwise; the nav
 * entry follows the same gate; a life article's publish/unpublish busts its cache entries.
 */
class LifeBlogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_life_index_lists_only_published_life_articles_under_a_permitted_theme(): void
    {
        $life = Article::factory()->create(['title' => 'A Life Post', 'channel' => 'life']);
        $professional = Article::factory()->create(['title' => 'A Pro Post', 'channel' => 'professional']);
        $draftLife = Article::factory()->draft()->create(['title' => 'Draft Life Post', 'channel' => 'life']);

        $response = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')->get(route('life.index'));

        $response->assertOk();
        $response->assertSee('A Life Post');
        $response->assertDontSee('A Pro Post');
        $response->assertDontSee('Draft Life Post');
    }

    public function test_life_routes_404_under_a_non_permitted_theme(): void
    {
        $life = Article::factory()->create(['channel' => 'life']);

        $this->get(route('life.index'))->assertNotFound();
        $this->get(route('life.show', $life->slug))->assertNotFound();

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'technical')
            ->get(route('life.index'))->assertNotFound();
    }

    public function test_the_nav_shows_the_life_entry_only_under_a_permitted_theme(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('>Life<', false);

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('>Life<', false);
    }

    public function test_publishing_or_unpublishing_a_life_article_busts_its_cache_entries(): void
    {
        $article = Article::factory()->create(['channel' => 'life']);

        $keys = ['public-page:127.0.0.1:matrix:life', 'public-page:127.0.0.1:matrix:life/' . $article->slug];
        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        $article->update(['published_at' => null]);

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "{$key} not forgotten");
        }
    }

    public function test_a_professional_article_is_never_listed_on_life(): void
    {
        Article::factory()->create(['title' => 'Strictly Professional', 'channel' => 'professional']);

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get(route('life.index'))
            ->assertOk()
            ->assertDontSee('Strictly Professional');
    }
}
