<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Gates the admin interface only — public routes read published content directly via query
 * scopes, never through a Policy. Single-admin site: every ability requires super_admin (§9
 * step 11).
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin');
    }
}
