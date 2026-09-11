<?php

namespace Tests\Feature\Phase2;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Support\MediaUploadField;
use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use App\Rules\AllowedMediaMime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E4-T3 (Phase 2, p2-step-28) — supplementary project files (project_files pivot + media
 * row): a PDF/ZIP is stored on private-media with a generated filename and an optional
 * label; an SVG is rejected and stores nothing; the admin field is wired through the shared
 * hardened upload component, not a bare FileUpload.
 *
 * Exercises the storage/attach logic CreateProject::handleRecordCreation and
 * EditProject::afterSave run (MediaUploadField::createMediaRecord + Project::projectFiles()
 * ->attach()) directly, rather than driving Filament's FileUpload component through a live
 * Livewire form fill — the rest of this codebase has no test that does that for any
 * MediaUploadField either (EditSocialProfile's icon included), since Filament's FileUpload
 * represents an in-progress upload very differently from the stored-path string
 * saveUploadedFileUsing() ultimately leaves in form state.
 */
class ProjectFilesUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaUploadField::DISK);
    }

    private function storedPdfPath(): string
    {
        $upload = Livewire::test(FileStoreTestComponent::class)
            ->set('file', UploadedFile::fake()->create('one-pager.pdf', 20, 'application/pdf'));

        /** @var FileStoreTestComponent $component */
        $component = $upload->instance();
        $path = MediaUploadField::store($component->file);

        $this->assertNotNull($path);

        return $path;
    }

    public function test_a_pdf_upload_is_stored_in_the_project_files_pivot_on_private_media(): void
    {
        $path = $this->storedPdfPath();
        $project = Project::factory()->create();

        // What CreateProject::handleRecordCreation / EditProject::afterSave do with one
        // repeater row once the form has handed back a stored path + label.
        $media = MediaUploadField::createMediaRecord($path, Project::class, $project->id);
        $project->projectFiles()->attach($media->id, ['label' => 'One-pager', 'sort_order' => 0]);

        $project->refresh();
        $this->assertCount(1, $project->projectFiles);

        $stored = $project->projectFiles->first();
        $this->assertSame(MediaUploadField::DISK, $stored->disk);
        $this->assertSame('One-pager', $stored->pivot?->getAttribute('label'));
        // Generated filename — never the client-supplied "one-pager.pdf".
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $stored->file_name);
        Storage::disk(MediaUploadField::DISK)->assertExists(MediaUploadField::DIRECTORY . '/' . $stored->file_name);
    }

    public function test_an_svg_upload_is_rejected_and_stores_nothing(): void
    {
        $svg = UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml');

        $validator = Validator::make(['path' => $svg], ['path' => [new AllowedMediaMime]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(0, Media::count());
    }

    public function test_the_files_field_is_a_repeater_of_the_hardened_upload_component(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateProject::class)
            ->assertFormFieldExists('files');

        // MediaUploadField (disk=private-media, no SVG, PDF/ZIP allowed, UUID filenames) is
        // what every real upload field in the admin is built from (its own docblock) — proven
        // once for the whole app by UploadValidationTest. Here: the Files repeater item is
        // actually built from it, not a bare FileUpload with its own (weaker) allowlist.
        $source = (string) file_get_contents(app_path('Filament/Resources/Projects/Schemas/ProjectForm.php'));
        $this->assertMatchesRegularExpression(
            '/Repeater::make\(\'files\'\).*?MediaUploadField::make\(\'path\'\)/s',
            $source,
        );
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}

/**
 * Test-only fixture: uses Livewire's own WithFileUploads purely to get the automatic
 * UploadedFile -> TemporaryUploadedFile conversion MediaUploadField::store() needs — same
 * fixture UploadValidationTest uses.
 */
class FileStoreTestComponent extends Component
{
    use WithFileUploads;

    public $file;

    public function render(): string
    {
        return '<div></div>';
    }
}
