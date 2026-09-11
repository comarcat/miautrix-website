<?php

namespace Tests\Feature\Phase2;

use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E6-T4 (Phase 2, p2-step-47) — TestimonialResource: Approve sets status=approved +
 * approved_at=now(), Reject sets status=rejected and leaves approved_at untouched, the
 * table filters by status, and creating from the panel is denied outright.
 */
class TestimonialResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_the_approve_action_sets_status_and_approved_at(): void
    {
        $testimonial = Testimonial::factory()->pending()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(ListTestimonials::class)
            ->callTableAction('approve', $testimonial);

        $testimonial->refresh();
        $this->assertSame('approved', $testimonial->status);
        $this->assertNotNull($testimonial->approved_at);
    }

    public function test_the_reject_action_sets_status_and_leaves_approved_at_null(): void
    {
        $testimonial = Testimonial::factory()->pending()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(ListTestimonials::class)
            ->callTableAction('reject', $testimonial);

        $testimonial->refresh();
        $this->assertSame('rejected', $testimonial->status);
        $this->assertNull($testimonial->approved_at);
    }

    public function test_the_table_filters_by_status(): void
    {
        Testimonial::factory()->pending()->create(['name' => 'Pending One']);
        Testimonial::factory()->approved()->create(['name' => 'Approved One']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListTestimonials::class)
            ->filterTable('status', 'approved')
            ->assertCanSeeTableRecords(Testimonial::where('status', 'approved')->get())
            ->assertCanNotSeeTableRecords(Testimonial::where('status', 'pending')->get());
    }

    public function test_creating_a_testimonial_from_the_panel_is_denied(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(TestimonialResource::getUrl('create'))
            ->assertForbidden();
    }
}
