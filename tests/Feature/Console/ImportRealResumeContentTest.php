<?php

namespace Tests\Feature\Console;

use App\Models\Certification;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRealResumeContentTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlaceholderProfile(): Profile
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Alex Rivera',
            'headline' => 'Full-Stack Software Engineer & Infrastructure Generalist',
            'bio' => 'Placeholder bio.',
            'location' => 'Remote',
            'availability_status' => 'Open to select freelance and contract work',
        ]);

        $company = Company::create(['name' => 'Northwind Digital']);
        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Senior Software Engineer',
            'description' => 'Placeholder.',
            'started_at' => '2022-01-01',
            'slug' => 'senior-software-engineer',
            'published' => true,
        ]);

        $category = SkillCategory::create(['name' => 'Languages & Frameworks', 'sort_order' => 0]);
        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'PHP',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);

        return $profile;
    }

    public function test_it_replaces_the_placeholder_profile_with_the_real_resume_content(): void
    {
        $profile = $this->seedPlaceholderProfile();

        $this->artisan('app:import-real-resume-content')->assertSuccessful();

        $profile->refresh();
        $this->assertSame('Omar (Cristobal) Arboleda Teran', $profile->full_name);
        $this->assertSame('IT Infrastructure & Operations Manager', $profile->headline);
        $this->assertSame('Winnipeg, MB, Canada', $profile->location);

        $this->assertSame(1, SocialProfile::where('profile_id', $profile->id)->count());
        $this->assertDatabaseHas('social_profiles', ['platform' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/carboledate/']);

        // The placeholder company/experience/skill/category must be gone, replaced by real
        // ones.
        $this->assertDatabaseMissing('companies', ['name' => 'Northwind Digital']);
        $this->assertDatabaseHas('companies', ['name' => 'FESAECUADOR']);
        $this->assertDatabaseMissing('experiences', ['title' => 'Senior Software Engineer']);
        $this->assertTrue(Experience::where('profile_id', $profile->id)->count() === 4);

        $this->assertDatabaseMissing('skill_categories', ['name' => 'Languages & Frameworks']);
        $this->assertDatabaseHas('skill_categories', ['name' => 'Microsoft Technologies']);
        $this->assertDatabaseHas('skills', ['name' => 'Fortinet', 'proficiency' => 'expert']);

        $this->assertSame(3, Education::where('profile_id', $profile->id)->count());
        $this->assertDatabaseHas('education', ['degree' => 'BS in Computer Science']);

        // Only the 2 dated certifications — never a guessed date for the other 6.
        $this->assertSame(2, Certification::where('profile_id', $profile->id)->count());
        $this->assertDatabaseHas('certifications', ['name' => 'Microsoft Expert Level Gold']);
        $this->assertDatabaseMissing('certifications', ['name' => 'Dell Storage Solutions Certification']);
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->seedPlaceholderProfile();

        $this->artisan('app:import-real-resume-content')->assertSuccessful();
        $this->artisan('app:import-real-resume-content')->assertSuccessful();

        $profile = Profile::first();
        $this->assertSame(4, Experience::where('profile_id', $profile->id)->count());
        $this->assertSame(1, SocialProfile::where('profile_id', $profile->id)->count());
    }

    public function test_it_fails_gracefully_when_no_profile_exists(): void
    {
        $this->artisan('app:import-real-resume-content')->assertFailed();
    }
}
