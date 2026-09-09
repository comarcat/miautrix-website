<?php

namespace App\Http\Controllers\Public;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Public /resume page — blueprint §4/§9 step 23 scoped this (route table line "| /resume |
 * Resume/documents | documents where kind=resume | public |"), but no controller, view, or
 * route for it ever shipped: DocumentResource (E3-T3) and the Documents CRUD in the admin
 * panel were built, and DatabaseSeeder even seeds a placeholder `kind=resume` Document row,
 * but nothing public ever read it. Found in review — an admin uploaded a real resume via
 * the admin panel's Documents section and had no way to see where a visitor would find or
 * download it, because there was nowhere.
 *
 * Kept deliberately simple to match this task's actual scope: the most recently published
 * `kind=resume` document, with a download button hitting the existing, already-working
 * documents.download route (§5's media-serving contract — publish-gated, streams the file,
 * increments download_count). An absent resume is an empty state (200 + an info alert), not
 * a 404 — same convention as every other "nothing published yet" section on this site
 * (e.g. public.home's "No projects published yet.").
 */
class ResumeController extends Controller
{
    public function index(): View
    {
        $resume = Document::query()
            ->where('kind', 'resume')
            ->where('published', true)
            ->latest('version')
            ->first();

        return view('public.resume', [
            'resume' => $resume,
        ]);
    }
}
