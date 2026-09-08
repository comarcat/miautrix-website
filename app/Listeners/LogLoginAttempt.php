<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

/**
 * Writes every login success/failure to audit_logs (blueprint §9 step 10). Auto-discovered by
 * Laravel's event system — each public handle* method's first parameter type is the event it
 * listens to.
 *
 * The 6th-attempt-in-15-minutes 429 case is deliberately NOT logged here: Laravel's
 * `ThrottleRequests` middleware (wrapping POST /login via `throttle:login`) blocks that
 * request before it ever reaches the guard, so no Failed event fires for it. That case is
 * logged directly from the Limit::response() callback in FortifyServiceProvider instead,
 * right where the 429 itself is produced.
 */
class LogLoginAttempt
{
    public function handleSuccess(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login_success',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'ip_address' => Request::ip(),
        ]);
    }

    public function handleFailure(Failed $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        // Wrong email entirely resolves to no user — 0 is a sentinel "no matching user" id,
        // not a real row (users.id starts at 1 via bigserial). auditable_id is NOT NULL (§4).
        $userId = $user?->id;

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'login_failed',
            'auditable_type' => User::class,
            'auditable_id' => $userId ?? 0,
            'ip_address' => Request::ip(),
        ]);
    }
}
