<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * E4-T4 (§9 step 22) — featured projects + the 3 latest published articles.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        $featuredProjects = Project::query()
            ->where('published', true)
            ->where('featured', true)
            ->orderBy('sort_order')
            ->with('projectCategory')
            ->get();

        $latestArticles = Article::query()
            ->published()
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('public.home', [
            'featuredProjects' => $featuredProjects,
            'latestArticles' => $latestArticles,
            // Found in review: "I should be able to change the text before the blog post
            // from the admin console" — the hero eyebrow/heading/subheading were hardcoded
            // in the Blade view. They now come from the Settings resource (a plain
            // key/value store already built for exactly this), falling back to the
            // original copy when nothing's been set yet — no migration or seed needed for
            // this to work on an existing site.
            'heroEyebrow' => Setting::get('home_hero_eyebrow', 'Portfolio · Blog · CMS'),
            'heroHeading' => Setting::get('home_hero_heading', 'Building reliable systems, end to end.'),
            'heroSubheading' => Setting::get(
                'home_hero_subheading',
                'A professional IT portfolio covering backend architecture, infrastructure, and the projects behind it.',
            ),
        ]);
    }
}
