<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E2-T5 (Phase 2, p2-step-13) — an unauthenticated request may fetch a media file when it is
 * the OG image of a *published* article or project, so social crawlers stop rendering blank
 * share cards. Media of an unpublished entity stays a hard 404.
 */
class OgImageCrawlerAccessTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG — real magic bytes so mime detection returns image/png. */
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\x0d\x0a\x2d\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    private function ogMedia(string $fileName = 'og-card.png'): Media
    {
        Storage::fake('private-media');
        Storage::disk('private-media')->put("uploads/{$fileName}", self::PNG);

        return Media::create([
            'model_type' => Article::class,
            'model_id' => 0,
            'collection_name' => 'default',
            'name' => 'og',
            'file_name' => $fileName,
            'mime_type' => 'image/png',
            'disk' => 'private-media',
            'size' => strlen(self::PNG),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    public function test_a_guest_may_fetch_the_og_image_of_a_published_article(): void
    {
        $media = $this->ogMedia();
        Article::factory()->create(['og_image_id' => $media->id]);

        $response = $this->get(route('media.show', [$media, $media->file_name]));

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_a_guest_may_fetch_the_og_image_of_a_published_project(): void
    {
        $media = $this->ogMedia('project-og.png');
        Project::factory()->create(['og_image_id' => $media->id, 'published' => true]);

        $this->get(route('media.show', [$media, $media->file_name]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_og_image_of_an_unpublished_entity_is_a_404(): void
    {
        $media = $this->ogMedia('draft-og.png');
        Article::factory()->draft()->create(['og_image_id' => $media->id]);

        $this->get(route('media.show', [$media, $media->file_name]))->assertNotFound();
    }
}
