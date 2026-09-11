<?php

namespace App\Http\Controllers\Public;

use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Phase 2 (E6-T3, §9 step 46) — the public "Endorsements" page (backlog item 15). Reads only
 * the `approved` status directly — never through a Policy, matching every other public
 * controller's own query-scope convention. Professional area only: no theme gate, no
 * counterpart under /life (see routes/web.php's own comment).
 */
class EndorsementController extends Controller
{
    public function index(): View
    {
        $testimonials = Testimonial::query()
            ->where('status', Testimonial::STATUS_APPROVED)
            ->with('company')
            ->latest('approved_at')
            ->get();

        return view('public.endorsements', [
            'testimonials' => $testimonials,
        ]);
    }
}
