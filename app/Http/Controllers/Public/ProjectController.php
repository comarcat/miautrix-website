<?php

namespace App\Http\Controllers\Public;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T5 (§9 step 23) — projects index (paginated, filterable by category) and detail.
 */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $query = Project::query()
            ->where('published', true)
            ->with('projectCategory')
            ->orderByDesc('featured')
            ->orderBy('sort_order');

        $categorySlug = $request->query('category');
        $activeCategory = null;

        if ($categorySlug) {
            $activeCategory = ProjectCategory::where('slug', $categorySlug)->first();

            // An unknown ?category= value narrows to "nothing matches" rather than being
            // silently ignored — the alternative (falling back to the unfiltered list) would
            // make a stale or mistyped filter link look like it worked when it didn't.
            $query->where('project_category_id', $activeCategory->id ?? 0);
        }

        return view('public.projects.index', [
            'projects' => $query->paginate(9)->withQueryString(),
            'categories' => ProjectCategory::orderBy('sort_order')->get(),
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(string $slug): View
    {
        $project = Project::where('slug', $slug)
            ->with(['technologies', 'media', 'documents', 'softwareProject', 'projectCategory'])
            ->first();

        if (! $project || ! $project->published) {
            throw new NotFoundHttpException;
        }

        return view('public.projects.show', [
            'project' => $project,
        ]);
    }
}
