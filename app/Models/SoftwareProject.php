<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 1:1 extension table on Project, not single-table-inheritance (§4).
 *
 * @property int $id
 * @property int $project_id
 * @property string|null $language_primary
 * @property string|null $architecture_notes
 * @property string|null $deployment_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SoftwareProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'language_primary',
        'architecture_notes',
        'deployment_notes',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
