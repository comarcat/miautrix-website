<?php

namespace Tests\Feature\Phase2;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * E1-T4 (Phase 2, p2-step-04) — the 503 maintenance view derives its countdown target from
 * the `Retry-After` header Laravel's maintenance middleware attaches when the site is taken
 * down with `php artisan down --retry=N`. The exception handler passes that HttpException to
 * the view as `$exception`; these tests render the view directly with a fabricated one so the
 * ETA wiring is exercised without actually toggling maintenance mode.
 *
 * Rendered both ways: with a retry value (a real ISO-8601 countdown target) and without one
 * (a static line and NO `data-countdown` element, so the client never initialises a broken
 * timer).
 */
class MaintenancePageTest extends TestCase
{
    public function test_a_retry_header_becomes_an_iso8601_countdown_target(): void
    {
        $html = view('errors.503', [
            'exception' => new HttpException(503, 'Service Unavailable', null, ['Retry-After' => 1800]),
        ])->render();

        $this->assertStringContainsString('We&rsquo;ll be back soon', $html);
        $this->assertMatchesRegularExpression('/data-countdown="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9:+\-]+"/', $html);

        preg_match('/data-countdown="([^"]+)"/', $html, $m);
        $target = Carbon::parse($m[1]);

        // ~1800s from now, generous window for a slow test run.
        $this->assertEqualsWithDelta(1800, Carbon::now()->diffInSeconds($target, false), 60);
    }

    public function test_no_retry_value_renders_a_static_line_and_no_countdown_element(): void
    {
        $html = view('errors.503', [
            'exception' => new HttpException(503, 'Service Unavailable'),
        ])->render();

        $this->assertStringNotContainsString('data-countdown', $html);
        $this->assertStringContainsString('back shortly', $html);
    }

    public function test_an_explicit_eta_override_sets_the_countdown_target(): void
    {
        $eta = Carbon::now()->addMinutes(45);

        $html = view('errors.503', ['eta' => $eta])->render();

        preg_match('/data-countdown="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null);
        $this->assertEqualsWithDelta(
            $eta->getTimestamp(),
            Carbon::parse($m[1])->getTimestamp(),
            60,
        );
    }
}
