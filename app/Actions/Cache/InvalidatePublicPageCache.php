<?php

namespace App\Actions\Cache;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * E5-T2 (§9 step 26) — invalidates one cached public-page entry immediately. Called from a
 * Project model-event listener (registered in AppServiceProvider) on publish/unpublish/delete
 * — never on a bare content edit, since only published-state changes affect whether the page
 * is servable at all; every other field edit is content the next natural TTL expiry catches.
 */
class InvalidatePublicPageCache
{
    public function __invoke(string $routePath): void
    {
        Cache::forget('public-page:' . ltrim($routePath, '/'));
    }

    public function forProject(Project $project): void
    {
        $this->__invoke('projects/' . $project->slug);
    }
}
