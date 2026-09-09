<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic tests: no outbound HTTP, no real mail, no queue side effects
        // unless a test opts back in explicitly.
        Http::preventStrayRequests();

        // E5-T2's database page cache (CACHE_STORE=database) is state RefreshDatabase's
        // per-test transaction does not reliably protect against leaking between tests —
        // found via a real CI-only flake (never reproduced locally): CorePagesTest's home
        // page test intermittently saw a stale cached response from an earlier test's
        // request to the same 'cache.public' route, under one specific random test order.
        // Every test starts with a clean cache regardless of which other tests ran before it.
        Cache::flush();
    }

    // Kept from the livewire-starter-kit scaffold — the generated Auth/Settings tests
    // (E1-T1) call this to skip gracefully if a Fortify feature is toggled off.
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
