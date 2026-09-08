<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $project_category_id
 * @property string $title
 * @property string $summary
 * @property string $description
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $repo_url
 * @property string|null $live_url
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
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_category_id',
        'title',
        'summary',
        'description',
        'started_at',
        'ended_at',
        'repo_url',
        'live_url',
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

    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class);
    }

    /**
     * 1:1 extension table, not single-table-inheritance (§4).
     */
    public function softwareProject(): HasOne
    {
        return $this->hasOne(SoftwareProject::class);
    }

    /**
     * project_technologies is a plain composite-PK pivot with no id/timestamps of its own,
     * hence the explicit table name (it doesn't match Eloquent's alphabetical-singular default)
     * and no withTimestamps().
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'project_technologies');
    }
}
