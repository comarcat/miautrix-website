<?php

namespace Tests\Feature\Console;

use App\Models\Company;
use App\Models\Education;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchCompanyEducationLogosTest extends TestCase
{
    use RefreshDatabase;

    // A tiny real 4x4 PNG — small enough to inline, real enough for GD to decode.
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkEpFjgAEmBiSAmwMADKgARK9yomwAAAAASUVORK5CYII=';

    public function test_it_attaches_a_logo_to_a_matching_company(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        $company = Company::create(['name' => 'MSP Corp Prairies (Broadview Networks)']);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNotNull($company->fresh()->logo_media_id);
    }

    public function test_it_attaches_a_logo_to_a_matching_education_row(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Test',
            'headline' => 'Test',
            'bio' => 'Test.',
        ]);
        $education = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'University of Winnipeg (Professional, Applied & Continuing Education)',
            'degree' => 'Diploma',
            'field_of_study' => 'Project Management',
            'started_at' => '2022-01-01',
            'slug' => 'diploma-test',
            'published' => true,
        ]);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNotNull($education->fresh()->logo_media_id);
    }

    public function test_it_skips_gracefully_when_no_matching_row_exists(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
        ]);

        // No Company/Education rows at all — every lookup misses, nothing should error.
        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();
    }

    public function test_it_leaves_the_company_without_a_logo_when_the_fetch_fails(): void
    {
        Http::fake([
            'www.google.com/s2/favicons*' => Http::response('', 404),
        ]);

        $company = Company::create(['name' => 'MSP Corp Prairies (Broadview Networks)']);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNull($company->fresh()->logo_media_id);
    }
}
