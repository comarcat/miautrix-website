<?php

namespace App\Models;

use App\Exceptions\MediaInUseException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * Extends spatie/laravel-medialibrary's own Media model (swapped in via
 * config('media-library.media_model')) purely to add the app-level delete guard from
 * blueprint §4: a Media row referenced by a *published* entity cannot be hard-deleted.
 * Media that isn't referenced by anything published degrades gracefully instead, via the
 * nullOnDelete foreign keys on the columns listed in $referencingColumns.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string|null $uuid
 * @property string $collection_name
 * @property string $name
 * @property string $file_name
 * @property string|null $mime_type
 * @property string $disk
 * @property string|null $conversions_disk
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pivot|null $pivot set only when
 *   loaded through a belongsToMany pivot relation (e.g. Project::projectFiles(), E4-T3) —
 *   absent otherwise.
 */
class Media extends SpatieMedia
{
    /**
     * Every column across the schema that can point at a media row, and — where the
     * owning table has one — the boolean column that must be true for the reference to
     * count as "in use". A table with no published column (none currently reference
     * media directly) would list null and always count as in use.
     *
     * @var list<array{table: string, column: string, publishedColumn: string|null}>
     */
    private static array $referencingColumns = [
        ['table' => 'profiles', 'column' => 'avatar_media_id', 'publishedColumn' => null],
        ['table' => 'companies', 'column' => 'logo_media_id', 'publishedColumn' => null],
        ['table' => 'experiences', 'column' => 'og_image_id', 'publishedColumn' => 'published'],
        ['table' => 'education', 'column' => 'og_image_id', 'publishedColumn' => 'published'],
        ['table' => 'education', 'column' => 'logo_media_id', 'publishedColumn' => 'published'],
        ['table' => 'certifications', 'column' => 'media_id', 'publishedColumn' => 'published'],
        ['table' => 'certifications', 'column' => 'og_image_id', 'publishedColumn' => 'published'],
        ['table' => 'projects', 'column' => 'og_image_id', 'publishedColumn' => 'published'],
        ['table' => 'documents', 'column' => 'media_id', 'publishedColumn' => 'published'],
        // Skills have no `published` gate of their own — a skill is public as soon as it
        // exists, so null here (same reasoning as profiles/companies above).
        ['table' => 'skills', 'column' => 'icon_media_id', 'publishedColumn' => null],
        // Same for social_profiles — no `published` gate of its own.
        ['table' => 'social_profiles', 'column' => 'icon_media_id', 'publishedColumn' => null],
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            if ($media->isInUseByAPublishedEntity()) {
                throw MediaInUseException::referencedByPublishedEntity($media->id);
            }
        });
    }

    private function isInUseByAPublishedEntity(): bool
    {
        foreach (self::$referencingColumns as $reference) {
            $query = DB::table($reference['table'])->where($reference['column'], $this->id);

            if ($reference['publishedColumn'] !== null) {
                $query->where($reference['publishedColumn'], true);
            }

            if ($query->exists()) {
                return true;
            }
        }

        // project_media/project_files have no published column of their own — a project's
        // own status governs (E4-T3 added project_files alongside the pre-existing gallery).
        foreach (['project_media', 'project_files'] as $pivotTable) {
            $usedByAPublishedProject = DB::table($pivotTable)
                ->join('projects', 'projects.id', '=', "{$pivotTable}.project_id")
                ->where("{$pivotTable}.media_id", $this->id)
                ->where('projects.published', true)
                ->exists();

            if ($usedByAPublishedProject) {
                return true;
            }
        }

        return false;
    }
}
