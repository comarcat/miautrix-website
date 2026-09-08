<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Blog. Standalone, single-author (the one admin), no FK beyond an implicit ownership (§4).
 *
 * @property int $id
 * @property string $title
 * @property string $excerpt
 * @property string $body
 * @property Carbon|null $published_at null = unpublished draft
 * @property bool $featured
 * @property string $slug
 * @property bool $published
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
class Article extends Model
{
    use HasAutoSlug, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'published_at',
        'featured',
        'slug',
        'published',
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
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'published' => 'boolean',
        ];
    }

    /**
     * Gates on published_at (not the `published` boolean) per E3-T6's own acceptance
     * criteria: null or future published_at excludes an article from public listings.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
