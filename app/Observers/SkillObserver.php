<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\Skill;

/**
 * Found in review: a new Skill added in the admin panel (e.g. "Ubiquiti" under Networking)
 * never appeared on the public /skills page — that page had no observer at all busting its
 * cache entry, the same gap Article/Setting/SocialProfile had before their own observers.
 */
class SkillObserver
{
    public function saved(Skill $skill): void
    {
        app(InvalidatePublicPageCache::class)->forSkills();
    }

    public function deleted(Skill $skill): void
    {
        app(InvalidatePublicPageCache::class)->forSkills();
    }
}
