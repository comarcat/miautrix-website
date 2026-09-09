<?php

namespace App\Actions\Cache;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * E5-T2 (§9 step 26) — invalidates one cached public-page entry immediately. Called from a
 * Project/Article model-event listener (registered in AppServiceProvider) on publish/
 * unpublish/delete — never on a bare content edit, since only published-state changes affect
 * whether the page is servable at all; every other field edit is content the next natural
 * TTL expiry catches.
 *
 * Forgets both themes' cache entries (CachePublicPage::keyFor() keys by theme, since a cache
 * entry that ignored the visitor's theme cookie was a real production bug — see that class's
 * docblock) — invalidation has no way to know which theme any given cached visitor was on, so
 * it clears both rather than risk leaving a stale entry under the theme it didn't check.
 *
 * BUG FIXED (found in production review): forProject() only ever forgot the project's OWN
 * detail page — never the /projects INDEX page a visitor actually browses to find it, so a
 * newly published (or newly unpublished) project could sit in a stale cached index list for
 * up to an hour. Same fix now applies to Article: it had NO observer at all, so neither a
 * new article, an edited published_at, nor a deletion ever invalidated /blog, /blog/{slug},
 * or the feed — reported as "articles disappear when I switch themes" (each theme's index
 * cache entry happened to be stale from a different point in time) and "the dates shown
 * aren't the real published date" (an edited published_at kept serving the OLD cached page).
 * Only the base /blog and /projects index entries (page 1, no filters) are busted — a deeper
 * paginated or category-filtered page is left to the normal TTL, same "not a nuclear flush"
 * reasoning as the rest of this class.
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
        $this->__invoke('projects');
    }

    public function forArticle(Article $article): void
    {
        $this->__invoke('blog/' . $article->slug);
        $this->__invoke('blog');
    }
}
