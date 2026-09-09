<?php

namespace Tests\Feature\Media;

use App\Filament\Support\MediaUploadField;
use App\Models\Media;
use App\Models\Project;
use App\Rules\AllowedMediaMime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Tests\TestCase;

/**
 * E3-T4 — media library: hardened upload validation, storage outside the web root
 * (blueprint §9 step 16).
 */
class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaUploadField::DISK);
    }

    public function test_an_svg_upload_is_rejected_and_never_stored(): void
    {
        $svg = UploadedFile::fake()->create('image.svg', 5, 'image/svg+xml');

        $validator = Validator::make(['file' => $svg], ['file' => [new AllowedMediaMime]]);

        $this->assertTrue($validator->fails());
        Storage::disk(MediaUploadField::DISK)->assertDirectoryEmpty(MediaUploadField::DIRECTORY);
    }

    public function test_a_file_with_a_spoofed_extension_is_rejected_based_on_sniffed_mime_not_the_extension(): void
    {
        // Real PHP source bytes, named as if it were a JPEG — getMimeType() sniffs the
        // actual content via fileinfo, never trusting the extension or a client-supplied
        // Content-Type.
        $path = tempnam(sys_get_temp_dir(), 'spoof');
        file_put_contents($path, '<?php echo "not a jpeg"; ?>');
        $spoofed = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);

        $validator = Validator::make(['file' => $spoofed], ['file' => [new AllowedMediaMime]]);

        $this->assertTrue($validator->fails());

        @unlink($path);
    }

    public function test_a_valid_jpeg_is_stored_reencoded_under_private_media_with_a_uuid_prefixed_filename(): void
    {
        $upload = Livewire::test(UploadValidationTestComponent::class)
            ->set('file', UploadedFile::fake()->image('photo.jpg', 200, 200));

        /** @var UploadValidationTestComponent $component */
        $component = $upload->instance();
        $path = MediaUploadField::store($component->file);

        $this->assertNotNull($path);
        $this->assertStringStartsWith(MediaUploadField::DIRECTORY . '/', $path);
        $this->assertMatchesRegularExpression(
            '/^' . preg_quote(MediaUploadField::DIRECTORY, '/') . '\/[0-9a-f-]{36}\.jpg$/',
            $path,
        );

        Storage::disk(MediaUploadField::DISK)->assertExists($path);

        // Genuinely re-encoded, not a byte-for-byte copy: decodable as a real JPEG on its own.
        $storedBytes = Storage::disk(MediaUploadField::DISK)->get($path);
        $tmpCheck = tempnam(sys_get_temp_dir(), 'reencoded');
        file_put_contents($tmpCheck, $storedBytes);
        $info = @getimagesize($tmpCheck);
        @unlink($tmpCheck);

        $this->assertNotFalse($info);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_a_jpeg_upload_also_gets_a_webp_sibling_alongside_the_original(): void
    {
        $upload = Livewire::test(UploadValidationTestComponent::class)
            ->set('file', UploadedFile::fake()->image('photo.jpg', 200, 200));

        /** @var UploadValidationTestComponent $component */
        $component = $upload->instance();
        $path = MediaUploadField::store($component->file);

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
        Storage::disk(MediaUploadField::DISK)->assertExists($webpPath);

        $storedBytes = Storage::disk(MediaUploadField::DISK)->get($webpPath);
        $tmpCheck = tempnam(sys_get_temp_dir(), 'webp-check');
        file_put_contents($tmpCheck, $storedBytes);
        $info = @getimagesize($tmpCheck);
        @unlink($tmpCheck);

        $this->assertNotFalse($info);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_media_belonging_to_an_unpublished_entity_404s(): void
    {
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/secret.jpg', 'fake-bytes');

        $project = Project::create([
            'title' => 'Unpublished Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'unpublished-project',
            'published' => false,
        ]);

        $media = Media::create([
            'model_type' => Project::class,
            'model_id' => $project->id,
            'collection_name' => 'default',
            'name' => 'secret',
            'file_name' => 'secret.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => MediaUploadField::DISK,
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $this->get(route('media.show', ['media' => $media->id, 'filename' => 'secret.jpg']))
            ->assertNotFound();
    }

    public function test_media_belonging_to_a_published_entity_is_served(): void
    {
        Storage::disk(MediaUploadField::DISK)->put(MediaUploadField::DIRECTORY . '/public.jpg', 'fake-bytes');

        $project = Project::create([
            'title' => 'Published Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'published-project',
            'published' => true,
        ]);

        $media = Media::create([
            'model_type' => Project::class,
            'model_id' => $project->id,
            'collection_name' => 'default',
            'name' => 'public',
            'file_name' => 'public.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => MediaUploadField::DISK,
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        // BinaryFileResponse streams its content via sendContent() rather than buffering it,
        // so TestResponse::getContent()/assertSee() see an empty body here even on success —
        // a well-known Laravel testing quirk, not a bug in the response itself. 200 (vs. the
        // 404 the previous test gets for the exact same setup, minus `published`) is what
        // actually distinguishes the two cases.
        $this->get(route('media.show', ['media' => $media->id, 'filename' => 'public.jpg']))
            ->assertOk();
    }
}

/**
 * Test-only fixture: uses Livewire's own WithFileUploads (not Filament) purely to get
 * ::set()'s automatic UploadedFile -> TemporaryUploadedFile conversion, so
 * MediaUploadField::store() can be exercised against a real temporary upload the same way
 * Filament's FileUpload would hand it one.
 */
class UploadValidationTestComponent extends Component
{
    use WithFileUploads;

    public $file;

    public function render(): string
    {
        return '<div></div>';
    }
}
