<?php

namespace App\View\Components;

use App\Models\Profile;
use App\Models\SocialProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

/**
 * Found in review: "add to the social profiles, a field to check if it should appear on the
 * footer of the website" — the footer needs this on every single public page, and there's no
 * controller step common to all of them to pass it through as a view variable.
 *
 * BUG FIXED: a View::composer('layouts.app', ...) was tried first, on the assumption that
 * <x-layouts::app> resolves to a view literally named 'layouts.app' (Laravel's normal
 * component-tag-to-view-path convention). It doesn't: Blade resolves an anonymous component
 * living outside resources/views/components into a per-boot HASHED namespace (verified via
 * View::composer('*', ...) — the actual rendered name was "{64-char-hash}::app"), so a
 * composer registered under the literal string 'layouts.app' never fired, and the footer
 * silently stayed empty on every request. A class-based component sidesteps the whole
 * name-resolution question — <x-footer-social-profiles /> always resolves straight to this
 * class regardless of what hashed name the surrounding layout itself got.
 */
class FooterSocialProfiles extends Component
{
    /** @var Collection<int, SocialProfile> */
    public Collection $socialProfiles;

    public function __construct()
    {
        $this->socialProfiles = Profile::query()->first()?->socialProfiles()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->with('icon')
            ->get() ?? new Collection;
    }

    public function render()
    {
        return view('components.footer-social-profiles');
    }
}
