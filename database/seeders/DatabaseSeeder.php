<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Company;
use App\Models\Document;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Media;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialProfile;
use App\Models\SoftwareProject;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Realistic seed data per blueprint §4 "Seed data" (§9 step 12) — every public section has
 * real content to render against, never an empty stub (Risk #1's mitigation).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = $this->seedAdminUser();
        $this->call(RolesSeeder::class);

        $profile = $this->seedProfile($admin);
        $companies = $this->seedCompanies();
        $this->seedExperiences($profile, $companies);
        $this->seedEducation($profile);
        $this->seedCertifications($profile);
        $this->seedSkills($profile);
        $this->seedTechnologies();
        $this->seedProjects();
        $this->seedDocument();
        $this->seedSocialProfiles($profile);
        $this->seedSettings();
        $this->seedArticles();
    }

    /**
     * MFA is left unconfirmed so the first real login enrols it (§4) — `super_admin`
     * login is blocked until then (E2-T5's EnsureMfaConfirmed).
     */
    private function seedAdminUser(): User
    {
        $email = config('admin.seed_email');
        $password = config('admin.seed_password');

        if (! $email || ! $password) {
            throw new RuntimeException(
                'ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD are required from step 12 onward — set both in .env before seeding.',
            );
        }

        return User::create([
            'name' => 'Site Administrator',
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function seedProfile(User $admin): Profile
    {
        return Profile::create([
            'user_id' => $admin->id,
            'full_name' => 'Alex Rivera',
            'headline' => 'Full-Stack Software Engineer & Infrastructure Generalist',
            'bio' => 'I build and operate web applications end to end — from schema design and '
                . 'backend services through to deployment and the infrastructure they run on. '
                . 'Recent work spans Laravel/Livewire applications, CI/CD pipelines, and '
                . 'self-hosted deployment targets. I care about systems that are boring in '
                . 'production: predictable, observable, and easy to hand off.',
            'location' => 'Remote',
            'availability_status' => 'Open to select freelance and contract work',
        ]);
    }

    /**
     * @return list<Company>
     */
    private function seedCompanies(): array
    {
        return [
            Company::create(['name' => 'Northwind Digital', 'website_url' => 'https://example.test/northwind']),
            Company::create(['name' => 'Fieldstone Systems', 'website_url' => 'https://example.test/fieldstone']),
        ];
    }

    /**
     * @param  list<Company>  $companies
     */
    private function seedExperiences(Profile $profile, array $companies): void
    {
        $rows = [
            [
                'company' => $companies[0],
                'title' => 'Senior Software Engineer',
                'description' => 'Led backend and infrastructure work for a portfolio of client web '
                    . 'applications: schema design, API contracts, CI pipelines, and production '
                    . 'deployment. Mentored two junior engineers.',
                'started_at' => '2022-03-01',
                'ended_at' => null,
            ],
            [
                'company' => $companies[1],
                'title' => 'Software Engineer',
                'description' => 'Built and maintained internal tooling and customer-facing features '
                    . 'in a Laravel monolith, including the team\'s first automated deployment pipeline.',
                'started_at' => '2019-06-01',
                'ended_at' => '2022-02-01',
            ],
            [
                'company' => $companies[0],
                'title' => 'Junior Developer',
                'description' => 'Started on the support and bugfix rotation before moving into '
                    . 'feature development on the core product.',
                'started_at' => '2017-09-01',
                'ended_at' => '2019-05-01',
            ],
        ];

        foreach ($rows as $row) {
            Experience::create([
                'profile_id' => $profile->id,
                'company_id' => $row['company']->id,
                'title' => $row['title'],
                'description' => $row['description'],
                'started_at' => $row['started_at'],
                'ended_at' => $row['ended_at'],
                'slug' => Str::slug($row['title'] . '-' . $row['company']->name),
                'published' => true,
                'sort_order' => 0,
            ]);
        }
    }

    private function seedEducation(Profile $profile): void
    {
        $rows = [
            ['institution' => 'State University', 'degree' => 'B.Sc. Computer Science', 'field_of_study' => 'Computer Science', 'started_at' => '2013-09-01', 'ended_at' => '2017-06-01'],
            ['institution' => 'Open Source Academy', 'degree' => 'Certificate, Backend Web Development', 'field_of_study' => 'Web Development', 'started_at' => '2017-01-01', 'ended_at' => '2017-05-01'],
        ];

        foreach ($rows as $row) {
            Education::create([
                'profile_id' => $profile->id,
                'institution' => $row['institution'],
                'degree' => $row['degree'],
                'field_of_study' => $row['field_of_study'],
                'started_at' => $row['started_at'],
                'ended_at' => $row['ended_at'],
                'slug' => Str::slug($row['institution'] . '-' . $row['degree']),
                'published' => true,
                'sort_order' => 0,
            ]);
        }
    }

    private function seedCertifications(Profile $profile): void
    {
        $rows = [
            ['name' => 'Certified Kubernetes Administrator', 'issuer' => 'CNCF', 'issued_at' => '2023-05-01'],
            ['name' => 'AWS Certified Solutions Architect – Associate', 'issuer' => 'Amazon Web Services', 'issued_at' => '2022-08-01'],
            ['name' => 'Professional Scrum Master I', 'issuer' => 'Scrum.org', 'issued_at' => '2020-02-01'],
        ];

        foreach ($rows as $row) {
            Certification::create([
                'profile_id' => $profile->id,
                'name' => $row['name'],
                'issuer' => $row['issuer'],
                'credential_url' => 'https://example.test/verify/' . Str::slug($row['name']),
                'issued_at' => $row['issued_at'],
                'slug' => Str::slug($row['name']),
                'published' => true,
                'sort_order' => 0,
            ]);
        }
    }

    private function seedSkills(Profile $profile): void
    {
        $categories = [
            SkillCategory::create(['name' => 'Languages & Frameworks', 'sort_order' => 0]),
            SkillCategory::create(['name' => 'Infrastructure & Tooling', 'sort_order' => 1]),
        ];

        $skills = [
            [$categories[0], 'PHP', 'expert'],
            [$categories[0], 'Laravel', 'expert'],
            [$categories[0], 'JavaScript', 'advanced'],
            [$categories[0], 'TypeScript', 'intermediate'],
            [$categories[1], 'PostgreSQL', 'advanced'],
            [$categories[1], 'Docker', 'advanced'],
            [$categories[1], 'GitHub Actions', 'advanced'],
            [$categories[1], 'Nginx', 'intermediate'],
        ];

        foreach ($skills as $index => [$category, $name, $proficiency]) {
            Skill::create([
                'profile_id' => $profile->id,
                'skill_category_id' => $category->id,
                'name' => $name,
                'proficiency' => $proficiency,
                'sort_order' => $index,
            ]);
        }
    }

    private function seedTechnologies(): void
    {
        foreach (['Laravel', 'PostgreSQL', 'Docker', 'Vue.js', 'Tailwind CSS'] as $name) {
            Technology::create(['name' => $name]);
        }
    }

    private function seedProjects(): void
    {
        $categories = [
            ProjectCategory::create(['name' => 'Web Applications', 'slug' => 'web-applications', 'sort_order' => 0]),
            ProjectCategory::create(['name' => 'Developer Tools', 'slug' => 'developer-tools', 'sort_order' => 1]),
        ];

        $projects = Project::factory()
            ->count(4)
            ->sequence(
                ['project_category_id' => $categories[0]->id],
                ['project_category_id' => $categories[0]->id],
                ['project_category_id' => $categories[1]->id],
                ['project_category_id' => $categories[1]->id],
            )
            ->create();

        // One project gets a software_projects 1:1 extension row (§4).
        SoftwareProject::create([
            'project_id' => $projects->first()->id,
            'language_primary' => 'PHP',
            'architecture_notes' => 'Laravel monolith, PostgreSQL, deployed to a single Debian LXC '
                . 'behind a Cloudflare Tunnel.',
            'deployment_notes' => 'PR-per-task GitHub flow, CI-gated deploys via a release script.',
        ]);
    }

    private function seedDocument(): void
    {
        $media = Media::create([
            'model_type' => Profile::class,
            'model_id' => 0,
            'collection_name' => 'default',
            'name' => 'resume',
            'file_name' => 'resume-placeholder.pdf',
            'mime_type' => 'application/pdf',
            'disk' => config('media-library.disk_name', 'public'),
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        Document::create([
            'title' => 'Résumé',
            'kind' => 'resume',
            'media_id' => $media->id,
            'version' => 1,
            'published' => true,
        ]);
    }

    private function seedSocialProfiles(Profile $profile): void
    {
        $rows = [
            ['platform' => 'GitHub', 'url' => 'https://github.com/example'],
            ['platform' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/example'],
            ['platform' => 'Mastodon', 'url' => 'https://mastodon.social/@example'],
        ];

        foreach ($rows as $index => $row) {
            SocialProfile::create([
                'profile_id' => $profile->id,
                'platform' => $row['platform'],
                'url' => $row['url'],
                'sort_order' => $index,
            ]);
        }
    }

    private function seedSettings(): void
    {
        Setting::put('site_title', 'miautrix');
        Setting::put('theme_default', 'technical');
        Setting::put('contact_email', config('admin.seed_email'));
    }

    private function seedArticles(): void
    {
        Article::factory()->count(3)->create();
    }
}
