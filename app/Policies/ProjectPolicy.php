<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Gates the admin interface only — public routes read published content directly via query
 * scopes, never through a Policy. Single-admin site: every ability requires super_admin (§9
 * step 11).
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole('super_admin');
    }
}
