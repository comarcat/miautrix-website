<?php

namespace App\Support\Analytics;

use App\Contracts\AnalyticsProviderInterface;

/**
 * Default binding for AnalyticsProviderInterface — a no-op. Nothing tracks visitors in v1
 * (Non-Goal #12), so no cookie-consent surface is needed either.
 */
class NullAnalyticsProvider implements AnalyticsProviderInterface
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $event, array $properties = []): void
    {
        // intentionally a no-op
    }
}
