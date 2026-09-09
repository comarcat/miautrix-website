<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E5-T1 — SEO surface: meta/OG/canonical, JSON-LD, sitemap (§9 step 25).
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_200_with_one_url_entry_per_published_entity_including_articles(): void
    {
        Project::factory()->create(['title' => 'Published Project', 'slug' => 'published-project']);
        Project::factory()->create(['published' => false, 'title' => 'Hidden Project', 'slug' => 'hidden-project']);
        Article::factory()->create(['title' => 'Published Article', 'slug' => 'published-article']);
        Article::factory()->draft()->create(['title' => 'Draft Article', 'slug' => 'draft-article']);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        $urls = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));

        // 7 static pages + 1 published project + 1 published article — the unpublished
        // project and the draft article must not appear at all.
        $this->assertCount(9, $urls);
        $this->assertContains(route('projects.show', 'published-project'), $urls);
        $this->assertContains(route('blog.show', 'published-article'), $urls);
    }

    public function test_project_show_page_emits_canonical_and_falls_back_to_the_site_default_og_image(): void
    {
        $project = Project::factory()->create([
            'title' => 'SEO Project',
            'slug' => 'seo-project',
            'og_image_id' => null,
        ]);

        $response = $this->get(route('projects.show', $project->slug));

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="' . route('projects.show', $project->slug) . '">', false);
        $response->assertSee('<meta property="og:image" content="' . asset('images/og-default.png') . '">', false);
        $response->assertSee('"@type":"CreativeWork"', false);
    }

    public function test_article_show_page_emits_blogposting_json_ld(): void
    {
        $article = Article::factory()->create(['title' => 'SEO Article', 'slug' => 'seo-article']);

        $response = $this->get(route('blog.show', $article->slug));

        $response->assertOk();
        $response->assertSee('"@type":"BlogPosting"', false);
        $response->assertSee('"headline":"SEO Article"', false);
    }

    public function test_about_page_emits_person_json_ld(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Bio.',
        ]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('"@type":"Person"', false);
        $response->assertSee('"name":"Ada Lovelace"', false);
    }
}
