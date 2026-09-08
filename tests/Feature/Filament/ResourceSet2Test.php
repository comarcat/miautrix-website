<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProjectCategories\ProjectCategoryResource;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\SoftwareProjectRelationManager;
use App\Filament\Resources\SkillCategories\SkillCategoryResource;
use App\Filament\Resources\Skills\SkillResource;
use App\Filament\Resources\Technologies\TechnologyResource;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * E3-T3 — Filament resources set 2: Projects (+SoftwareProject relation manager), categories,
 * tech, skills (blueprint §9 step 15).
 */
class ResourceSet2Test extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
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

    public function test_software_project_resource_is_not_a_registered_filament_resource_class(): void
    {
        // The literal acceptance criterion: no such file anywhere under app/Filament/Resources.
        $finder = (new Finder)
            ->files()
            ->in(app_path('Filament/Resources'))
            ->name('SoftwareProjectResource.php');
        $this->assertCount(0, $finder);

        // And it's genuinely not part of the registered panel — SoftwareProject is only ever
        // reachable through ProjectResource's relation manager.
        $registeredResources = Filament::getPanel('admin')->getResources();
        $this->assertNotContains('App\\Filament\\Resources\\SoftwareProjectResource', $registeredResources);
    }

    public function test_project_resource_edit_page_renders_the_software_details_relation_manager_tab(): void
    {
        $admin = $this->superAdmin();
        $project = $this->makeProject();

        $response = $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $project]));

        $response->assertOk();
        $response->assertSee('Software Details');
        $this->assertContains(
            SoftwareProjectRelationManager::class,
            ProjectResource::getRelations(),
        );
    }

    public function test_attaching_technologies_to_a_project_through_the_form_persists_the_pivot_rows(): void
    {
        $admin = $this->superAdmin();
        $project = $this->makeProject();
        $laravel = Technology::create(['name' => 'Laravel']);
        $postgres = Technology::create(['name' => 'PostgreSQL']);

        Livewire::actingAs($admin)
            ->test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['technologies' => [$laravel->id, $postgres->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            [$laravel->id, $postgres->id],
            $project->technologies()->pluck('technologies.id')->sort()->values()->all(),
        );
    }

    public function test_project_resource_index_returns_200_for_the_authenticated_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(ProjectResource::getUrl('index'))
            ->assertOk();
    }

    public function test_the_remaining_four_resource_index_pages_return_200_for_the_authenticated_super_admin(): void
    {
        $admin = $this->superAdmin();

        foreach ([ProjectCategoryResource::class, TechnologyResource::class, SkillResource::class, SkillCategoryResource::class] as $resource) {
            $this->actingAs($admin)
                ->get($resource::getUrl('index'))
                ->assertOk();
        }
    }
}
