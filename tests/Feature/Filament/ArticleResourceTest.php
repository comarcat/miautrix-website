<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E3-T6 — blog admin: Filament Article resource (blueprint §9 step 18).
 */
class ArticleResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_article_resource_index_returns_200_for_the_authenticated_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(ArticleResource::getUrl('index'))
            ->assertOk();
    }

    public function test_creating_an_article_through_the_form_persists_a_non_null_slug_derived_from_title(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(CreateArticle::class)
            ->fillForm([
                'title' => 'How I Deployed This Site',
                'excerpt' => 'A short excerpt.',
                'body' => '<p>Some body text.</p>',
                // slug deliberately left blank — HasAutoSlug derives it from title.
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('title', 'How I Deployed This Site')->firstOrFail();
        $this->assertNotNull($article->slug);
        $this->assertSame('how-i-deployed-this-site', $article->slug);
    }

    public function test_the_published_toggle_sets_published_at_and_governs_the_published_scope(): void
    {
        $article = Article::create([
            'title' => 'A Draft',
            'excerpt' => 'Excerpt.',
            'body' => '<p>Body.</p>',
            'slug' => 'a-draft',
            'published_at' => null,
        ]);

        $this->assertFalse(Article::published()->whereKey($article->id)->exists());

        $article->update(['published_at' => now()->subMinute()]);

        $this->assertTrue(Article::published()->whereKey($article->id)->exists());

        $article->update(['published_at' => null]);

        $this->assertFalse(Article::published()->whereKey($article->id)->exists());
    }

    public function test_saving_the_rich_editor_body_strips_script_tags(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(CreateArticle::class)
            ->fillForm([
                'title' => 'A Malicious Post',
                'excerpt' => 'Excerpt.',
                'body' => '<p>Safe content.</p><script>alert("xss")</script>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('title', 'A Malicious Post')->firstOrFail();

        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('alert(', $article->body);
        $this->assertStringContainsString('Safe content.', $article->body);
    }
}
