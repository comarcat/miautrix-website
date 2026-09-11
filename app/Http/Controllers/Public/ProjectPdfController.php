<?php

namespace App\Http\Controllers\Public;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T2 (§9 step 26) — streams a published project as a PDF rendered from
 * resources/views/pdf/project.blade.php. Registered OUTSIDE cache.public (a binary body has
 * no business in the HTML page cache) — routes/web.php.
 */
class ProjectPdfController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $project = Project::query()
            ->where('slug', $slug)
            ->with(['technologies', 'softwareProject', 'projectCategory'])
            ->first();

        if ($project === null || ! $project->published) {
            throw new NotFoundHttpException;
        }

        return Pdf::loadView('pdf.project', ['project' => $project])
            ->download($project->slug . '.pdf');
    }
}
