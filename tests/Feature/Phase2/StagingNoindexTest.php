<?php

namespace Tests\Feature\Phase2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E1-T8 (Phase 2, p2-step-08) — staging.miautrix.tech is a full copy of production for the
 * sponsor to review before a production promote, so `SecurityHeaders` puts
 * `X-Robots-Tag: noindex, nofollow` on EVERY response when `app()->environment('staging')`.
 * Production and local are unchanged (the `/admin`-only X-Robots-Tag from Phase 1 still
 * applies there, and nowhere else).
 *
 * The environment is faked by rebinding the container's `env` before the request — the
 * middleware reads it through `app()->environment(...)`.
 */
class StagingNoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_route_is_noindex_on_staging(): void
    {
        $this->app['env'] = 'staging';
        config(['app.env' => 'staging']);

        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_a_public_route_has_no_staging_robots_tag_on_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $this->get('/')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }
}
