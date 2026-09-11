<?php

namespace App\Policies;

use App\Models\Theme;
use App\Models\User;

/**
 * Gates the admin interface only — the public switcher and ThemeResolver read `themes`
 * directly via query scopes, never through a Policy. Single-admin site: every ability
 * requires super_admin (mirrors ArticlePolicy / SocialProfileGroupPolicy).
 */
class ThemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Theme $theme): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Theme $theme): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Theme $theme): bool
    {
        return $user->hasRole('super_admin');
    }
}
