<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E3-T1) — a theme is an admin-managed row: `key` is the stable identifier the
 * `miautrix_theme` cookie and the page-cache key are written in; `tokens` is a JSON map of
 * CSS-custom-property overrides injected into a nonce'd <style> at render (E3-T4); an empty
 * `tokens` (the seeded `technical`/`matrix` state) means "render exactly as Phase 1 did".
 *
 * Exactly one row is `is_default` at a time — the `saving` hook demotes every other row
 * whenever a row is saved with `is_default = true`.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array<string, string> $tokens
 * @property bool $is_default
 * @property bool $enabled
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property bool $shows_life_blog
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'tokens',
        'is_default',
        'enabled',
        'active_from',
        'active_until',
        'shows_life_blog',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_default' => 'boolean',
            'enabled' => 'boolean',
            'active_from' => 'date',
            'active_until' => 'date',
            'shows_life_blog' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $theme): void {
            if (! $theme->is_default) {
                return;
            }

            // Query-builder update: bypasses model events, so no recursion back into this
            // hook. Excludes the row being saved by its natural key (id may be null on
            // insert).
            static::query()
                ->where('key', '!=', $theme->key)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
