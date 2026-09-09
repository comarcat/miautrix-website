<?php

namespace App\Http\Controllers\Public;

use App\Models\SkillCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * E4-T4 (§9 step 22) — skills grouped by category. Skill has no `published` column
 * (E2-T2's schema), so there is nothing to filter on beyond the grouping itself.
 */
class SkillsController extends Controller
{
    public function index(): View
    {
        $skillCategories = SkillCategory::query()
            ->orderBy('sort_order')
            ->with(['skills' => fn ($query) => $query->orderBy('sort_order')->with('icon')])
            ->get();

        return view('public.skills', [
            'skillCategories' => $skillCategories,
        ]);
    }
}
