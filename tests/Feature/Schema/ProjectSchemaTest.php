<?php

namespace Tests\Feature\Schema;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SoftwareProject;
use App\Models\Technology;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E2-T3 — project schema: projects, project_categories, software_projects, and the 3 pivot
 * tables (blueprint §4).
 */
class ProjectSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): Project
    {
        return Project::create([
            'title' => 'Portfolio Platform',
            'summary' => 'A short summary.',
            'description' => 'A longer description.',
            'started_at' => '2024-01-01',
            'slug' => 'portfolio-platform',
        ]);
    }

    public function test_migrate_creates_projects_project_categories_software_projects_and_the_three_pivot_tables(): void
    {
        $this->assertTrue(Schema::hasTable('project_categories'));
        $this->assertTrue(Schema::hasColumns('project_categories', ['id', 'name', 'slug', 'sort_order', 'deleted_at']));

        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertTrue(Schema::hasColumns('projects', [
            'id', 'project_category_id', 'title', 'summary', 'description', 'started_at', 'ended_at',
            'repo_url', 'live_url', 'slug', 'published', 'featured', 'sort_order', 'og_image_id', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('software_projects'));
        $this->assertTrue(Schema::hasColumns('software_projects', [
            'id', 'project_id', 'language_primary', 'architecture_notes', 'deployment_notes', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('project_technologies'));
        $this->assertTrue(Schema::hasColumns('project_technologies', ['project_id', 'technology_id']));

        $this->assertTrue(Schema::hasTable('project_media'));
        $this->assertTrue(Schema::hasColumns('project_media', ['project_id', 'media_id', 'sort_order']));

        $this->assertTrue(Schema::hasTable('project_documents'));
        $this->assertTrue(Schema::hasColumns('project_documents', ['project_id', 'document_id']));
    }

    public function test_project_exposes_its_software_project_extension_via_has_one(): void
    {
        $project = $this->makeProject();

        $softwareProject = SoftwareProject::create([
            'project_id' => $project->id,
            'language_primary' => 'PHP',
        ]);

        $this->assertInstanceOf(SoftwareProject::class, $project->softwareProject);
        $this->assertTrue($project->softwareProject->is($softwareProject));
    }

    public function test_attaching_a_technology_to_a_project_enforces_the_composite_primary_key(): void
    {
        $project = $this->makeProject();
        $technology = Technology::create(['name' => 'Laravel']);

        $project->technologies()->attach($technology->id);

        $this->assertTrue($project->technologies()->where('technologies.id', $technology->id)->exists());

        $this->expectException(QueryException::class);

        // A raw duplicate insert against the composite PK — attach() itself is idempotent
        // (firstOrCreate-style) so it wouldn't trip the constraint on its own.
        DB::table('project_technologies')->insert([
            'project_id' => $project->id,
            'technology_id' => $technology->id,
        ]);
    }

    public function test_project_belongs_to_a_project_category(): void
    {
        $category = ProjectCategory::create(['name' => 'Web Apps', 'slug' => 'web-apps']);

        $project = Project::create([
            'project_category_id' => $category->id,
            'title' => 'Another Project',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'started_at' => '2023-01-01',
            'slug' => 'another-project',
        ]);

        $this->assertTrue($project->projectCategory->is($category));
        $this->assertTrue($category->projects->first()->is($project));
    }
}
