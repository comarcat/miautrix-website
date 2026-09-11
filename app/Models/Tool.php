<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use App\Support\Github\RepoStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E5-T5, §9 step 39) — a downloadable tool (backlog item 13). `tool_file_media_id`
 * is this app's one-FK-column single-file "collection" convention (see the migration's own
 * docblock) — not Spatie's native media API. `gh_*` (E5-T7, backlog item 13.1) are a cached
 * snapshot of the repo_url's GitHub stats — never written directly, only through
 * RepoStats::for() (triggered by reading the repoStats accessor).
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
 * @property int|null $gh_stars
 * @property int|null $gh_forks
 * @property string|null $gh_language
 * @property string|null $gh_license
 * @property Carbon|null $gh_pushed_at
 * @property Carbon|null $gh_fetched_at
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
        'gh_stars',
        'gh_forks',
        'gh_language',
        'gh_license',
        'gh_pushed_at',
        'gh_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'gh_stars' => 'integer',
            'gh_forks' => 'integer',
            'gh_pushed_at' => 'datetime',
            'gh_fetched_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * Read-triggered refresh: reading this attribute calls RepoStats::for($this), which
     * fetches (subject to its own two staleness guards) and persists fresh gh_* values onto
     * this same instance before returning the snapshot below.
     *
     * @return Attribute<array{stars: int|null, forks: int|null, language: string|null, license: string|null, pushed_at: Carbon|null, fetched_at: Carbon|null}, never>
     */
    protected function repoStats(): Attribute
    {
        return Attribute::make(get: function (): array {
            app(RepoStats::class)->for($this);

            return [
                'stars' => $this->gh_stars,
                'forks' => $this->gh_forks,
                'language' => $this->gh_language,
                'license' => $this->gh_license,
                'pushed_at' => $this->gh_pushed_at,
                'fetched_at' => $this->gh_fetched_at,
            ];
        });
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
