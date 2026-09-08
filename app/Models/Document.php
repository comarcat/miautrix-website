<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Versioned uploads (resume and any other downloadable document) — §4.
 *
 * @property int $id
 * @property string $title
 * @property string $kind resume|other
 * @property int|null $media_id
 * @property int $version
 * @property int $download_count
 * @property bool $published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'kind',
        'media_id',
        'version',
        'download_count',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
