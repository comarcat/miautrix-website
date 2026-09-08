<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property int $company_id
 * @property string $title
 * @property string $description
 * @property Carbon $started_at
 * @property Carbon|null $ended_at null = current role
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
class Experience extends Model
{
    use HasAutoSlug, HasFactory, SoftDeletes;

    protected $fillable = [
        'profile_id',
        'company_id',
        'title',
        'description',
        'started_at',
        'ended_at',
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
            'started_at' => 'date',
            'ended_at' => 'date',
            'published' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
