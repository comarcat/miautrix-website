<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T2 (§9 step 26) — streams a published article as a PDF rendered from
 * resources/views/pdf/article.blade.php. Registered OUTSIDE cache.public — routes/web.php.
 * `published()` scope gates on published_at (null / future = 404), matching BlogController.
 */
class ArticlePdfController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $article = Article::query()->where('slug', $slug)->published()->first();

        if ($article === null) {
            throw new NotFoundHttpException;
        }

        return Pdf::loadView('pdf.article', ['article' => $article])
            ->download($article->slug . '.pdf');
    }
}
