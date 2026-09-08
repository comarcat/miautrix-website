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
 * @property string $name
 * @property string $issuer
 * @property string|null $credential_id
 * @property string $credential_url
 * @property Carbon $issued_at
 * @property Carbon|null $expires_at
 * @property int|null $media_id badge image
 * @property string $slug
 * @property bool $published
 * @property bool $featured
 * @property int $sort_order
 * @property string|null $seo_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int|null $og_image_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Certification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'profile_id',
        'name',
        'issuer',
        'credential_id',
        'credential_url',
        'issued_at',
        'expires_at',
        'media_id',
        'slug',
        'published',
        'featured',
        'sort_order',
        'seo_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'published' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
