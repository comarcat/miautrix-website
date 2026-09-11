<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E5-T8, §9 step 42) — one row per recorded page view (backlog item 14), written
 * only by RecordPageView while site.analytics.record_page_views is on. Append-only:
 * `created_at` only, `$timestamps = false` so Eloquent never writes an `updated_at` — the
 * column doesn't exist.
 *
 * @property int $id
 * @property string $path
 * @property string|null $referrer_host
 * @property string|null $country
 * @property string|null $device 'desktop'|'mobile'|'bot', coarse from the UA
 * @property string $channel 'professional'|'life'|'other'
 * @property Carbon|null $created_at
 */
class PageView extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'path',
        'referrer_host',
        'country',
        'device',
        'channel',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
