<?php

namespace Tests\Feature\Phase2;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-T1 (Phase 2, p2-step-26) — the two Dompdf print views render to self-contained HTML:
 * one inline <style> block and not a single external <link> or <script> (Dompdf fetches
 * nothing, so anything external would silently drop).
 */
class PdfViewsRenderTest extends TestCase
{
    use RefreshDatabase;

    private function assertSelfContained(string $html): void
    {
        $this->assertStringContainsString('<style>', $html);
        $this->assertDoesNotMatchRegularExpression('/<link\b/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<script\b/i', $html);
        $this->assertStringNotContainsString('@vite', $html);
    }

    public function test_the_project_print_view_is_self_contained(): void
    {
        $project = Project::factory()->create(['title' => 'Print Me', 'slug' => 'print-me']);

        $html = view('pdf.project', ['project' => $project->fresh()])->render();

        $this->assertStringContainsString('Print Me', $html);
        $this->assertSelfContained($html);
    }

    public function test_the_article_print_view_is_self_contained(): void
    {
        $article = Article::factory()->create(['title' => 'Printable Article', 'slug' => 'printable-article']);

        $html = view('pdf.article', ['article' => $article->fresh()])->render();

        $this->assertStringContainsString('Printable Article', $html);
        $this->assertSelfContained($html);
    }
}
