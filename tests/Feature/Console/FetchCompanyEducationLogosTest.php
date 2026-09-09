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

    private function fakeEveryFetchSucceeds(): void
    {
        Http::fake(fn () => Http::response(base64_decode(self::TINY_PNG_BASE64), 200, ['Content-Type' => 'image/png']));
    }

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Test',
            'headline' => 'Test',
            'bio' => 'Test.',
        ]);
    }

    public function test_it_attaches_a_logo_to_a_matching_company(): void
    {
        $this->fakeEveryFetchSucceeds();

        $company = Company::create(['name' => 'MSP Corp Prairies (Broadview Networks)']);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNotNull($company->fresh()->logo_media_id);
    }

    public function test_it_attaches_a_logo_to_a_matching_education_row(): void
    {
        $this->fakeEveryFetchSucceeds();

        $profile = $this->profile();
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

    /**
     * Regression test for a real production bug: two Education rows sharing the same
     * institution name (a Diploma and a Certificate, both "University of Winnipeg...")
     * used to only get ONE of them updated (->first()) — the other silently kept no logo.
     */
    public function test_it_attaches_a_logo_to_every_row_sharing_the_same_institution_name(): void
    {
        $this->fakeEveryFetchSucceeds();

        $profile = $this->profile();
        $diploma = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'University of Winnipeg (Professional, Applied & Continuing Education)',
            'degree' => 'Diploma',
            'field_of_study' => 'Project Management',
            'started_at' => '2022-01-01',
            'slug' => 'diploma-test',
            'published' => true,
        ]);
        $certificate = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'University of Winnipeg (Professional, Applied & Continuing Education)',
            'degree' => 'Certificate',
            'field_of_study' => 'Management',
            'started_at' => '2022-01-01',
            'slug' => 'certificate-test',
            'published' => true,
        ]);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNotNull($diploma->fresh()->logo_media_id);
        $this->assertNotNull($certificate->fresh()->logo_media_id);
        // Independent Media rows, one per owning record.
        $this->assertNotEquals($diploma->fresh()->logo_media_id, $certificate->fresh()->logo_media_id);
    }

    public function test_it_attaches_a_logo_to_espe_and_digital_solutions_via_their_own_sites(): void
    {
        $this->fakeEveryFetchSucceeds();

        $profile = $this->profile();
        $digitalSolutions = Company::create(['name' => 'Digital Solutions']);
        $espe = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'Army Polytechnic School (ESPE), Quito, Ecuador',
            'degree' => 'BS in Computer Science',
            'field_of_study' => 'Systems Engineering',
            'started_at' => '2005-01-01',
            'slug' => 'espe-test',
            'published' => true,
        ]);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNotNull($digitalSolutions->fresh()->logo_media_id);
        $this->assertNotNull($espe->fresh()->logo_media_id);
    }

    public function test_it_skips_gracefully_when_no_matching_row_exists(): void
    {
        $this->fakeEveryFetchSucceeds();

        // No Company/Education rows at all — every lookup misses, nothing should error.
        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();
    }

    public function test_it_leaves_the_company_without_a_logo_when_the_fetch_fails(): void
    {
        Http::fake(fn () => Http::response('', 404));

        $company = Company::create(['name' => 'MSP Corp Prairies (Broadview Networks)']);

        $this->artisan('app:fetch-company-education-logos')->assertSuccessful();

        $this->assertNull($company->fresh()->logo_media_id);
    }
}
