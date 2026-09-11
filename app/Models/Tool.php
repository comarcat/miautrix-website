<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E5-T5, §9 step 39) — a downloadable tool (backlog item 13). `gh_*` columns land
 * in E5-T7. `tool_file_media_id` is this app's one-FK-column single-file "collection"
 * convention (see the migration's own docblock) — not Spatie's native media API.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string|null $description
 * @property string|null $version
 * @property bool $published
 * @property string|null $repo_url
 * @property int $sort_order
 * @property int|null $tool_file_media_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Tool extends Model
{
    use HasAutoSlug, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'version',
        'published',
        'repo_url',
        'sort_order',
        'tool_file_media_id',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function toolFile(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'tool_file_media_id');
    }

    /**
     * @return HasMany<ToolDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(ToolDownload::class);
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
