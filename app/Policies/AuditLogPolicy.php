<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * audit_logs is append-only (§4/§9 step 17) — every mutating ability is denied
 * unconditionally, for every user including super_admin. Backs AuditLogResource's own
 * hidden create/edit/delete UI with a real Gate-level denial, and is what
 * AuditLog::booted()'s updating()/deleting() guards (E2-T4) exist as the last line of
 * defense behind, not the only one.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
