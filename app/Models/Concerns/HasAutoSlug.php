<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-derives `slug` from the model's slugSource() when it's left blank at creation — the
 * single source of truth for this, rather than duplicating slugify-on-blur JS in every
 * Filament resource form. A resource form can still show/edit `slug` directly (all these
 * models keep it a real, unique column); this only fills in a sane default when nothing was
 * typed.
 */
trait HasAutoSlug
{
    protected static function bootHasAutoSlug(): void
    {
        static::creating(function ($model): void {
            if (blank($model->slug)) {
                $model->slug = Str::slug($model->slugSource());
            }
        });
    }

    /**
     * The text this model's slug is derived from, e.g. `$this->title`.
     */
    abstract protected function slugSource(): string;
}
