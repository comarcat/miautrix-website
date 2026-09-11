<?php

namespace Tests\Feature\Phase2;

use App\Models\Company;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E6-T1 (Phase 2, p2-step-44) — testimonials: the expected column set, a default `pending`
 * status, and a deleted Company nulling company_id while keeping the testimonial.
 */
class TestimonialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_has_the_expected_shape(): void
    {
        $this->assertTrue(Schema::hasTable('testimonials'));
        $this->assertTrue(Schema::hasColumns('testimonials', [
            'name', 'organization', 'role', 'contact_type', 'contact_value', 'body',
            'status', 'approved_at', 'company_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_a_testimonial_inserted_without_a_status_defaults_to_pending(): void
    {
        $testimonial = Testimonial::create([
            'name' => 'Ada Lovelace',
            'contact_type' => 'email',
            'contact_value' => 'ada@example.test',
            'body' => 'Great work.',
        ]);

        $this->assertSame('pending', $testimonial->fresh()->status);
    }

    public function test_deleting_the_referenced_company_nulls_company_id_and_keeps_the_testimonial(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $testimonial = Testimonial::factory()->create(['company_id' => $company->id]);

        // Company is soft-deleting (SoftDeletes) — the DB-level nullOnDelete FK only fires
        // on a real row removal, so this exercises forceDelete(), same as Tool/ToolDownload's
        // own cascade test (E5-T5). A plain soft delete leaves the row (and therefore the FK
        // value) untouched, which is correct: the company isn't gone yet.
        $company->forceDelete();

        $this->assertNotNull($testimonial->fresh());
        $this->assertNull($testimonial->fresh()->company_id);
    }
}
