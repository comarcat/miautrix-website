<?php

namespace Tests\Feature\Auth;

use App\Models\Article;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E2-T6 — authorization: spatie/laravel-permission, Policies, seeded super_admin (§9 step 11).
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(User $user): Profile
    {
        return Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);
    }

    private function makeProject(): Project
    {
        return Project::create([
            'title' => 'A Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'a-project',
        ]);
    }

    private function makeArticle(): Article
    {
        return Article::create([
            'title' => 'An Article',
            'excerpt' => 'Excerpt.',
            'body' => 'Body.',
            'slug' => 'an-article',
        ]);
    }

    public function test_migrate_creates_the_spatie_permission_tables(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
        $this->assertTrue(Schema::hasTable('role_has_permissions'));
    }

    public function test_roles_seeder_creates_exactly_one_super_admin_role_and_assigns_it_to_the_seeded_admin_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        config(['admin.seed_email' => 'admin@example.test']);

        (new RolesSeeder)->run();
        // Idempotent: running it a second time must not create a duplicate role.
        (new RolesSeeder)->run();

        $this->assertSame(1, Role::where('name', 'super_admin')->count());
        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_is_authorized_for_every_ability_on_profile_project_and_article(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $profile = $this->makeProfile($admin);
        $project = $this->makeProject();
        $article = $this->makeArticle();

        $this->assertTrue($admin->can('viewAny', Profile::class));
        $this->assertTrue($admin->can('view', $profile));
        $this->assertTrue($admin->can('update', $profile));
        $this->assertTrue($admin->can('viewAny', Project::class));
        $this->assertTrue($admin->can('update', $project));
        $this->assertTrue($admin->can('delete', $project));
        $this->assertTrue($admin->can('viewAny', Article::class));
        $this->assertTrue($admin->can('create', Article::class));
        $this->assertTrue($admin->can('update', $article));
        $this->assertTrue($admin->can('delete', $article));
    }

    public function test_a_user_without_super_admin_is_denied_every_ability_and_gate_authorize_throws(): void
    {
        $user = User::factory()->create(); // no role assigned

        $profile = $this->makeProfile($user);
        $project = $this->makeProject();
        $article = $this->makeArticle();

        $this->assertFalse($user->can('update', $profile));
        $this->assertFalse($user->can('update', $project));
        $this->assertFalse($user->can('update', $article));

        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);

        Gate::authorize('update', $project);
    }
}
