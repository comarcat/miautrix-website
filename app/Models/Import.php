<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Seam table for the deferred GitHub import (Non-Goal #2) — created, unpopulated by any
 * implementation in v1. See App\Contracts\PortfolioIntegration.
 *
 * @property int $id
 * @property string $source github|linkedin
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Import extends Model
{
    protected $fillable = [
        'source',
        'status',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(ImportRecord::class);
    }
}
