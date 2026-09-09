<?php

namespace App\Actions\Cache;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * E5-T2 (§9 step 26) — invalidates one cached public-page entry immediately. Called from a
 * Project model-event listener (registered in AppServiceProvider) on publish/unpublish/delete
 * — never on a bare content edit, since only published-state changes affect whether the page
 * is servable at all; every other field edit is content the next natural TTL expiry catches.
 *
 * Forgets both themes' cache entries (CachePublicPage::keyFor() keys by theme, since a cache
 * entry that ignored the visitor's theme cookie was a real production bug — see that class's
 * docblock) — invalidation has no way to know which theme any given cached visitor was on, so
 * it clears both rather than risk leaving a stale entry under the theme it didn't check.
 */
class InvalidatePublicPageCache
{
    public function __invoke(string $routePath): void
    {
        $routePath = ltrim($routePath, '/');

        foreach (['technical', 'matrix'] as $theme) {
            Cache::forget("public-page:{$theme}:{$routePath}");
        }
    }

    public function forProject(Project $project): void
    {
        $this->__invoke('projects/' . $project->slug);
    }
}
