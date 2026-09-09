<?php

namespace Tests\Feature\Public;

use App\Models\Document;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression test for a real production request: the /resume page needed to embed the PDF,
 * not just link to it — documents.download forces Content-Disposition: attachment (an
 * <iframe> pointed at it triggers a save dialog instead of rendering) and increments
 * download_count on every request, wrong for a page embed that renders on every visit.
 * documents.preview streams the same file inline and never touches the counter.
 */
class DocumentPreviewTest extends TestCase
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

    public function test_previewing_a_published_document_streams_it_inline_without_incrementing_the_download_counter(): void
    {
        Storage::fake('private-media');
        Storage::disk('private-media')->put('uploads/resume.pdf', 'fake-pdf-bytes');

        $document = Document::create([
            'title' => 'Résumé',
            'kind' => 'resume',
            'media_id' => $this->makeMedia()->id,
            'version' => 1,
            'download_count' => 0,
            'published' => true,
        ]);

        $response = $this->get(route('documents.preview', $document));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));

        $this->assertSame(0, $document->fresh()->download_count);
    }

    public function test_previewing_an_unpublished_document_404s(): void
    {
        $document = Document::create([
            'title' => 'Draft',
            'kind' => 'resume',
            'media_id' => $this->makeMedia('draft.pdf')->id,
            'version' => 1,
            'published' => false,
        ]);

        $this->get(route('documents.preview', $document))->assertNotFound();
    }
}
