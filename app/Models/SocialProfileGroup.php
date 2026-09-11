<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E2-T6) — one ordered section on the /connect page. `heading` and `intro_text`
 * are admin-editable copy rendered above the group's links.
 *
 * No SoftDeletes on purpose: `social_profiles.group_id` is `nullOnDelete`, so a hard delete
 * here ungroups the member profiles (acceptance 2) rather than orphaning a dangling FK that
 * a soft delete would leave pointing at a hidden row.
 *
 * @property int $id
 * @property string $name
 * @property string $heading
 * @property string|null $intro_text
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SocialProfileGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'heading',
        'intro_text',
        'sort_order',
    ];

    /**
     * @return HasMany<SocialProfile, $this>
     */
    public function socialProfiles(): HasMany
    {
        return $this->hasMany(SocialProfile::class, 'group_id');
    }
}
