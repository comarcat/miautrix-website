<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Project;

/**
 * E5-T2 (§9 step 26, acceptance 2) — invalidates a Project's detail-page cache entry the
 * instant `published` changes, whether that happens through Filament's admin UI or any other
 * write path (a model observer fires regardless of what triggered the save). Only reacts to
 * a genuine change in `published` — a content edit that leaves `published` untouched is left
 * for the cache's normal TTL expiry, not force-busted on every save.
 */
class ProjectObserver
{
    public function saved(Project $project): void
    {
        if ($project->wasChanged('published')) {
            app(InvalidatePublicPageCache::class)->forProject($project);
        }
    }

    /**
     * A soft-deleted project is exactly as unpublished as one with published=false — its
     * detail page must stop being servable from a stale cache entry either way.
     */
    public function deleted(Project $project): void
    {
        app(InvalidatePublicPageCache::class)->forProject($project);
    }
}
