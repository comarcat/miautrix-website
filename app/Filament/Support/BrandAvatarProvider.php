<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Found in review: this used to generate a local initials badge (a `data:image/svg+xml` URI
 * computed from User::initials()) to avoid Filament's default UiAvatarsProvider, which calls
 * out to https://ui-avatars.com — a request our own CSP's `img-src 'self' data:` already
 * blocks. Since then, a real brand image was uploaded and explicitly asked to be the admin
 * panel's avatar too — this now returns that image instead, still served locally
 * (public/images/brand/miautrix-avatar.png, img-src 'self'), so the CSP reasoning that
 * ruled out UiAvatarsProvider in the first place still holds.
 *
 * Single-admin site (§4 — a singleton in practice), so returning the same static image for
 * every record here is intentional, not a placeholder for per-user photos.
 */
class BrandAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        return asset('images/brand/miautrix-avatar.png');
    }
}
