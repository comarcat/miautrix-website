<?php

namespace Tests\Feature\Phase2;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E6-T3 (Phase 2, p2-step-46) — /endorsements renders only approved testimonials (each with
 * name/organization/role + a linked contact) plus the submission form; pending/rejected
 * testimonials are never rendered publicly; the route exists only in the professional area
 * (no /life/endorsements).
 */
class EndorsementsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_only_approved_testimonials_with_contact_and_the_form(): void
    {
        $approved = Testimonial::factory()->approved()->create([
            'name' => 'Ada Lovelace',
            'organization' => 'Acme Corp',
            'role' => 'CTO',
            'contact_type' => 'email',
            'contact_value' => 'ada@example.test',
            'body' => 'Superb engineer.',
        ]);

        $response = $this->get(route('endorsements.index'));

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
        $response->assertSee('Acme Corp');
        $response->assertSee('CTO');
        $response->assertSee('Superb engineer.');
        $response->assertSee('mailto:ada@example.test', false);
        // The submission form is mounted on the page.
        $response->assertSee('testimonial_contact_type', false);
    }

    public function test_pending_and_rejected_testimonials_are_never_rendered(): void
    {
        Testimonial::factory()->pending()->create(['name' => 'Pending Person']);
        Testimonial::factory()->rejected()->create(['name' => 'Rejected Person']);

        $response = $this->get(route('endorsements.index'));

        $response->assertOk();
        $response->assertDontSee('Pending Person');
        $response->assertDontSee('Rejected Person');
    }

    public function test_the_route_exists_only_in_the_professional_area_with_no_life_counterpart(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('endorsements.index'));

        $lifeRoute = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'life/endorsements' || $route->getName() === 'life.endorsements',
        );
        $this->assertNull($lifeRoute, 'a /life/endorsements route must never exist');
    }

    public function test_the_page_shows_an_empty_state_and_still_mounts_the_form_when_no_testimonials_exist(): void
    {
        $response = $this->get(route('endorsements.index'));

        $response->assertOk();
        $response->assertSee('No endorsements published yet.');
        $response->assertSee('testimonial_contact_type', false);
    }
}
