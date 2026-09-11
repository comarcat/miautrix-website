<?php

namespace Tests\Feature\Phase2;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E6-T5 (Phase 2, p2-step-48) — TestimonialObserver busts the /endorsements cache entry on
 * every save (Approve/Reject included) or delete; approving a pending testimonial makes it
 * appear on the very next /endorsements request; a rejected one still never renders.
 */
class TestimonialCacheBustTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_testimonial_forgets_the_endorsements_cache_for_every_seeded_theme(): void
    {
        $testimonial = Testimonial::factory()->pending()->create();

        $keys = [
            'public-page:127.0.0.1:technical:endorsements',
            'public-page:127.0.0.1:matrix:endorsements',
            'public-page:miautrix.tech:technical:endorsements',
            'public-page:www.miautrix.tech:matrix:endorsements',
        ];
        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        $testimonial->update(['status' => Testimonial::STATUS_APPROVED, 'approved_at' => now()]);

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "{$key} not forgotten");
        }
    }

    public function test_approving_a_pending_testimonial_makes_it_appear_on_the_next_request(): void
    {
        $testimonial = Testimonial::factory()->pending()->create(['name' => 'Newly Approved']);

        $this->get(route('endorsements.index'))->assertDontSee('Newly Approved');

        $testimonial->update(['status' => Testimonial::STATUS_APPROVED, 'approved_at' => now()]);

        $this->get(route('endorsements.index'))->assertSee('Newly Approved');
    }

    public function test_a_rejected_testimonial_never_renders_on_endorsements(): void
    {
        $testimonial = Testimonial::factory()->pending()->create(['name' => 'Never Shown']);

        $testimonial->update(['status' => Testimonial::STATUS_REJECTED]);

        $this->get(route('endorsements.index'))->assertDontSee('Never Shown');
    }
}
