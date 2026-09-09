<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production request: "add the logos of the companies /
 * education institutions" — neither the Experience (via Company) nor Education timeline on
 * /experience and /about rendered a logo at all; Education had no logo column whatsoever.
 */
class CompanyEducationLogoTest extends TestCase
{
    use RefreshDatabase;

    private function makeLogoMedia(string $modelType, int $modelId): Media
    {
        return Media::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'collection_name' => 'default',
            'name' => 'logo',
            'file_name' => 'company-logo.png',
            'mime_type' => 'image/png',
            'disk' => 'private-media',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);
    }

    public function test_the_experience_page_shows_a_company_logo_when_one_is_attached(): void
    {
        $profile = $this->profile();
        $company = Company::create(['name' => 'Acme Corp']);
        $logo = $this->makeLogoMedia(Company::class, $company->id);
        $company->update(['logo_media_id' => $logo->id]);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'engineer-with-logo',
            'published' => true,
        ]);

        $response = $this->get(route('experience'));

        $response->assertOk();
        $response->assertSee(route('media.show', [$logo, $logo->file_name]), false);
    }

    public function test_the_about_page_shows_an_education_logo_when_one_is_attached(): void
    {
        $profile = $this->profile();

        $education = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'State University',
            'degree' => 'B.Sc.',
            'field_of_study' => 'CS',
            'started_at' => '2014-09-01',
            'slug' => 'state-university-bsc-with-logo',
            'published' => true,
        ]);
        $logo = $this->makeLogoMedia(Education::class, $education->id);
        $education->update(['logo_media_id' => $logo->id]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee(route('media.show', [$logo, $logo->file_name]), false);
    }

    public function test_a_timeline_entry_with_no_logo_renders_without_one(): void
    {
        $profile = $this->profile();
        $company = Company::create(['name' => 'No Logo Inc']);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'engineer-without-logo',
            'published' => true,
        ]);

        $response = $this->get(route('experience'));

        $response->assertOk();
        $response->assertSee('No Logo Inc');
        // Not a blanket "no <img> anywhere" check — the page's own header logo is an <img>
        // too. Specifically, the timeline's logo slot (the size-12 class from
        // timeline.blade.php) must not render when the entry has none.
        $response->assertDontSee('size-12 shrink-0', false);
    }
}
