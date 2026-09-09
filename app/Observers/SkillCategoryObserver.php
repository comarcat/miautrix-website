<?php

namespace App\Observers;

use App\Actions\Cache\InvalidatePublicPageCache;
use App\Models\SkillCategory;

/**
 * Same reasoning as SkillObserver: renaming or deleting a category changes what /skills
 * shows just as much as editing a Skill itself does.
 */
class SkillCategoryObserver
{
    public function saved(SkillCategory $skillCategory): void
    {
        app(InvalidatePublicPageCache::class)->forSkills();
    }

    public function deleted(SkillCategory $skillCategory): void
    {
        app(InvalidatePublicPageCache::class)->forSkills();
    }
}
