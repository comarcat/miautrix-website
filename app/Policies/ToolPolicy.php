<?php

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;

/**
 * Gates the admin interface only — the public /tools pages read Tool rows directly via
 * query scopes, never through a Policy. Single-admin site: every ability requires
 * super_admin (mirrors ArticlePolicy / ThemePolicy).
 */
class ToolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Tool $tool): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Tool $tool): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Tool $tool): bool
    {
        return $user->hasRole('super_admin');
    }
}
