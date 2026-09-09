<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-T4 — public pages: Home, About, Experience, Skills (§9 step 22). One test per page, per
 * this task's own acceptance criterion 5.
 */
class CorePagesTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
            'location' => 'Remote',
            'availability_status' => 'Open to work',
        ]);
    }

    public function test_home_page_returns_200_and_renders_featured_projects_and_the_latest_3_published_articles(): void
    {
        Project::factory()->featured()->create(['title' => 'Featured One']);
        Project::factory()->create(['title' => 'Not Featured', 'featured' => false]);

        Article::factory()->create(['title' => 'Newest Article', 'published_at' => now()]);
        Article::factory()->create(['title' => 'Middle Article', 'published_at' => now()->subDay()]);
        Article::factory()->create(['title' => 'Oldest Shown Article', 'published_at' => now()->subDays(2)]);
        Article::factory()->create(['title' => 'Too Old To Show', 'published_at' => now()->subDays(3)]);
        Article::factory()->draft()->create(['title' => 'Draft Article']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Featured One');
        $response->assertDontSee('Not Featured');
        $response->assertSeeInOrder(['Newest Article', 'Middle Article', 'Oldest Shown Article']);
        $response->assertDontSee('Too Old To Show');
        $response->assertDontSee('Draft Article');

        // Found in review: swapped so the blog (ready to show) comes before Projects
        // (still being worked on) on the homepage.
        $response->assertSeeInOrder(['Latest from the blog', 'Featured projects']);
    }

    public function test_about_page_returns_200_and_renders_the_profile_bio_and_published_experience_education_summaries(): void
    {
        $profile = $this->profile();
        $company = Company::create(['name' => 'Acme Corp']);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Senior Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'senior-engineer',
            'published' => true,
        ]);
        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Unpublished Role',
            'description' => 'Hidden.',
            'started_at' => '2019-01-01',
            'slug' => 'unpublished-role',
            'published' => false,
        ]);

        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'State University',
            'degree' => 'B.Sc.',
            'field_of_study' => 'CS',
            'started_at' => '2014-09-01',
            'slug' => 'state-university-bsc',
            'published' => true,
        ]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
        $response->assertSee('Building reliable systems.');
        $response->assertSee('Senior Engineer');
        $response->assertDontSee('Unpublished Role');
        $response->assertSee('State University');
    }

    /**
     * Regression test for a real production report: "both studies at University of
     * Winnipeg are separated one without image and the ESPE in the middle when that one is
     * olders" — the About page's Education section had NO ordering at all, so it fell back
     * to whatever order the database happened to return rows in.
     */
    public function test_the_about_pages_education_section_is_ordered_most_recent_first(): void
    {
        $profile = $this->profile();

        // Inserted in a deliberately "wrong" order relative to date, matching how the real
        // bug was found: the middle (oldest) row inserted between the two most-recent ones.
        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'University of Winnipeg',
            'degree' => 'Management Certificate',
            'field_of_study' => 'Management',
            'started_at' => '2022-01-01',
            'ended_at' => '2022-12-31',
            'slug' => 'management-certificate',
            'published' => true,
            'sort_order' => 1,
        ]);
        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'Army Polytechnic School (ESPE)',
            'degree' => 'BS in Computer Science',
            'field_of_study' => 'Systems Engineering',
            'started_at' => '2005-01-01',
            'ended_at' => '2005-12-31',
            'slug' => 'espe-bsc',
            'published' => true,
            'sort_order' => 0,
        ]);
        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'University of Winnipeg',
            'degree' => 'Project Management Diploma',
            'field_of_study' => 'Project Management',
            'started_at' => '2022-01-01',
            'ended_at' => '2022-12-31',
            'slug' => 'pm-diploma',
            'published' => true,
            'sort_order' => 0,
        ]);

        $response = $this->get(route('about'));

        $response->assertOk();
        // The two 2022 entries (by sort_order) come before the 2005 one.
        $response->assertSeeInOrder(['Project Management Diploma', 'Management Certificate', 'BS in Computer Science']);
    }

    public function test_experience_page_returns_200_and_renders_only_published_experiences_ordered_by_started_at_desc(): void
    {
        $profile = $this->profile();
        $company = Company::create(['name' => 'Acme Corp']);

        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Older Role',
            'description' => 'A role.',
            'started_at' => '2018-01-01',
            'slug' => 'older-role',
            'published' => true,
        ]);
        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Newer Role',
            'description' => 'A role.',
            'started_at' => '2022-01-01',
            'slug' => 'newer-role',
            'published' => true,
        ]);
        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Hidden Role',
            'description' => 'A role.',
            'started_at' => '2023-01-01',
            'slug' => 'hidden-role',
            'published' => false,
        ]);

        $response = $this->get(route('experience'));

        $response->assertOk();
        $response->assertSeeInOrder(['Newer Role', 'Older Role']);
        $response->assertDontSee('Hidden Role');
    }

    public function test_skills_page_returns_200_and_groups_skills_by_skill_category(): void
    {
        $profile = $this->profile();

        $backend = SkillCategory::create(['name' => 'Backend', 'sort_order' => 0]);
        $frontend = SkillCategory::create(['name' => 'Frontend', 'sort_order' => 1]);

        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $backend->id,
            'name' => 'PHP',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);
        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $frontend->id,
            'name' => 'Tailwind CSS',
            'proficiency' => 'advanced',
            'sort_order' => 0,
        ]);

        $response = $this->get(route('skills'));

        $response->assertOk();
        $response->assertSee('Backend');
        $response->assertSee('PHP');
        $response->assertSee('Frontend');
        $response->assertSee('Tailwind CSS');
    }
}
