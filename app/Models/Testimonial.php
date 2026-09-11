<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Phase 2 (E6-T1, §9 step 44) — a visitor-submitted endorsement (backlog item 15). Public
 * submission (E6-T2's TestimonialForm) never sets `status` — it's `pending` until the owner
 * approves it in Filament (E6-T4). `contact_type`/`status` are enum-in-code: validated on
 * write, never a DB enum (matches Article::channel, Theme's booleans, etc.).
 *
 * @property int $id
 * @property string $name
 * @property string|null $organization
 * @property string|null $role
 * @property string $contact_type 'email'|'linkedin'|'url'
 * @property string $contact_value
 * @property string $body
 * @property string $status 'pending'|'approved'|'rejected'
 * @property Carbon|null $approved_at
 * @property int|null $company_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Testimonial extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const CONTACT_TYPE_EMAIL = 'email';

    public const CONTACT_TYPE_LINKEDIN = 'linkedin';

    public const CONTACT_TYPE_URL = 'url';

    protected $fillable = [
        'name',
        'organization',
        'role',
        'contact_type',
        'contact_value',
        'body',
        'status',
        'approved_at',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
