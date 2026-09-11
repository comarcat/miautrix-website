<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Support\Theming\ThemeResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T8 (§9 step 33) — the "Life / Gaming Life" blog. Entirely theme-gated: 404 unless the
 * ACTIVE theme (ThemeResolver, not the raw cookie value) has `shows_life_blog` true — the
 * seeded `matrix` theme does, `technical` does not (backlog item 11). No feed of its own
 * (confirmed with the user — professional-only, see BlogController@feed's own comment).
 */
class LifeController extends Controller
{
    public function index(Request $request): View
    {
        $this->abortUnlessThemeAllows($request);

        $articles = Article::query()
            ->life()
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('public.life.index', [
            'articles' => $articles,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $this->abortUnlessThemeAllows($request);

        $article = Article::where('slug', $slug)->life()->published()->first();

        if (! $article) {
            throw new NotFoundHttpException;
        }

        return view('public.life.show', [
            'article' => $article,
        ]);
    }

    private function abortUnlessThemeAllows(Request $request): void
    {
        $theme = app(ThemeResolver::class)->active($request);

        if (! $theme->shows_life_blog) {
            throw new NotFoundHttpException;
        }
    }
}
