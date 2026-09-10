<?php

namespace Tests\Feature\Phase2;

use App\Models\ShareClick;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E2-T1 (Phase 2, p2-step-09) — the `share_clicks` click log is append-only: `created_at`
 * only, no `updated_at`, no soft delete, and no FK to the subject it references.
 */
class ShareClickSchemaTest extends TestCase
{
    public function test_the_table_has_the_append_only_column_set(): void
    {
        $this->assertTrue(Schema::hasTable('share_clicks'));
        $this->assertTrue(Schema::hasColumns('share_clicks', [
            'network', 'type', 'subject_id', 'referrer', 'ip', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('share_clicks', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('share_clicks', 'deleted_at'));
    }

    public function test_a_row_inserts_through_the_model_without_an_updated_at(): void
    {
        $click = ShareClick::create([
            'network' => 'x',
            'type' => 'article',
            'subject_id' => 7,
            'referrer' => 'https://example.test/blog/some-post',
            'ip' => '203.0.113.9',
        ]);

        $this->assertDatabaseHas('share_clicks', [
            'id' => $click->id,
            'network' => 'x',
            'type' => 'article',
            'subject_id' => 7,
        ]);
        $this->assertFalse($click->timestamps);
        $this->assertNotNull($click->fresh()->created_at);
    }

    public function test_the_factory_produces_a_valid_row(): void
    {
        $click = ShareClick::factory()->create(['network' => 'reddit', 'type' => 'article', 'subject_id' => 3]);

        $this->assertDatabaseHas('share_clicks', [
            'id' => $click->id,
            'network' => 'reddit',
            'subject_id' => 3,
        ]);
        $this->assertInstanceOf(CarbonInterface::class, $click->created_at);
    }
}
