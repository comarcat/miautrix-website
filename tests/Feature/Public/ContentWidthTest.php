<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production report: several public pages' body copy used
 * `max-w-(--breakpoint-xs)` — a 375px MEDIA-QUERY breakpoint token (resources/css/app.css),
 * not a content-width design token — which pinned the hero, about bio, project summary, and
 * every blog article's body to phone width even on a full desktop viewport ("the space for
 * the content is like the space on a phone"). None of these pages should reference that
 * token any more; content should fill the same width the header/nav already use.
 */
class ContentWidthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_no_longer_pins_its_hero_copy_to_phone_width(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('breakpoint-xs');
    }

    public function test_the_contact_page_no_longer_pins_its_copy_and_form_to_phone_width(): void
    {
        $response = $this->get(route('contact'));

        $response->assertOk();
        $response->assertDontSee('breakpoint-xs');
    }

    public function test_the_about_page_no_longer_pins_the_bio_to_phone_width(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertDontSee('breakpoint-xs');
    }

    public function test_a_project_show_page_no_longer_pins_its_summary_to_phone_width(): void
    {
        $project = Project::factory()->create(['slug' => 'width-regression-project']);

        $response = $this->get(route('projects.show', $project->slug));

        $response->assertOk();
        $response->assertDontSee('breakpoint-xs');
    }

    public function test_a_blog_article_no_longer_pins_its_body_to_phone_width(): void
    {
        $article = Article::factory()->create(['slug' => 'width-regression-article']);

        $response = $this->get(route('blog.show', $article->slug));

        $response->assertOk();
        $response->assertDontSee('breakpoint-xs');
    }
}
