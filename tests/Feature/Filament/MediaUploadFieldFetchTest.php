<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\MediaUploadField;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression test for a real production request: "pull that information from the web and
 * save it to our site" — fetchAndStore() downloads a logo found on the web and stores it
 * exactly the way an admin's own upload would (re-encoded through GD, UUID filename, WebP
 * sibling), rather than linking to (or trusting) the remote URL directly.
 */
class MediaUploadFieldFetchTest extends TestCase
{
    // A tiny real 4x4 PNG — small enough to inline, real enough for GD to decode.
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkEpFjgAEmBiSAmwMADKgARK9yomwAAAAASUVORK5CYII=';

    // A 4x4 PALETTE/indexed-color PNG (imagecreate(), not imagecreatetruecolor()) — the
    // exact shape a real favicon service returns ("8-bit colormap" per `file`), and what
    // triggered the bug below.
    private const TINY_PALETTE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAEAQMAAACTPww9AAAAA1BMVEUKFB5+TFI6AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNggAAAAAgAAa9T6iIAAAAASUVORK5CYII=';

    public function test_it_downloads_re_encodes_and_stores_a_logo_found_on_the_web(): void
    {
        Storage::fake(MediaUploadField::DISK);
        Http::fake([
            'example.com/logo.png' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = MediaUploadField::fetchAndStore('https://example.com/logo.png');

        $this->assertNotNull($path);
        $this->assertStringStartsWith(MediaUploadField::DIRECTORY . '/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk(MediaUploadField::DISK)->assertExists($path);

        // A WebP sibling is written alongside, same as a real admin upload.
        $webpPath = preg_replace('/\.png$/', '.webp', $path);
        Storage::disk(MediaUploadField::DISK)->assertExists($webpPath);
    }

    /**
     * BUG FIXED (found running app:fetch-company-education-logos in production): every
     * fetched favicon came back as a palette/indexed-color PNG, and imagewebp() refuses to
     * encode a palette image at all ("Palette image not supported by webp") — this crashed
     * the command outright rather than just skipping the WebP sibling. Not
     * fetchAndStore()-specific: the identical crash would hit a real admin's own upload of
     * any indexed PNG.
     */
    public function test_a_palette_png_is_converted_to_truecolor_before_encoding_so_the_webp_sibling_does_not_crash(): void
    {
        Storage::fake(MediaUploadField::DISK);
        Http::fake([
            'example.com/favicon.png' => Http::response(base64_decode(self::TINY_PALETTE_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = MediaUploadField::fetchAndStore('https://example.com/favicon.png');

        $this->assertNotNull($path);
        Storage::disk(MediaUploadField::DISK)->assertExists($path);

        $webpPath = preg_replace('/\.png$/', '.webp', $path);
        Storage::disk(MediaUploadField::DISK)->assertExists($webpPath);
    }

    public function test_it_returns_null_for_a_non_image_response(): void
    {
        Http::fake([
            'example.com/not-an-image.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertNull(MediaUploadField::fetchAndStore('https://example.com/not-an-image.html'));
    }

    public function test_it_returns_null_when_the_request_fails(): void
    {
        Http::fake([
            'example.com/missing.png' => Http::response('', 404),
        ]);

        $this->assertNull(MediaUploadField::fetchAndStore('https://example.com/missing.png'));
    }
}
