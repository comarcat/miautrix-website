<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Testimonial;

/**
 * Phase 2 (E6-T5, §9 step 48) — busts the /endorsements cache entry on every save (Approve/
 * Reject included, since both are plain `$record->update()` calls) or delete. See
 * InvalidatePublicPageCache::forEndorsements()'s own docblock for why this is currently a
 * defensive no-op against a route that isn't cached at all.
 */
class TestimonialObserver
{
    public function saved(Testimonial $testimonial): void
    {
        app(InvalidatePublicPageCache::class)->forEndorsements();
    }

    public function deleted(Testimonial $testimonial): void
    {
        app(InvalidatePublicPageCache::class)->forEndorsements();
    }
}
