<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\SocialProfileGroup;

/**
 * Phase 2 (E2-T8) — the /connect page renders one section per SocialProfileGroup, keyed on
 * the group's `heading`, `intro_text` and `sort_order`. Without this observer, editing any of
 * those in the admin panel would sit invisible behind the cached page's TTL — the same gap
 * Article/Setting/SocialProfile each had before their observers were added.
 *
 * Narrower than SocialProfileObserver on purpose: a group never appears in the footer, so
 * only the /connect entry needs busting, not the whole forSocialProfiles() sweep.
 */
class SocialProfileGroupObserver
{
    public function saved(SocialProfileGroup $group): void
    {
        app(InvalidatePublicPageCache::class)->forConnect();
    }

    public function deleted(SocialProfileGroup $group): void
    {
        app(InvalidatePublicPageCache::class)->forConnect();
    }
}
