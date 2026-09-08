<?php

namespace Tests\Feature\Schema;

use App\Models\Certification;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E2-T2 — career schema: companies, experiences, education, certifications,
 * skill_categories, skills, technologies (blueprint §4).
 */
class CareerSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(): Profile
    {
        $user = User::factory()->create();

        return Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);
    }

    public function test_migrate_creates_all_seven_career_tables_with_their_documented_columns(): void
    {
        $this->assertTrue(Schema::hasTable('companies'));
        $this->assertTrue(Schema::hasColumns('companies', ['id', 'name', 'logo_media_id', 'website_url', 'deleted_at']));

        $this->assertTrue(Schema::hasTable('experiences'));
        $this->assertTrue(Schema::hasColumns('experiences', [
            'id', 'profile_id', 'company_id', 'title', 'description', 'started_at', 'ended_at',
            'slug', 'published', 'featured', 'sort_order', 'seo_title', 'meta_description',
            'canonical_url', 'og_title', 'og_description', 'og_image_id', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('education'));
        $this->assertTrue(Schema::hasColumns('education', [
            'id', 'profile_id', 'institution', 'degree', 'field_of_study', 'started_at', 'ended_at',
            'slug', 'published', 'featured', 'sort_order', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('certifications'));
        $this->assertTrue(Schema::hasColumns('certifications', [
            'id', 'profile_id', 'name', 'issuer', 'credential_id', 'credential_url',
            'issued_at', 'expires_at', 'media_id', 'slug', 'published', 'featured', 'sort_order', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('skill_categories'));
        $this->assertTrue(Schema::hasColumns('skill_categories', ['id', 'name', 'sort_order', 'deleted_at']));

        $this->assertTrue(Schema::hasTable('skills'));
        $this->assertTrue(Schema::hasColumns('skills', [
            'id', 'profile_id', 'skill_category_id', 'name', 'proficiency', 'sort_order', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('technologies'));
        $this->assertTrue(Schema::hasColumns('technologies', ['id', 'name', 'icon_slug', 'deleted_at']));
    }

    public function test_profile_experiences_returns_only_that_profiles_rows_ordered_by_started_at_desc(): void
    {
        $profile = $this->makeProfile();
        $otherProfile = $this->makeProfile();
        $company = Company::create(['name' => 'Acme Corp']);

        $older = Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Junior Engineer',
            'description' => 'First role.',
            'started_at' => '2018-01-01',
            'ended_at' => '2020-01-01',
            'slug' => 'junior-engineer',
        ]);
        $newer = Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Senior Engineer',
            'description' => 'Current role.',
            'started_at' => '2020-01-01',
            'ended_at' => null,
            'slug' => 'senior-engineer',
        ]);
        Experience::create([
            'profile_id' => $otherProfile->id,
            'company_id' => $company->id,
            'title' => 'Not this profile',
            'description' => 'Belongs to someone else.',
            'started_at' => '2021-01-01',
            'slug' => 'not-this-profile',
        ]);

        $results = $profile->experiences()->get();

        $this->assertCount(2, $results);
        $this->assertSame([$newer->id, $older->id], $results->pluck('id')->all());
    }

    public function test_duplicate_slug_in_experiences_raises_a_unique_constraint_violation(): void
    {
        $profile = $this->makeProfile();
        $company = Company::create(['name' => 'Acme Corp']);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'duplicate-slug',
        ]);

        $this->expectException(QueryException::class);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Another Engineer',
            'description' => 'Another role.',
            'started_at' => '2021-01-01',
            'slug' => 'duplicate-slug',
        ]);
    }

    public function test_skill_belongs_to_its_skill_category(): void
    {
        $profile = $this->makeProfile();
        $category = SkillCategory::create(['name' => 'Languages', 'sort_order' => 0]);

        $skill = Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'PHP',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);

        $this->assertTrue($skill->skillCategory->is($category));
        $this->assertTrue($category->skills->first()->is($skill));
    }

    public function test_technology_name_is_unique_at_the_database_level(): void
    {
        Technology::create(['name' => 'Laravel']);

        $this->expectException(QueryException::class);

        Technology::create(['name' => 'Laravel']);
    }

    public function test_company_has_many_experiences(): void
    {
        $profile = $this->makeProfile();
        $company = Company::create(['name' => 'Acme Corp']);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'engineer-at-acme',
        ]);

        $this->assertCount(1, $company->experiences);
    }

    public function test_certification_persists_with_nullable_credential_id_and_expires_at(): void
    {
        $profile = $this->makeProfile();

        $certification = Certification::create([
            'profile_id' => $profile->id,
            'name' => 'Certified Kubernetes Administrator',
            'issuer' => 'CNCF',
            'credential_id' => null,
            'credential_url' => 'https://example.test/verify',
            'issued_at' => '2023-05-01',
            'expires_at' => null,
            'slug' => 'certified-kubernetes-administrator',
        ]);

        $this->assertNull($certification->fresh()->credential_id);
        $this->assertNull($certification->fresh()->expires_at);
        $this->assertTrue($certification->profile->is($profile));

        // education created alongside to confirm the sibling table round-trips too, without
        // needing its own dedicated test slot.
        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'State University',
            'degree' => 'B.Sc. Computer Science',
            'field_of_study' => 'Computer Science',
            'started_at' => '2014-09-01',
            'ended_at' => '2018-06-01',
            'slug' => 'bsc-computer-science',
        ]);

        $this->assertCount(1, $profile->education);
    }
}
