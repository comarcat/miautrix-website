<?php

namespace App\Contracts;

/**
 * Seam for a future analytics vendor (Non-Goal #12). Bound to NullAnalyticsProvider by
 * default — no cookie-consent surface is needed because nothing tracks visitors in v1. A
 * concrete analytics need is what would justify adding one, wired against this interface.
 */
interface AnalyticsProviderInterface
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $event, array $properties = []): void;
}
