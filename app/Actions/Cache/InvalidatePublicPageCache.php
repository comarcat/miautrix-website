<?php

namespace App\Actions\Cache;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
    /**
     * E3-T6 — every hostname CachePublicPage::keyFor() may have keyed an entry under: the
     * canonical host and its `www.` sibling, plus the APP_URL host and its `www.` sibling
     * (they diverge in local/test — APP_URL is 127.0.0.1 while canonical_host is
     * miautrix.tech — and on staging APP_URL is the staging domain, so this covers it with
     * no extra env var). Extra forgets are harmless; a missed one leaves a stale page, so
     * the list errs wide.
     *
     * @return list<string>
     */
    private function hosts(): array
    {
        $seeds = [
            (string) config('site.canonical_host', 'miautrix.tech'),
            (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''),
        ];

        $hosts = [];
        foreach ($seeds as $seed) {
            if ($seed === '') {
                continue;
            }
            $bare = (string) Str::of($seed)->replaceMatches('/^www\./i', '');
            $hosts[] = $bare;
            $hosts[] = 'www.' . $bare;
        }

        return array_values(array_unique($hosts));
    }

    public function __invoke(string $routePath): void
    {
        $routePath = ltrim($routePath, '/');

        foreach ($this->hosts() as $host) {
            foreach (['technical', 'matrix'] as $theme) {
                Cache::forget("public-page:{$host}:{$theme}:{$routePath}");
            }
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

    /**
     * Phase 2 (E4-T8) — the theme-gated /life blog has its own index + detail entries,
     * distinct from forArticle()'s /blog ones. Called from ArticleObserver only when the
     * saved/deleted article's channel is 'life'.
     */
    public function forLife(Article $article): void
    {
        $this->__invoke('life/' . $article->slug);
        $this->__invoke('life');
    }

    /**
     * The home page reads a handful of Setting rows directly (home_hero_eyebrow/heading/
     * subheading) — any Setting save busts it, rather than checking which key changed,
     * since Settings are edited rarely enough that this isn't the "nuclear flush on every
     * save" this class otherwise avoids.
     *
     * Not routed through __invoke(): Request::path() returns the literal string '/' for the
     * root URL (the one path Laravel doesn't strip the leading slash from), so
     * CachePublicPage::keyFor() keys it as 'public-page:{host}:{theme}:/' — ltrim()-ing that
     * '/' the way every other route path needs would produce the wrong key (an empty path
     * segment).
     */
    public function forHome(): void
    {
        foreach ($this->hosts() as $host) {
            foreach (['technical', 'matrix'] as $theme) {
                Cache::forget("public-page:{$host}:{$theme}:/");
            }
        }
    }

    /**
     * The footer (and, for /connect, the full list) reads SocialProfile rows via a
     * layout-level View::composer, so it renders on every single cached public page — unlike
     * Project/Article, there's no single detail page to target. Rather than a true nuclear
     * flush, this forgets every statically-known path from the 'cache.public' route group
     * (§routes/web.php) plus home. The two {slug} detail pages (projects/*, blog/*) are left
     * alone: they already get busted on their own publish/unpublish via forProject()/
     * forArticle(), and enumerating every existing slug here just to catch a rarely-changed
     * footer link isn't worth the extra query on every SocialProfile save.
     */
    public function forSocialProfiles(): void
    {
        $this->forHome();

        foreach (['about', 'experience', 'skills', 'resume', 'projects', 'connect', 'blog'] as $routePath) {
            $this->__invoke($routePath);
        }
    }

    /**
     * Found in review: a new/edited Skill (or SkillCategory — renaming one changes what the
     * /skills page groups under) never showed up on the public page, because neither model
     * had an observer at all — the exact same gap Article/Setting/SocialProfile had before
     * their own observers were added.
     */
    public function forSkills(): void
    {
        $this->__invoke('skills');
    }

    /**
     * Phase 2 (E2-T8) — a SocialProfileGroup only affects the /connect page (its heading,
     * intro and ordering), never the footer, so a group save/delete busts just that one
     * entry rather than the whole forSocialProfiles() sweep.
     */
    public function forConnect(): void
    {
        $this->__invoke('connect');
    }

    /**
     * E3-T5 — a `themes` row save/delete can change how ANY public page renders (token
     * overrides, the active window, which row is default), so this is a deliberate full
     * sweep: every seeded theme × every known host (see hosts()) × every statically-known
     * public path (or a single `$path` when given). The two `{slug}` detail routes are left
     * to the normal TTL — same reasoning as forSocialProfiles().
     */
    public function forAllThemes(?string $path = null): void
    {
        $paths = $path !== null
            ? [($trimmed = ltrim($path, '/')) === '' ? '/' : $trimmed]
            : ['/', 'about', 'experience', 'skills', 'resume', 'projects', 'connect', 'blog'];

        foreach ($paths as $p) {
            foreach ($this->hosts() as $host) {
                foreach (['technical', 'matrix'] as $theme) {
                    Cache::forget("public-page:{$host}:{$theme}:{$p}");
                }
            }
        }
    }
}
