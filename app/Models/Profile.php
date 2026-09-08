<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string $headline
 * @property string $bio
 * @property int|null $avatar_media_id
 * @property string|null $location
 * @property string|null $availability_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Profile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'headline',
        'bio',
        'avatar_media_id',
        'location',
        'availability_status',
    ];

    /**
     * The one administrator this profile belongs to — modeled as a table for consistency,
     * but a singleton in practice (§4).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
