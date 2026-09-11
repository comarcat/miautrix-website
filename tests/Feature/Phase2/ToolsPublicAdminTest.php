<?php

namespace Tests\Feature\Phase2;

use App\Filament\Resources\Tools\ToolResource;
use App\Filament\Support\MediaUploadField;
use App\Models\Tool;
use App\Models\ToolDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E5-T6 (Phase 2, p2-step-40) — /tools lists only published tools, /tools/{slug}/download
 * logs one row and streams the file, a Tool save busts the /tools index cache, and the
 * download route is never cached; the admin ToolResource shows a CRUD table + downloads.
 */
class ToolsPublicAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaUploadField::DISK);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_the_index_lists_only_published_tools_and_the_show_page_404s_for_unpublished(): void
    {
        $published = Tool::factory()->create(['title' => 'Published Tool', 'published' => true]);
        $draft = Tool::factory()->create(['title' => 'Draft Tool', 'published' => false]);

        $this->get(route('tools.index'))
            ->assertOk()
            ->assertSee('Published Tool')
            ->assertDontSee('Draft Tool');

        $this->get(route('tools.show', $published->slug))->assertOk();
        $this->get(route('tools.show', $draft->slug))->assertNotFound();
    }

    public function test_downloading_a_published_tools_file_logs_one_row_and_streams_it(): void
    {
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/tool.zip', 'fake-zip-bytes');

        $tool = Tool::factory()->create(['published' => true]);
        $media = MediaUploadField::createMediaRecord(MediaUploadField::DIRECTORY . '/tool.zip', Tool::class, $tool->id);
        $tool->update(['tool_file_media_id' => $media->id]);

        $response = $this->get(route('tools.download', $tool->slug));

        $response->assertOk();
        $this->assertSame(1, ToolDownload::where('tool_id', $tool->id)->count());
        $download = ToolDownload::where('tool_id', $tool->id)->first();
        $this->assertNotEmpty($download->ip);
    }

    public function test_saving_a_tool_busts_the_tools_index_cache_and_the_download_route_is_never_cached(): void
    {
        $this->assertNotContains('cache.public', Route::getRoutes()->getByName('tools.download')->middleware());

        foreach (['127.0.0.1:technical', 'miautrix.tech:matrix'] as $seg) {
            Cache::put("public-page:{$seg}:tools", 'stale', 600);
        }

        Tool::factory()->create();

        foreach (['127.0.0.1:technical', 'miautrix.tech:matrix'] as $seg) {
            $this->assertFalse(Cache::has("public-page:{$seg}:tools"), "{$seg} not forgotten");
        }
    }

    public function test_an_unpublished_tools_download_route_is_404(): void
    {
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/tool.zip', 'fake-zip-bytes');

        $tool = Tool::factory()->create(['published' => false]);
        $media = MediaUploadField::createMediaRecord(MediaUploadField::DIRECTORY . '/tool.zip', Tool::class, $tool->id);
        $tool->update(['tool_file_media_id' => $media->id]);

        $this->get(route('tools.download', $tool->slug))->assertNotFound();
        $this->assertSame(0, ToolDownload::where('tool_id', $tool->id)->count());
    }

    public function test_the_admin_resource_shows_a_crud_table_and_the_downloads_breakdown(): void
    {
        $tool = Tool::factory()->create();
        ToolDownload::factory()->create(['tool_id' => $tool->id, 'country' => 'US']);

        $this->actingAs($this->superAdmin())
            ->get(ToolResource::getUrl('index'))
            ->assertOk()
            ->assertSee($tool->title);

        $this->actingAs($this->superAdmin())
            ->get(ToolResource::getUrl('edit', ['record' => $tool]))
            ->assertOk()
            ->assertSee('Downloads');
    }
}
