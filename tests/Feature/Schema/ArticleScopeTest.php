<?php

namespace Tests\Feature\Schema;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Substantiates the note left on E3-T6 in tasks.json: the articles migration and
 * Article::published() scope were pulled forward into E2-T6 (ArticlePolicy/E2-T7 both needed
 * the table much earlier than E3-T6's original position). This covers what would otherwise
 * have been E3-T6's first two acceptance criteria — the Filament resource itself is still
 * that task's real scope.
 */
class ArticleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_creates_the_articles_table_per_the_blueprint_schema(): void
    {
        $this->assertTrue(Schema::hasTable('articles'));
        $this->assertTrue(Schema::hasColumns('articles', [
            'id', 'title', 'excerpt', 'body', 'published_at', 'featured',
            'slug', 'published', 'sort_order', 'seo_title', 'meta_description',
            'canonical_url', 'og_title', 'og_description', 'og_image_id', 'deleted_at',
        ]));
    }

    public function test_an_article_with_null_published_at_does_not_appear_in_the_published_scope(): void
    {
        Article::create([
            'title' => 'Draft',
            'excerpt' => 'Excerpt.',
            'body' => 'Body.',
            'slug' => 'draft',
            'published_at' => null,
        ]);

        $this->assertSame(0, Article::published()->count());
    }

    public function test_an_article_with_a_past_published_at_appears_in_the_published_scope(): void
    {
        $article = Article::create([
            'title' => 'Live Article',
            'excerpt' => 'Excerpt.',
            'body' => 'Body.',
            'slug' => 'live-article',
            'published_at' => now()->subDay(),
        ]);

        $this->assertTrue(Article::published()->whereKey($article->id)->exists());
    }
}
