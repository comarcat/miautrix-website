<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property int|null $group_id
 * @property string $platform
 * @property string $url
 * @property bool $show_in_footer
 * @property int|null $icon_media_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SocialProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'profile_id',
        'group_id',
        'platform',
        'url',
        'show_in_footer',
        'icon_media_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Phase 2 (E2-T6) — the /connect section this profile belongs to. Null = ungrouped.
     *
     * @return BelongsTo<SocialProfileGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SocialProfileGroup::class, 'group_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'icon_media_id');
    }
}
