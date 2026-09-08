<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Models\Project;
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
        ]);
    }
}
