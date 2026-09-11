<?php

namespace Tests\Feature\Phase2;

use App\Filament\Support\MediaUploadField;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E4-T4 (Phase 2, p2-step-29) — a published project's Files list renders a working download
 * link; /projects/{slug}/files/{media} 404s for an unpublished project or a media row that
 * isn't actually one of that project's own files; a valid request streams the file as an
 * attachment.
 */
class ProjectFilesPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaUploadField::DISK);
    }

    private function attachFile(Project $project, string $fileName = 'one-pager.pdf', ?string $label = 'One-pager'): Media
    {
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/' . $fileName, 'fake-pdf-bytes');

        $media = MediaUploadField::createMediaRecord(MediaUploadField::DIRECTORY . '/' . $fileName, Project::class, $project->id);
        $project->projectFiles()->attach($media->id, ['label' => $label, 'sort_order' => 0]);

        return $media;
    }

    public function test_a_published_projects_files_list_renders_a_working_download_link(): void
    {
        $project = Project::factory()->create(['slug' => 'files-project', 'published' => true]);
        $media = $this->attachFile($project);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('Files')
            ->assertSee('One-pager')
            ->assertSee(route('projects.file', [$project->slug, $media]), false);
    }

    public function test_the_download_route_404s_for_an_unpublished_project(): void
    {
        $project = Project::factory()->create(['slug' => 'unpublished-files', 'published' => false]);
        $media = $this->attachFile($project);

        $this->get(route('projects.file', [$project->slug, $media]))->assertNotFound();
    }

    public function test_the_download_route_404s_for_a_media_row_that_is_not_this_projects_file(): void
    {
        $project = Project::factory()->create(['slug' => 'project-a', 'published' => true]);
        $otherProject = Project::factory()->create(['slug' => 'project-b', 'published' => true]);
        $this->attachFile($project);
        $foreignMedia = $this->attachFile($otherProject, 'other.pdf', 'Other');

        $this->get(route('projects.file', [$project->slug, $foreignMedia]))->assertNotFound();
    }

    public function test_the_download_route_streams_the_file_as_an_attachment(): void
    {
        $project = Project::factory()->create(['slug' => 'download-project', 'published' => true]);
        $media = $this->attachFile($project);

        $response = $this->get(route('projects.file', [$project->slug, $media]));

        $response->assertOk();
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
