<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialProfile;
use App\Models\SocialProfileGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Requested in production review: "a new section to publish all social profiles there with
 * their logos near to the links" — every SocialProfile, not just the ones shown in the
 * footer (SocialProfile::show_in_footer only controls the footer's own, smaller list).
 *
 * Phase 2 (E2-T8): the page is now organised into admin-managed groups
 * (SocialProfileGroup, ordered by sort_order), each with its own heading and intro copy.
 * Profiles with no group_id fall into a trailing "Other" section.
 */
class ConnectController extends Controller
{
    public function index(): View
    {
        $groups = SocialProfileGroup::query()
            ->orderBy('sort_order')
            ->with(['socialProfiles' => fn ($query) => $query->orderBy('sort_order')->with('icon')])
            ->get();

        $ungrouped = SocialProfile::query()
            ->whereNull('group_id')
            ->orderBy('sort_order')
            ->with('icon')
            ->get();

        return view('public.connect', [
            'groups' => $groups,
            'ungrouped' => $ungrouped,
        ]);
    }
}
