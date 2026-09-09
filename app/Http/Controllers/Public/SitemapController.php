<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Routing\Controller;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * E5-T1 (§9 step 25) — one url entry per static page plus every published Project and
 * Article (acceptance 2). Built manually rather than via SitemapGenerator's link-crawler:
 * a crawl can miss a page nothing currently links to (or double-count one reachable by two
 * paths), which defeats the point of a sitemap as the authoritative list.
 */
class SitemapController extends Controller
{
    public function __invoke(): Responsable
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home'))->setPriority(1.0))
            ->add(Url::create(route('about'))->setPriority(0.8))
            ->add(Url::create(route('experience'))->setPriority(0.8))
            ->add(Url::create(route('skills'))->setPriority(0.8))
            ->add(Url::create(route('projects.index'))->setPriority(0.8))
            ->add(Url::create(route('resume'))->setPriority(0.7))
            ->add(Url::create(route('blog.index'))->setPriority(0.8))
            ->add(Url::create(route('contact'))->setPriority(0.5));

        Project::query()->where('published', true)->each(
            fn (Project $project) => $sitemap->add(
                Url::create(route('projects.show', $project->slug))
                    ->setLastModificationDate($project->updated_at)
                    ->setPriority(0.7),
            ),
        );

        Article::query()->published()->each(
            fn (Article $article) => $sitemap->add(
                Url::create(route('blog.show', $article->slug))
                    ->setLastModificationDate($article->updated_at)
                    ->setPriority(0.6),
            ),
        );

        return $sitemap;
    }
}
