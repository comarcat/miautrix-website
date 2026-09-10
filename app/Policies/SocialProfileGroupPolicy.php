<?php

namespace App\Policies;

use App\Models\SocialProfileGroup;
use App\Models\User;

/**
 * Gates the admin interface only — the public /connect page reads groups directly via query
 * scopes, never through a Policy. Single-admin site: every ability requires super_admin
 * (mirrors ArticlePolicy / ProjectPolicy).
 */
class SocialProfileGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, SocialProfileGroup $group): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, SocialProfileGroup $group): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, SocialProfileGroup $group): bool
    {
        return $user->hasRole('super_admin');
    }
}
