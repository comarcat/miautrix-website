<?php

namespace Tests\Feature\Public;

use App\Models\Document;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production report: blueprint §4/§9 step 23 scoped a public
 * /resume page (route table: "documents where kind=resume | public"), and DocumentResource,
 * the seeded placeholder Document row, and documents.download all shipped — but no
 * route/view ever read them, so an admin who uploaded a real resume had no way to see where
 * a visitor would find or download it.
 */
class ResumeTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(string $fileName = 'resume.pdf'): Media
    {
        return Media::create([
            'model_type' => Document::class,
            'model_id' => 0,
            'collection_name' => 'default',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'disk' => 'private-media',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    public function test_the_resume_page_shows_a_download_link_for_the_published_resume(): void
    {
        $document = Document::create([
            'title' => 'Résumé',
            'kind' => 'resume',
            'media_id' => $this->makeMedia()->id,
            'version' => 3,
            'published' => true,
        ]);

        $response = $this->get(route('resume'));

        $response->assertOk();
        $response->assertSee('Résumé');
        $response->assertSee('version 3');
        $response->assertSee(route('documents.download', $document), false);
    }

    public function test_the_resume_page_shows_an_empty_state_when_nothing_is_published(): void
    {
        // An unpublished resume must not appear — same as every other "published" gate
        // on this site.
        Document::create([
            'title' => 'Draft Résumé',
            'kind' => 'resume',
            'media_id' => $this->makeMedia('draft-resume.pdf')->id,
            'version' => 1,
            'published' => false,
        ]);

        $response = $this->get(route('resume'));

        $response->assertOk();
        $response->assertSee('No résumé published yet.');
        $response->assertDontSee('Draft Résumé');
    }

    public function test_the_resume_page_is_linked_from_the_main_navigation(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('resume'), false);
    }
}
