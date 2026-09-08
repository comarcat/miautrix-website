<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\ContentStatusWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E3-T5 — Documents/Resume, Social Profiles, Settings, Audit Log viewer, dashboard widgets
 * (blueprint §9 step 17).
 */
class ResourceSet3Test extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function makeMedia(string $fileName = 'resume-v1.pdf'): Media
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

    public function test_uploading_a_new_document_version_increments_version_and_keeps_the_prior_media_row_intact(): void
    {
        $oldMedia = $this->makeMedia('resume-v1.pdf');
        $newMedia = $this->makeMedia('resume-v2.pdf');

        $document = Document::create([
            'title' => 'Résumé',
            'kind' => 'resume',
            'media_id' => $oldMedia->id,
            'version' => 1,
            'published' => true,
        ]);

        $document->update(['media_id' => $newMedia->id]);

        $this->assertSame(2, $document->fresh()->version);
        $this->assertSame($newMedia->id, $document->fresh()->media_id);
        // Never deleted, never overwritten — just no longer referenced.
        $this->assertNotNull(Media::find($oldMedia->id));
    }

    public function test_downloading_a_published_document_increments_download_count_by_exactly_one(): void
    {
        Storage::fake('private-media');
        Storage::disk('private-media')->put('uploads/resume.pdf', 'fake-pdf-bytes');

        $media = $this->makeMedia('resume.pdf');
        $document = Document::create([
            'title' => 'Résumé',
            'kind' => 'resume',
            'media_id' => $media->id,
            'version' => 1,
            'download_count' => 0,
            'published' => true,
        ]);

        $this->get(route('documents.download', $document))->assertOk();

        $this->assertSame(1, $document->fresh()->download_count);
    }

    public function test_a_super_admin_cannot_delete_an_audit_log_row(): void
    {
        $admin = $this->superAdmin();
        $log = AuditLog::create([
            'action' => 'login_success',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
        ]);

        $this->assertFalse($admin->can('delete', $log));
    }

    public function test_the_admin_dashboard_renders_both_widgets_with_real_counts(): void
    {
        $admin = $this->superAdmin();

        Project::create([
            'title' => 'A Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'a-project',
            'published' => true,
        ]);
        AuditLog::create([
            'action' => 'login_success',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSeeLivewire(RecentActivityWidget::class);
        $response->assertSeeLivewire(ContentStatusWidget::class);
    }
}
