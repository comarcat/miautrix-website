<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Gates the admin interface only — the public /endorsements page reads the `approved` scope
 * directly, never through a Policy, and the public submission form creates a Testimonial
 * through its own Livewire component, not through this policy's `create()` (deliberately
 * denied here — see its own docblock). Single-admin site: every other ability requires
 * super_admin (mirrors ArticlePolicy / ThemePolicy).
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * E6-T1 (§9 step 44) — always false. A Testimonial only ever originates from the public
     * submission form (TestimonialForm, E6-T2), which writes directly via the model rather
     * than through Filament/this policy; nothing in the admin panel should be able to
     * fabricate one by hand.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->hasRole('super_admin');
    }
}
