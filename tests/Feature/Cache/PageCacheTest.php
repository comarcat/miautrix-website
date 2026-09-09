<?php

namespace Tests\Feature\Cache;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E5-T2 — database-driven page cache (§9 step 26). Each test proves a cache HIT by mutating
 * the underlying row directly (bypassing whatever would normally bust the cache) and showing
 * the response still reflects the stale, cached snapshot — a cache-hit assertion, not a
 * timing one, per this task's own acceptance criterion 1.
 */
class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repeated_request_to_the_same_route_is_served_from_the_database_cache(): void
    {
        $project = Project::factory()->create([
            'title' => 'Original Title',
            'slug' => 'cached-project',
            'featured' => true,
        ]);

        $first = $this->get(route('projects.index'));
        $first->assertOk();
        $first->assertSee('Original Title');

        // Bypasses Eloquent events entirely (a raw query, not ->update()) — nothing here
        // could invalidate the cache even if something were listening for a title change,
        // which nothing is (only `published` toggles bust a cache entry, E5-T2 acceptance 2).
        Project::withoutEvents(fn () => $project->newQuery()->where('id', $project->id)->update(['title' => 'Mutated Title']));

        $second = $this->get(route('projects.index'));
        $second->assertOk();
        $second->assertSee('Original Title');
        $second->assertDontSee('Mutated Title');
    }

    public function test_publishing_or_unpublishing_a_project_invalidates_its_detail_page_cache_entry_immediately(): void
    {
        $project = Project::factory()->create(['title' => 'Toggle Project', 'slug' => 'toggle-project']);

        $this->get(route('projects.show', $project->slug))->assertOk();
        $this->assertTrue(Cache::has('public-page:projects/toggle-project'));

        $project->update(['published' => false]);

        $this->assertFalse(Cache::has('public-page:projects/toggle-project'));
        $this->get(route('projects.show', $project->slug))->assertNotFound();
    }

    public function test_the_cache_key_is_scoped_by_query_string_not_just_the_route(): void
    {
        $webCategory = ProjectCategory::create(['name' => 'Web', 'slug' => 'web', 'sort_order' => 0]);
        Project::factory()->create(['title' => 'Web Project', 'project_category_id' => $webCategory->id]);

        $this->get(route('projects.index'))->assertOk();
        $this->get(route('projects.index', ['category' => 'web']))->assertOk();

        $this->assertTrue(Cache::has('public-page:projects'));
        $this->assertTrue(Cache::has('public-page:projects?category=web'));
    }
}
