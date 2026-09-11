<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Theme;

/**
 * E3-T5 (§9 step 21) — a `themes` row save or delete can change how ANY public page renders:
 * its token overrides, whether it is inside its active window, or which row is `is_default`
 * (the `saving` hook may have demoted another row too). So this triggers a full sweep of the
 * public-page cache — every seeded theme × every known host × every statically-known path —
 * rather than trying to reason about which entry moved.
 */
class ThemeObserver
{
    public function saved(Theme $theme): void
    {
        app(InvalidatePublicPageCache::class)->forAllThemes();
    }

    public function deleted(Theme $theme): void
    {
        app(InvalidatePublicPageCache::class)->forAllThemes();
    }
}
