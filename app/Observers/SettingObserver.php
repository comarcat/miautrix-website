<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Setting;

/**
 * The home page now reads home_hero_eyebrow/heading/subheading directly from Settings
 * (found in review: "I should be able to change the text before the blog post from the
 * admin console") — without this, an edit through the admin panel would sit invisible
 * behind the home page's own cache entry for up to an hour.
 */
class SettingObserver
{
    public function saved(Setting $setting): void
    {
        app(InvalidatePublicPageCache::class)->forHome();
    }

    public function deleted(Setting $setting): void
    {
        app(InvalidatePublicPageCache::class)->forHome();
    }
}
