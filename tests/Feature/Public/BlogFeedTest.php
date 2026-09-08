<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-T6 — public blog index/detail + hand-rolled /feed.xml RSS (§9 step 24). One test per
 * acceptance criterion, per this task's own acceptance criterion 5.
 */
class BlogFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_returns_200_paginated_and_shows_only_published_articles(): void
    {
        Article::factory()->count(10)->create();
        Article::factory()->draft()->create(['title' => 'Draft Article']);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertDontSee('Draft Article');
        $response->assertViewHas('articles', fn ($articles) => $articles->count() === 9 && $articles->hasMorePages());
    }

    public function test_blog_show_returns_200_for_a_published_article_with_the_rendered_richeditor_body(): void
    {
        $article = Article::factory()->create([
            'title' => 'A Published Article',
            'slug' => 'a-published-article',
            'body' => '<p>Rendered <strong>rich</strong> content.</p>',
        ]);

        $response = $this->get(route('blog.show', $article->slug));

        $response->assertOk();
        $response->assertSee('A Published Article');
        $response->assertSee('Rendered <strong>rich</strong> content.', false);
    }

    public function test_blog_show_returns_404_for_an_unpublished_article(): void
    {
        $article = Article::factory()->draft()->create(['slug' => 'a-draft-article']);

        $this->get(route('blog.show', $article->slug))->assertNotFound();
    }

    public function test_feed_xml_returns_rss_content_type_and_valid_rss_with_one_item_per_published_article(): void
    {
        Article::factory()->count(3)->create();
        Article::factory()->draft()->create(['title' => 'Draft Article']);

        $response = $this->get(route('feed'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(3, $xml->channel->item);
        $this->assertStringNotContainsString('Draft Article', $response->getContent());
    }
}
