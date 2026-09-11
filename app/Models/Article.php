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
 * @property string $channel 'professional' (default) or 'life'
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Article extends Model
{
    use HasAutoSlug, HasFactory, SoftDeletes;

    /**
     * Phase 2 (E4-T7) — the only two channels a row may carry. `/feed.xml`
     * (BlogController@feed) and /life (E4-T8's LifeController) each read one.
     */
    public const CHANNEL_PROFESSIONAL = 'professional';

    public const CHANNEL_LIFE = 'life';

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
        'channel',
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

    /**
     * Phase 2 (E4-T7) — filter by channel. `professional()`/`life()` are the two shorthands
     * everything else (the feed, E4-T8's /life) actually calls.
     */
    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeProfessional(Builder $query): Builder
    {
        return $query->channel(self::CHANNEL_PROFESSIONAL);
    }

    public function scopeLife(Builder $query): Builder
    {
        return $query->channel(self::CHANNEL_LIFE);
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
