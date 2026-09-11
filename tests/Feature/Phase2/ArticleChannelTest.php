<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E4-T7 (Phase 2, p2-step-32) — articles.channel defaults to 'professional', the
 * professional()/life() scopes filter by it, /feed.xml excludes 'life', and /blog is
 * unaffected by the new column.
 */
class ArticleChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_channel_column_exists_indexed_and_defaults_to_professional(): void
    {
        $this->assertTrue(Schema::hasColumn('articles', 'channel'));

        $article = Article::factory()->create();
        $this->assertSame('professional', $article->fresh()->channel);
    }

    public function test_the_professional_and_life_scopes_filter_by_channel(): void
    {
        Article::factory()->create(['title' => 'Pro Post', 'channel' => 'professional']);
        Article::factory()->create(['title' => 'Life Post', 'channel' => 'life']);

        $this->assertSame(['Pro Post'], Article::professional()->pluck('title')->all());
        $this->assertSame(['Life Post'], Article::life()->pluck('title')->all());
        $this->assertSame(['Life Post'], Article::channel('life')->pluck('title')->all());
    }

    public function test_feed_xml_excludes_life_articles(): void
    {
        Article::factory()->create(['title' => 'Pro Feed Post', 'channel' => 'professional']);
        Article::factory()->create(['title' => 'Life Feed Post', 'channel' => 'life']);

        $response = $this->get(route('feed'));

        $response->assertOk();
        $response->assertSee('Pro Feed Post');
        $response->assertDontSee('Life Feed Post');
    }

    public function test_blog_index_is_unaffected_by_the_new_column(): void
    {
        Article::factory()->create(['title' => 'Pro Blog Post', 'channel' => 'professional']);
        Article::factory()->create(['title' => 'Life Blog Post', 'channel' => 'life']);

        // E4-T7 is deliberately a no-op for /blog — the dedicated /life route lands in
        // E4-T8. Both channels still show here until then.
        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertSee('Pro Blog Post');
        $response->assertSee('Life Blog Post');
    }

    public function test_the_admin_form_offers_a_channel_select_and_the_table_a_channel_filter(): void
    {
        $source = (string) file_get_contents(app_path('Filament/Resources/Articles/Schemas/ArticleForm.php'));
        $this->assertStringContainsString("Select::make('channel')", $source);

        $tableSource = (string) file_get_contents(app_path('Filament/Resources/Articles/Tables/ArticlesTable.php'));
        $this->assertStringContainsString("SelectFilter::make('channel')", $tableSource);
    }
}
