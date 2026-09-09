<?php

namespace App\Http\Controllers\Public;

use App\Models\Experience;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * E4-T4 (§9 step 22) — published experiences only, ordered by started_at desc.
 */
class ExperienceController extends Controller
{
    public function index(): View
    {
        $experiences = Experience::query()
            ->where('published', true)
            ->orderByDesc('started_at')
            ->with('company.logo')
            ->get();

        return view('public.experience', [
            'experiences' => $experiences,
        ]);
    }
}
