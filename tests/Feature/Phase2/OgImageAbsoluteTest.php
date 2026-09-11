<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use App\Models\Media;
use App\Models\Project;
use App\Support\Seo\OgImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-T4 (Phase 2, p2-step-12) — `OgImage::resolve()` always returns an absolute `https://`
 * URL, for a real media id and for the `og-default.png` fallback alike, and the blog and
 * project detail pages emit that URL as their `<meta property="og:image">`.
 */
class OgImageAbsoluteTest extends TestCase
{
    use RefreshDatabase;

    private function media(): Media
    {
        return Media::create([
            'model_type' => Article::class,
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'og',
            'file_name' => 'og-card.png',
            'mime_type' => 'image/png',
            'disk' => 'private-media',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    public function test_the_null_fallback_is_an_absolute_https_url(): void
    {
        $url = OgImage::resolve(null);

        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('og-default.png', $url);
    }

    public function test_a_media_id_resolves_to_an_absolute_https_url(): void
    {
        $url = OgImage::resolve($this->media()->id);

        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('og-card.png', $url);
        $this->assertStringNotContainsString('http://', substr($url, strlen('https://')));
    }

    public function test_a_blog_post_emits_an_absolute_og_image_meta_tag(): void
    {
        $article = Article::factory()->create(['og_image_id' => $this->media()->id]);

        $html = $this->get(route('blog.show', $article->slug))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https://[^"]+og-card\.png">#',
            (string) $html,
        );
    }

    public function test_a_project_emits_an_absolute_og_image_meta_tag(): void
    {
        $project = Project::factory()->create();

        $html = $this->get(route('projects.show', $project->slug))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https://[^"]+(og-default\.png|/media/)[^"]*">#',
            (string) $html,
        );
    }
}
