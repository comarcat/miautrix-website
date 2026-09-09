<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Article;

/**
 * Found in production review: Article had no observer at all — unlike Project
 * (ProjectObserver), nothing ever invalidated /blog or /blog/{slug} when an article was
 * created, published, unpublished, edited, or deleted. Reported as articles disappearing
 * from the index when switching themes (each theme's cache entry was stale from a different
 * point in time) and published dates on the page not matching what was actually set (an
 * edited published_at kept serving the OLD cached page).
 *
 * Reacts to any change in `published_at` — not just a null↔non-null transition — because
 * Article gates public visibility on the DATE itself (Article::scopePublished():
 * `published_at <= now()`), and changing that date (even while it stays "published") changes
 * both the article's own displayed date and its sort position on the index.
 */
class ArticleObserver
{
    public function saved(Article $article): void
    {
        if ($article->wasChanged('published_at')) {
            app(InvalidatePublicPageCache::class)->forArticle($article);
        }
    }

    /**
     * A soft-deleted article is exactly as unpublished as one with published_at cleared —
     * its pages must stop being servable from a stale cache entry either way.
     */
    public function deleted(Article $article): void
    {
        app(InvalidatePublicPageCache::class)->forArticle($article);
    }
}
