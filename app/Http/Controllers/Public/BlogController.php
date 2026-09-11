<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T6 (§9 step 24) — blog index (paginated, published only), detail, and the hand-rolled
 * RSS feed. No RSS package: feed() renders resources/views/feed.xml.blade.php (resolvable as
 * view('feed') via the 'xml.blade.php' extension registered in AppServiceProvider) and sets
 * the RSS content-type on the response itself rather than inside the view.
 */
class BlogController extends Controller
{
    public function index(): View
    {
        $articles = Article::query()
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('public.blog.index', [
            'articles' => $articles,
        ]);
    }

    public function show(string $slug): View
    {
        $article = Article::where('slug', $slug)->published()->first();

        if (! $article) {
            throw new NotFoundHttpException;
        }

        return view('public.blog.show', [
            'article' => $article,
        ]);
    }

    public function feed(): Response
    {
        // Phase 2 (E4-T7) — professional-only; the future /life blog (E4-T8) gets no feed of
        // its own (backlog item 11, confirmed with the user: "the life/gaming blog doesn't
        // need an RSS/Json feeds only professional needs that").
        $articles = Article::published()->professional()->latest('published_at')->get();

        return response(view('feed', ['articles' => $articles]))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
