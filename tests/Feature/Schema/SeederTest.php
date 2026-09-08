<?php

namespace Tests\Feature\Schema;

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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E2-T7 — realistic content seeder + factories for every entity (blueprint §4 "Seed data",
 * §9 step 12).
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_runs_against_a_migrated_empty_database_and_exits_cleanly(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Reaching this line without an exception is the exit-0 assertion — the artisan
        // command itself is exercised end-to-end by this task's own `verify` array.
        $this->assertTrue(true);
    }

    public function test_seed_creates_exactly_one_user_with_the_super_admin_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(User::first()->hasRole('super_admin'));
    }

    public function test_seed_creates_two_or_three_published_articles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $count = Article::count();
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(3, $count);
        $this->assertSame($count, Article::where('published', true)->count());
    }

    public function test_seed_creates_rows_for_every_entity_blueprint_defines(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Profile::count());
        $this->assertSame(2, Company::count());
        $this->assertSame(3, Experience::count());
        $this->assertSame(2, Education::count());
        $this->assertSame(3, Certification::count());
        $this->assertSame(2, SkillCategory::count());
        $this->assertSame(8, Skill::count());
        $this->assertSame(5, Technology::count());
        $this->assertSame(2, ProjectCategory::count());
        $this->assertSame(4, Project::count());
        $this->assertSame(1, SoftwareProject::count());
        $this->assertSame(1, Media::count());
        $this->assertSame(1, Document::count());
        $this->assertSame(3, SocialProfile::count());
        $this->assertGreaterThanOrEqual(2, Setting::count());
    }

    public function test_running_the_seeder_twice_does_not_leave_duplicate_super_admin_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        // A real second db:seed run would fail on unique constraints (users.email, every
        // slug) well before RolesSeeder's own idempotency mattered — that's expected and
        // correct (db:seed is a one-time bootstrap, not a repeatable operation). This
        // isolates and confirms the one piece that genuinely is designed to run twice.
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesSeeder'])->assertSuccessful();

        $this->assertSame(1, Role::where('name', 'super_admin')->count());
    }
}
