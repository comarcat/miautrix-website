<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\SocialProfile;

/**
 * The footer's social links (and the /connect page's full list) are shared onto every public
 * page via a layout-level View::composer (AppServiceProvider::boot()) — without this observer,
 * toggling show_in_footer or adding a new profile in the admin panel would sit invisible behind
 * every cached page's TTL, exactly like the Setting/Article gaps found in earlier review passes.
 */
class SocialProfileObserver
{
    public function saved(SocialProfile $socialProfile): void
    {
        app(InvalidatePublicPageCache::class)->forSocialProfiles();
    }

    public function deleted(SocialProfile $socialProfile): void
    {
        app(InvalidatePublicPageCache::class)->forSocialProfiles();
    }
}
