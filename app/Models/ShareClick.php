<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E2-T1) — one row per click on a blog share control, written by
 * `ShareRedirectController` before it 302s to the real network URL.
 *
 * Append-only (see the migration): `created_at` only. `$timestamps = false` so Eloquent
 * never writes an `updated_at` — the column does not exist — and the DB fills `created_at`
 * via its `useCurrent()` default when the factory / caller does not set it explicitly.
 *
 * @property int $id
 * @property string $network
 * @property string $type
 * @property int $subject_id
 * @property string|null $referrer
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
class ShareClick extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'network',
        'type',
        'subject_id',
        'referrer',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
