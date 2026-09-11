<?php

namespace Tests\Feature\Phase2;

use App\Filament\Support\MediaUploadField;
use App\Models\Tool;
use App\Models\ToolDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E5-T5 (Phase 2, p2-step-39) — the tools/tool_downloads schema: a force-deleted Tool
 * cascades its download log, a soft-deleted one keeps it, and a single file attaches via
 * the app's one-FK-column convention on the private-media disk.
 */
class ToolSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tables_have_the_expected_shape(): void
    {
        $this->assertTrue(Schema::hasTable('tools'));
        $this->assertTrue(Schema::hasColumns('tools', [
            'title', 'slug', 'summary', 'description', 'version', 'published',
            'repo_url', 'sort_order', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('tool_downloads'));
        $this->assertTrue(Schema::hasColumns('tool_downloads', [
            'tool_id', 'ip', 'country', 'region', 'city', 'isp', 'referrer', 'user_agent', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('tool_downloads', 'updated_at'));
    }

    public function test_soft_deleting_a_tool_keeps_its_downloads(): void
    {
        $tool = Tool::factory()->create();
        ToolDownload::factory()->count(2)->create(['tool_id' => $tool->id]);

        $tool->delete();

        $this->assertSoftDeleted($tool);
        $this->assertSame(2, ToolDownload::where('tool_id', $tool->id)->count());
    }

    public function test_force_deleting_a_tool_cascades_its_downloads(): void
    {
        $tool = Tool::factory()->create();
        ToolDownload::factory()->count(2)->create(['tool_id' => $tool->id]);

        $tool->forceDelete();

        $this->assertSame(0, ToolDownload::where('tool_id', $tool->id)->count());
    }

    public function test_a_single_file_attaches_to_a_tool_on_private_media(): void
    {
        Storage::fake(MediaUploadField::DISK);
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/tool.zip', 'fake-zip-bytes');

        $tool = Tool::factory()->create();
        $media = MediaUploadField::createMediaRecord(MediaUploadField::DIRECTORY . '/tool.zip', Tool::class, $tool->id);
        $tool->update(['tool_file_media_id' => $media->id]);

        $this->assertSame(MediaUploadField::DISK, $tool->toolFile->disk);
        $this->assertSame('tool.zip', $tool->toolFile->file_name);
    }
}
