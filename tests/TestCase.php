<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
