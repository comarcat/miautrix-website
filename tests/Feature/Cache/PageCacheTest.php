<?php

namespace Tests\Feature\Cache;

use App\Models\Article;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialProfile;
use App\Models\User;
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
        $this->assertTrue(Cache::has('public-page:technical:projects/toggle-project'));

        $project->update(['published' => false]);

        $this->assertFalse(Cache::has('public-page:technical:projects/toggle-project'));
        $this->get(route('projects.show', $project->slug))->assertNotFound();
    }

    public function test_the_cache_key_is_scoped_by_query_string_not_just_the_route(): void
    {
        $webCategory = ProjectCategory::create(['name' => 'Web', 'slug' => 'web', 'sort_order' => 0]);
        Project::factory()->create(['title' => 'Web Project', 'project_category_id' => $webCategory->id]);

        $this->get(route('projects.index'))->assertOk();
        $this->get(route('projects.index', ['category' => 'web']))->assertOk();

        $this->assertTrue(Cache::has('public-page:technical:projects'));
        $this->assertTrue(Cache::has('public-page:technical:projects?category=web'));
    }

    /**
     * Regression test for a real production bug: forProject() used to forget only the
     * project's own detail page, never the /projects index a visitor actually browses to
     * find it — a newly published project could sit missing from a stale cached index for
     * up to an hour.
     */
    public function test_publishing_a_new_project_invalidates_the_projects_index_cache_entry(): void
    {
        $this->get(route('projects.index'))->assertOk();
        $this->assertTrue(Cache::has('public-page:technical:projects'));

        $project = Project::factory()->create(['title' => 'Brand New Project', 'published' => false]);
        $project->update(['published' => true]);

        $this->assertFalse(Cache::has('public-page:technical:projects'));
        $this->get(route('projects.index'))->assertSee('Brand New Project');
    }

    /**
     * Regression test for a real production report: Article had no observer at all, so
     * neither publishing, editing the published date, nor deleting an article ever
     * invalidated /blog or /blog/{slug} — reported as articles disappearing from the index
     * when switching themes, and displayed dates not matching what was actually set.
     */
    public function test_publishing_a_new_article_invalidates_both_its_detail_and_the_blog_index_cache_entry(): void
    {
        $this->get(route('blog.index'))->assertOk();
        $this->assertTrue(Cache::has('public-page:technical:blog'));

        // A draft's show page 404s and is never cached (CachePublicPage only caches a 200) —
        // the assertion that matters is what happens to the index cache once it's published.
        $article = Article::factory()->draft()->create(['title' => 'Fresh Off The Press', 'slug' => 'fresh-off-the-press']);
        $this->get(route('blog.show', $article->slug))->assertNotFound();

        $article->update(['published_at' => now()]);

        $this->assertFalse(Cache::has('public-page:technical:blog'));
        $this->assertFalse(Cache::has('public-page:technical:blog/fresh-off-the-press'));
        $this->get(route('blog.index'))->assertSee('Fresh Off The Press');
        $this->get(route('blog.show', $article->slug))->assertOk();
    }

    public function test_editing_an_already_published_articles_date_invalidates_its_cache_entries(): void
    {
        $article = Article::factory()->create(['title' => 'Dated Article', 'slug' => 'dated-article', 'published_at' => '2026-01-01']);

        $first = $this->get(route('blog.show', $article->slug));
        $first->assertSee('January 1, 2026');
        $this->assertTrue(Cache::has('public-page:technical:blog/dated-article'));

        $article->update(['published_at' => '2026-03-15']);

        $this->assertFalse(Cache::has('public-page:technical:blog/dated-article'));
        $this->get(route('blog.show', $article->slug))->assertSee('March 15, 2026');
    }

    public function test_deleting_an_article_invalidates_its_cache_entries(): void
    {
        $article = Article::factory()->create(['title' => 'Doomed Article', 'slug' => 'doomed-article']);

        $this->get(route('blog.index'))->assertSee('Doomed Article');
        $this->get(route('blog.show', $article->slug))->assertOk();
        $this->assertTrue(Cache::has('public-page:technical:blog'));
        $this->assertTrue(Cache::has('public-page:technical:blog/doomed-article'));

        $article->delete();

        $this->assertFalse(Cache::has('public-page:technical:blog'));
        $this->assertFalse(Cache::has('public-page:technical:blog/doomed-article'));
        $this->get(route('blog.index'))->assertDontSee('Doomed Article');
    }

    /**
     * Regression test for a real production report: the footer's social links (and the
     * /connect page) are shared onto every public page via a layout-level composer, so
     * without an observer a SocialProfile save would sit invisible behind every one of
     * those pages' cached entries — not just home.
     */
    public function test_saving_a_social_profile_invalidates_every_static_public_page_cache_entry(): void
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);

        foreach (['home', 'about', 'experience', 'skills', 'resume', 'projects.index', 'connect', 'blog.index'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        foreach (['/', 'about', 'experience', 'skills', 'resume', 'projects', 'connect', 'blog'] as $key) {
            $this->assertTrue(Cache::has("public-page:technical:{$key}"));
        }

        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/example',
            'sort_order' => 0,
        ]);

        foreach (['/', 'about', 'experience', 'skills', 'resume', 'projects', 'connect', 'blog'] as $key) {
            $this->assertFalse(Cache::has("public-page:technical:{$key}"));
        }

        $this->get(route('connect'))->assertSee('LinkedIn');
    }

    /**
     * Regression test for a real production report: "changes that I am doing on the admin
     * part for [skills] are not being show[n] like a new Ubiquiti Skill under Networking
     * Category" — neither Skill nor SkillCategory had an observer at all, so /skills stayed
     * stuck on whatever was cached before the edit, same gap Article/Setting/SocialProfile
     * had before their own observers.
     */
    public function test_saving_a_skill_or_its_category_invalidates_the_skills_page_cache_entry(): void
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);
        $category = SkillCategory::create(['name' => 'Networking', 'sort_order' => 0]);

        $this->get(route('skills'))->assertOk();
        $this->assertTrue(Cache::has('public-page:technical:skills'));

        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'Ubiquiti',
            'proficiency' => 'advanced',
            'sort_order' => 0,
        ]);

        $this->assertFalse(Cache::has('public-page:technical:skills'));
        $this->get(route('skills'))->assertSee('Ubiquiti');

        $this->get(route('skills'))->assertOk();
        $this->assertTrue(Cache::has('public-page:technical:skills'));

        $category->update(['name' => 'Networking & Security']);

        $this->assertFalse(Cache::has('public-page:technical:skills'));
        $this->get(route('skills'))->assertSee('Networking & Security');
    }
}
