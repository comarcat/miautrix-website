<?php

namespace App\Http\Controllers\Public;

use App\Models\Profile;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Requested in production review: "a new section to publish all social profiles there with
 * their logos near to the links" — every SocialProfile, not just the ones shown in the
 * footer (SocialProfile::show_in_footer only controls the footer's own, smaller list).
 */
class ConnectController extends Controller
{
    public function index(): View
    {
        $profile = Profile::query()->first();

        $socialProfiles = $profile
            ? $profile->socialProfiles()->orderBy('sort_order')->with('icon')->get()
            : collect();

        return view('public.connect', [
            'socialProfiles' => $socialProfiles,
        ]);
    }
}
