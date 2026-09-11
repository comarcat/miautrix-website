<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use App\Models\ShareClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-T3 (Phase 2, p2-step-11) — `GET /s/{network}/{type}/{id}` records one append-only
 * `share_clicks` row and 302s to the network's own share endpoint. Unknown network or a
 * non-public article is a 404 with nothing written. The blog detail page routes its
 * `<x-share-links>` buttons through this path, not straight at the networks.
 */
class ShareRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_one_row_and_redirects_to_the_network(): void
    {
        $article = Article::factory()->create();

        $response = $this->get("/s/x/article/{$article->id}");

        $response->assertStatus(302);
        $this->assertStringContainsString('twitter.com', (string) $response->headers->get('Location'));

        $this->assertSame(1, ShareClick::query()->count());
        $this->assertDatabaseHas('share_clicks', [
            'network' => 'x',
            'type' => 'article',
            'subject_id' => $article->id,
        ]);
    }

    public function test_an_unknown_network_is_a_404_and_writes_no_row(): void
    {
        $article = Article::factory()->create();

        $this->get("/s/myspace/article/{$article->id}")->assertNotFound();

        $this->assertSame(0, ShareClick::query()->count());
    }

    public function test_a_non_public_article_is_a_404_and_writes_no_row(): void
    {
        $article = Article::factory()->draft()->create();

        $this->get("/s/facebook/article/{$article->id}")->assertNotFound();

        $this->assertSame(0, ShareClick::query()->count());
    }

    public function test_the_blog_detail_page_routes_share_links_through_the_redirect(): void
    {
        $article = Article::factory()->create();

        $this->get(route('blog.show', $article->slug))
            ->assertOk()
            ->assertSee("/s/x/article/{$article->id}", false)
            ->assertSee("/s/facebook/article/{$article->id}", false);
    }
}
