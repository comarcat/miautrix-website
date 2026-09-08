<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Certifications\CertificationResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Education\EducationResource;
use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\Experiences\Pages\CreateExperience;
use App\Filament\Resources\Profiles\ProfileResource;
use App\Models\Certification;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E3-T2 — Filament resources set 1: Profile, Companies, Experience, Education, Certifications
 * (blueprint §9 step 14). One test per resource, per the epic's own instruction.
 */
class ResourceSet1Test extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_profile_resource_index_returns_200_for_the_authenticated_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(ProfileResource::getUrl('index'))
            ->assertOk();
    }

    public function test_company_resource_index_returns_200_for_the_authenticated_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(CompanyResource::getUrl('index'))
            ->assertOk();
    }

    public function test_experience_resource_index_returns_200_and_creating_through_the_form_persists_a_non_null_slug_derived_from_title(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(ExperienceResource::getUrl('index'))
            ->assertOk();

        $profile = Profile::create([
            'user_id' => $admin->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);
        $company = Company::create(['name' => 'Acme Corp']);

        Livewire::actingAs($admin)
            ->test(CreateExperience::class)
            ->fillForm([
                'profile_id' => $profile->id,
                'company_id' => $company->id,
                'title' => 'Senior Engineer',
                'description' => 'A role.',
                'started_at' => '2020-01-01',
                // slug deliberately left blank — HasAutoSlug derives it from title.
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $experience = Experience::where('title', 'Senior Engineer')->firstOrFail();
        $this->assertNotNull($experience->slug);
        $this->assertSame('senior-engineer', $experience->slug);
    }

    public function test_education_resource_index_returns_200_and_the_published_toggle_governs_the_public_query_scope(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(EducationResource::getUrl('index'))
            ->assertOk();

        $profile = Profile::create([
            'user_id' => $admin->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);

        $education = Education::create([
            'profile_id' => $profile->id,
            'institution' => 'State University',
            'degree' => 'B.Sc.',
            'field_of_study' => 'CS',
            'started_at' => '2014-09-01',
            'slug' => 'state-university-bsc',
            'published' => true,
        ]);

        $this->assertTrue(Education::where('published', true)->whereKey($education->id)->exists());

        $education->update(['published' => false]);

        $this->assertFalse(Education::where('published', true)->whereKey($education->id)->exists());
    }

    public function test_certification_resource_index_returns_200_and_the_published_toggle_governs_the_public_query_scope(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(CertificationResource::getUrl('index'))
            ->assertOk();

        $profile = Profile::create([
            'user_id' => $admin->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);

        $certification = Certification::create([
            'profile_id' => $profile->id,
            'name' => 'Certified Kubernetes Administrator',
            'issuer' => 'CNCF',
            'credential_url' => 'https://example.test/verify',
            'issued_at' => '2023-05-01',
            'slug' => 'certified-kubernetes-administrator',
            'published' => true,
        ]);

        $this->assertTrue(Certification::where('published', true)->whereKey($certification->id)->exists());

        $certification->update(['published' => false]);

        $this->assertFalse(Certification::where('published', true)->whereKey($certification->id)->exists());
    }
}
