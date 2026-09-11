<?php

namespace App\Http\Controllers\Public;

use App\Models\Media;
use App\Models\Project;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T4 (§9 step 29) — streams a supplementary project file. Mirrors
 * DocumentDownloadController: 404 unless the owning project is published, 404 unless
 * `$media` is actually one of that project's `project_files` (not just any Media row id —
 * the two route segments bind independently). `{project}` is the slug, matching every other
 * public project URL (projects.show, projects.pdf) rather than the numeric id Media itself
 * binds by. `X-Content-Type-Options: nosniff` on top of the disk's own
 * `Content-Disposition: attachment` — an uploaded file's sniffed type is never trusted by
 * the browser as something to render inline.
 */
class ProjectFileDownloadController extends Controller
{
    public function __invoke(string $project, Media $media): Response
    {
        $project = Project::where('slug', $project)->first();

        if ($project === null || ! $project->published || ! $project->projectFiles()->where('media.id', $media->id)->exists()) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($media->disk);
        $path = 'uploads/' . $media->file_name;

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        return $disk->download($path, $media->file_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
