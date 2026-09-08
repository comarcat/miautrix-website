<?php

namespace Tests\Feature\Schema;

use App\Contracts\AnalyticsProviderInterface;
use App\Exceptions\MediaInUseException;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Project;
use App\Support\Analytics\NullAnalyticsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * E2-T4 — media (spatie/laravel-medialibrary), documents, social_profiles, settings,
 * audit_logs, imports/import_records (blueprint §4).
 */
class MediaDocsAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(): Media
    {
        return Media::create([
            'model_type' => Project::class,
            'model_id' => 0,
            'collection_name' => 'default',
            'name' => 'test-image',
            'file_name' => 'test-image.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    private function makeProject(bool $published, ?int $ogImageId = null): Project
    {
        return Project::create([
            'title' => 'A Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'a-project-' . ($published ? 'published' : 'draft') . '-' . uniqid(),
            'published' => $published,
            'og_image_id' => $ogImageId,
        ]);
    }

    public function test_migrate_creates_media_documents_social_profiles_settings_audit_logs_imports_and_import_records(): void
    {
        $this->assertTrue(Schema::hasTable('media'));

        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasColumns('documents', [
            'id', 'title', 'kind', 'media_id', 'version', 'download_count', 'published', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('social_profiles'));
        $this->assertTrue(Schema::hasColumns('social_profiles', ['id', 'profile_id', 'platform', 'url', 'sort_order']));

        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasColumns('settings', ['id', 'key', 'value']));

        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));

        $this->assertTrue(Schema::hasTable('imports'));
        $this->assertTrue(Schema::hasColumns('imports', ['id', 'source', 'status']));

        $this->assertTrue(Schema::hasTable('import_records'));
        $this->assertTrue(Schema::hasColumns('import_records', ['id', 'import_id', 'external_id', 'payload']));
    }

    public function test_audit_log_rows_are_append_only_and_accept_no_updated_at_write(): void
    {
        $log = AuditLog::create([
            'action' => 'login_success',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 1,
        ]);

        $this->assertNotNull($log->fresh()->created_at);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('audit_logs is append-only');

        $log->update(['action' => 'login_failed']);
    }

    public function test_analytics_provider_interface_resolves_to_the_null_analytics_provider_binding(): void
    {
        $provider = $this->app->make(AnalyticsProviderInterface::class);

        $this->assertInstanceOf(NullAnalyticsProvider::class, $provider);
    }

    public function test_deleting_media_referenced_by_a_published_project_throws_and_leaves_the_row_intact(): void
    {
        $media = $this->makeMedia();
        $this->makeProject(published: true, ogImageId: $media->id);

        $this->expectException(MediaInUseException::class);

        try {
            $media->delete();
        } finally {
            $this->assertNotNull(Media::find($media->id));
        }
    }

    public function test_deleting_media_not_referenced_by_any_published_entity_succeeds(): void
    {
        $media = $this->makeMedia();
        $this->makeProject(published: false, ogImageId: $media->id);

        $media->delete();

        $this->assertNull(Media::find($media->id));
    }
}
