<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Tool;

/**
 * Phase 2 (E5-T6, §9 step 40) — busts the /tools index cache for every seeded theme × known
 * host on any save/delete. forAllThemes('tools') rather than a dedicated forTool() method:
 * the same full-sweep reasoning ThemeObserver already uses — a save can flip `published`,
 * reorder the list, or change which file downloads, any of which changes the index.
 */
class ToolObserver
{
    public function saved(Tool $tool): void
    {
        app(InvalidatePublicPageCache::class)->forAllThemes('tools');
    }

    public function deleted(Tool $tool): void
    {
        app(InvalidatePublicPageCache::class)->forAllThemes('tools');
    }
}
