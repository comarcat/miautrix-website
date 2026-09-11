<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property float|null $budget_planned
 * @property float|null $budget_actual
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property int|null $team_size
 * @property string|null $role
 * @property string|null $outcome
 * @property-read float|null $schedulePerformancePct computed, never stored — null unless
 *   all four schedule dates are set
 * @property-read float|null $budgetPerformancePct computed, never stored — null unless both
 *   budgets are set
 * @property-read bool|null $isOnTime computed — null unless planned_end and actual_end are
 *   both set
 * @property-read bool|null $isOnBudget computed — null unless both budgets are set
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Project extends Model
{
    use HasAutoSlug, HasFactory, SoftDeletes;

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
        'budget_planned',
        'budget_actual',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'team_size',
        'role',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'published' => 'boolean',
            'featured' => 'boolean',
            'budget_planned' => 'decimal:2',
            'budget_actual' => 'decimal:2',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
            'team_size' => 'integer',
        ];
    }

    /**
     * Phase 2 (E4-T5) — planned duration ÷ actual duration × 100. Null unless all four
     * schedule dates are set, or the actual duration is zero days (nothing to divide by).
     * Computed on read, never stored — editing a date changes the number immediately, with
     * no stale cached percentage to go out of sync.
     */
    protected function schedulePerformancePct(): Attribute
    {
        return Attribute::make(get: function (): ?float {
            if (! $this->planned_start || ! $this->planned_end || ! $this->actual_start || ! $this->actual_end) {
                return null;
            }

            $plannedDays = $this->planned_start->diffInDays($this->planned_end);
            $actualDays = $this->actual_start->diffInDays($this->actual_end);

            if ($actualDays <= 0) {
                return null;
            }

            return round($plannedDays / $actualDays * 100, 1);
        });
    }

    /**
     * Phase 2 (E4-T5) — budget_planned ÷ budget_actual × 100. Null unless both are set, or
     * the actual budget is zero (nothing to divide by).
     */
    protected function budgetPerformancePct(): Attribute
    {
        return Attribute::make(get: function (): ?float {
            if ($this->budget_planned === null || $this->budget_actual === null) {
                return null;
            }

            $actual = (float) $this->budget_actual;

            if ($actual === 0.0) {
                return null;
            }

            return round((float) $this->budget_planned / $actual * 100, 1);
        });
    }

    /**
     * Phase 2 (E4-T5) — null unless planned_end and actual_end are both set.
     */
    protected function isOnTime(): Attribute
    {
        return Attribute::make(get: function (): ?bool {
            if (! $this->planned_end || ! $this->actual_end) {
                return null;
            }

            return $this->actual_end->lessThanOrEqualTo($this->planned_end);
        });
    }

    /**
     * Phase 2 (E4-T5) — null unless both budgets are set.
     */
    protected function isOnBudget(): Attribute
    {
        return Attribute::make(get: function (): ?bool {
            if ($this->budget_planned === null || $this->budget_actual === null) {
                return null;
            }

            return (float) $this->budget_actual <= (float) $this->budget_planned;
        });
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

    /**
     * project_media is the same shape as project_technologies (composite PK, no
     * id/timestamps) plus its own sort_order column (E4-T5, §9 step 23 — a project's gallery
     * needs a stable display order, unlike the technologies pivot).
     *
     * @return BelongsToMany<Media, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'project_media')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * project_documents: same composite-PK-pivot shape, no ordering column (E4-T5).
     *
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'project_documents');
    }

    /**
     * Phase 2 (E4-T3) — supplementary downloadable files (PDFs, ZIP archives) distinct from
     * the image gallery (media()) and the resume-style Document rows (documents()). Same
     * composite-PK-pivot shape plus an optional admin-editable label and sort_order.
     *
     * @return BelongsToMany<Media, $this>
     */
    public function projectFiles(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'project_files')
            ->withPivot('label', 'sort_order')
            ->orderByPivot('sort_order');
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
