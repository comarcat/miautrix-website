<?php

namespace App\Http\Controllers\Public;

use App\Models\Profile;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * E4-T4 (§9 step 22) — profile bio + published experience/education summaries. Profile is a
 * singleton in practice (§4) — the site has exactly one administrator/owner.
 */
class AboutController extends Controller
{
    public function index(): View
    {
        $profile = Profile::query()->first();

        if (! $profile) {
            // No profile has been created yet — a styled 404 rather than a broken page
            // (blueprint §6's empty/error-state rule), since a site with no owner profile
            // has nothing coherent to show at /about at all.
            throw new NotFoundHttpException;
        }

        return view('public.about', [
            'profile' => $profile,
            'experiences' => $profile->experiences()->where('published', true)->with('company')->get(),
            'education' => $profile->education()->where('published', true)->get(),
        ]);
    }
}
