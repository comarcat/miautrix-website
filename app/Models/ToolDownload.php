<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E5-T5, §9 step 39) — one row per download of a Tool (backlog item 13). Append-only,
 * same shape as ShareClick (E2-T1): `created_at` only, `$timestamps = false` so Eloquent never
 * writes an `updated_at` — the column doesn't exist.
 *
 * @property int $id
 * @property int $tool_id
 * @property string|null $ip
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property string|null $isp
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class ToolDownload extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tool_id',
        'ip',
        'country',
        'region',
        'city',
        'isp',
        'referrer',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
