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

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Uploading a new version (§9 step 17): swapping media_id on an already-persisted
     * document bumps version automatically. The prior Media row is never touched here — it
     * simply becomes unreferenced by this Document, which is what "keep the prior version's
     * media row intact" means (nothing deletes it).
     */
    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->exists && $document->isDirty('media_id')) {
                $document->version = ((int) $document->getOriginal('version')) + 1;
            }
        });
    }
}
