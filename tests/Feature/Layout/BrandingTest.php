<?php

namespace Tests\Feature\Layout;

use Tests\TestCase;

/**
 * Regression test for a real production request: "use that image as website icon file, as
 * well as the main logo" — the public header, the favicon files, and the retained
 * starter-kit's own logo slot (app-logo-icon.blade.php, shared by its sidebar/header brand
 * and the auth card/simple/split layouts) now all use the real miautrix artwork
 * (public/images/brand/miautrix-logo.png) instead of the generic starter-kit mark / plain
 * text-only brand link.
 */
class BrandingTest extends TestCase
{
    public function test_the_public_header_shows_the_brand_logo_image(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('images/brand/miautrix-logo.png', false);
    }

    public function test_the_favicon_files_exist_and_are_the_expected_formats(): void
    {
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));

        $ico = file_get_contents(public_path('favicon.ico'));
        // ICO header: reserved (0x0000) + type 1 (image) — first 4 bytes.
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));

        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertStringContainsString('<svg', $svg);
    }
}
