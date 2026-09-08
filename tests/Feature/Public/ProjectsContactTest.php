<?php

namespace Tests\Feature\Public;

use App\Livewire\ContactForm;
use App\Mail\ContactMessageMail;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * E4-T5 — public projects index/detail + the Livewire contact form (§9 step 23). One test per
 * acceptance criterion (5), plus a happy-path contact-form test, per this task's own
 * acceptance criterion 6 (6 passing tests).
 */
class ProjectsContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_index_returns_200_paginated_and_filterable_by_category(): void
    {
        $webCategory = ProjectCategory::create(['name' => 'Web', 'slug' => 'web', 'sort_order' => 0]);
        $cliCategory = ProjectCategory::create(['name' => 'CLI', 'slug' => 'cli', 'sort_order' => 1]);

        Project::factory()->count(10)->create(['project_category_id' => $webCategory->id]);
        Project::factory()->create(['project_category_id' => $cliCategory->id, 'title' => 'The CLI Tool']);
        Project::factory()->create(['published' => false, 'title' => 'Hidden Project']);

        $response = $this->get(route('projects.index'));
        $response->assertOk();
        $response->assertDontSee('Hidden Project');
        // 10 web-category projects paginate at 9/page — the index must not dump all 10.
        $response->assertViewHas('projects', fn ($projects) => $projects->count() === 9 && $projects->hasMorePages());

        $filtered = $this->get(route('projects.index', ['category' => 'cli']));
        $filtered->assertOk();
        $filtered->assertSee('The CLI Tool');
        $filtered->assertViewHas('projects', fn ($projects) => $projects->total() === 1);
    }

    public function test_project_show_returns_200_for_a_published_project_with_its_technologies_media_and_documents(): void
    {
        $project = Project::factory()->create(['title' => 'Showcase Project', 'slug' => 'showcase-project']);

        $technology = Technology::create(['name' => 'Laravel']);
        $project->technologies()->attach($technology->id);

        $mediaId = DB::table('media')->insertGetId([
            'model_type' => Project::class,
            'model_id' => $project->id,
            'collection_name' => 'gallery',
            'name' => 'screenshot',
            'file_name' => 'screenshot.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'private-media',
            'size' => 1024,
            'manipulations' => '{}',
            'custom_properties' => '{}',
            'generated_conversions' => '{}',
            'responsive_images' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_media')->insert(['project_id' => $project->id, 'media_id' => $mediaId, 'sort_order' => 0]);

        $document = Document::create([
            'title' => 'Architecture Notes',
            'kind' => 'other',
            'version' => 1,
            'download_count' => 0,
            'published' => true,
        ]);
        DB::table('project_documents')->insert(['project_id' => $project->id, 'document_id' => $document->id]);

        $response = $this->get(route('projects.show', $project->slug));

        $response->assertOk();
        $response->assertSee('Showcase Project');
        $response->assertSee('Laravel');
        $response->assertSee('Architecture Notes');
        $response->assertViewHas('project', fn ($viewProject) => $viewProject->media->count() === 1
            && $viewProject->documents->count() === 1
            && $viewProject->technologies->count() === 1);
    }

    public function test_project_show_returns_404_for_an_unpublished_project(): void
    {
        $project = Project::factory()->create(['published' => false, 'slug' => 'unpublished-project']);

        $this->get(route('projects.show', $project->slug))->assertNotFound();
    }

    public function test_contact_form_sends_mail_on_a_valid_submission(): void
    {
        Mail::fake();

        Livewire::test(ContactForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.test')
            ->set('subject', 'Hello')
            ->set('message', 'This is a test message.')
            ->call('submit')
            ->assertHasNoErrors();

        Mail::assertSent(ContactMessageMail::class, fn ($mail) => $mail->senderEmail === 'ada@example.test');
    }

    public function test_contact_form_honeypot_silently_discards_the_submission_with_no_mail_sent(): void
    {
        Mail::fake();

        Livewire::test(ContactForm::class)
            ->set('name', 'A Bot')
            ->set('email', 'bot@example.test')
            ->set('subject', 'Buy now')
            ->set('message', 'Spam.')
            ->set('website_url_confirm', 'http://spam.example')
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_contact_form_rejects_the_6th_submission_from_the_same_ip_within_an_hour(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(ContactForm::class)
                ->set('name', 'Ada Lovelace')
                ->set('email', "ada{$i}@example.test")
                ->set('subject', 'Hello')
                ->set('message', 'This is a test message.')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('rateLimitMessage', null);
        }

        Mail::assertSentCount(5);

        Livewire::test(ContactForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada6@example.test')
            ->set('subject', 'Hello')
            ->set('message', 'This is a test message.')
            ->call('submit')
            ->assertSet('rateLimitMessage', fn ($message) => is_string($message) && $message !== '');

        Mail::assertSentCount(5);
    }
}
