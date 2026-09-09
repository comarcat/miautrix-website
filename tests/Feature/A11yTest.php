<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTheme;
use App\Models\Article;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

/**
 * E5-T1 — a DomCrawler structural sweep over every published public route, in both themes
 * (§9 step 25, acceptance 4). Structural only (no computed styles/contrast — that needs a
 * real browser, out of reach of a DomCrawler-based Pest suite): html[lang], exactly one h1,
 * every img has a non-empty alt, every link/button has accessible text, every text-like form
 * input has an associated label.
 */
class A11yTest extends TestCase
{
    use RefreshDatabase;

    private function seedPublishedContent(): array
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Ada Lovelace',
            'headline' => 'Software Engineer',
            'bio' => 'Building reliable systems.',
        ]);
        $company = Company::create(['name' => 'Acme Corp']);
        Experience::create([
            'profile_id' => $profile->id,
            'company_id' => $company->id,
            'title' => 'Senior Engineer',
            'description' => 'A role.',
            'started_at' => '2020-01-01',
            'slug' => 'senior-engineer',
            'published' => true,
        ]);
        Education::create([
            'profile_id' => $profile->id,
            'institution' => 'State University',
            'degree' => 'B.Sc.',
            'field_of_study' => 'CS',
            'started_at' => '2014-09-01',
            'slug' => 'state-university-bsc',
            'published' => true,
        ]);
        $category = SkillCategory::create(['name' => 'Backend', 'sort_order' => 0]);
        Skill::create([
            'profile_id' => $profile->id,
            'skill_category_id' => $category->id,
            'name' => 'PHP',
            'proficiency' => 'expert',
            'sort_order' => 0,
        ]);
        $project = Project::factory()->create(['title' => 'A11y Project', 'slug' => 'a11y-project', 'featured' => true]);
        $article = Article::factory()->create(['title' => 'A11y Article', 'slug' => 'a11y-article']);

        return [$project, $article];
    }

    /**
     * @return list<string>
     */
    private function publishedRoutes(): array
    {
        [$project, $article] = $this->seedPublishedContent();

        return [
            route('home'),
            route('about'),
            route('experience'),
            route('skills'),
            route('projects.index'),
            route('projects.show', $project->slug),
            route('contact'),
            route('blog.index'),
            route('blog.show', $article->slug),
        ];
    }

    public function test_every_published_route_passes_the_structural_sweep_in_both_themes(): void
    {
        $routes = $this->publishedRoutes();

        foreach (['technical', 'matrix'] as $theme) {
            foreach ($routes as $url) {
                $request = $theme === 'matrix'
                    ? $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
                    : $this;

                $response = $request->get($url);
                $response->assertOk();

                $this->assertStructurallySound($response->getContent(), "{$url} (theme={$theme})");
            }
        }
    }

    private function assertStructurallySound(string $html, string $context): void
    {
        $crawler = new Crawler($html);

        $htmlLang = $crawler->filter('html')->attr('lang');
        $this->assertNotEmpty($htmlLang, "{$context}: <html> is missing a lang attribute.");

        $h1Count = $crawler->filter('h1')->count();
        $this->assertSame(1, $h1Count, "{$context}: expected exactly one <h1>, found {$h1Count}.");

        $crawler->filter('img')->each(function (Crawler $img) use ($context): void {
            $alt = $img->attr('alt');
            $this->assertNotNull($alt, "{$context}: an <img> is missing an alt attribute entirely.");
        });

        $crawler->filter('a, button')->each(function (Crawler $node) use ($context): void {
            $text = trim($node->text(''));
            $ariaLabel = trim((string) $node->attr('aria-label'));
            $this->assertTrue(
                $text !== '' || $ariaLabel !== '',
                "{$context}: a <{$node->nodeName()}> has no accessible text and no aria-label."
            );
        });

        $crawler->filter('input[type="text"], input[type="email"], input:not([type]), textarea')->each(function (Crawler $input) use ($crawler, $context): void {
            $id = $input->attr('id');
            $ariaLabel = $input->attr('aria-label');
            $hasLabel = $id && $crawler->filter('label[for="' . $id . '"]')->count() > 0;

            $this->assertTrue(
                $hasLabel || $ariaLabel,
                "{$context}: a text input/textarea has neither an associated <label for> nor an aria-label."
            );
        });
    }
}
