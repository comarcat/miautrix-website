<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E4-T2 (Phase 2, p2-step-27) — `/projects/{slug}/pdf` and `/blog/{slug}/pdf` stream a real
 * PDF for a published entity, 404 an unpublished one, and sit outside the page cache.
 */
class PdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_project_downloads_as_a_pdf(): void
    {
        $project = Project::factory()->create(['slug' => 'shipping-pipeline', 'published' => true]);

        $response = $this->get(route('projects.pdf', $project->slug));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_a_published_article_downloads_as_a_pdf(): void
    {
        $article = Article::factory()->create(['slug' => 'my-post']);

        $response = $this->get(route('blog.pdf', $article->slug));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_an_unpublished_project_pdf_is_404(): void
    {
        $project = Project::factory()->create(['slug' => 'wip', 'published' => false]);

        $this->get(route('projects.pdf', $project->slug))->assertNotFound();
    }

    public function test_a_draft_article_pdf_is_404(): void
    {
        $article = Article::factory()->draft()->create(['slug' => 'draft-post']);

        $this->get(route('blog.pdf', $article->slug))->assertNotFound();
    }

    public function test_neither_pdf_route_is_cached_and_both_are_linked_from_their_show_pages(): void
    {
        foreach (['projects.pdf', 'blog.pdf'] as $name) {
            $this->assertNotContains('cache.public', Route::getRoutes()->getByName($name)->middleware());
        }

        $project = Project::factory()->create(['slug' => 'linked-project', 'published' => true]);
        $article = Article::factory()->create(['slug' => 'linked-article']);

        $this->get(route('projects.show', $project->slug))
            ->assertSee(route('projects.pdf', $project->slug), false)
            ->assertSee('Download PDF');
        $this->get(route('blog.show', $article->slug))
            ->assertSee(route('blog.pdf', $article->slug), false)
            ->assertSee('Download PDF');
    }
}
