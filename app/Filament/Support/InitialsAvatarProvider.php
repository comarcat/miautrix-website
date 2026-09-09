<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Found in review: Filament's own default (UiAvatarsProvider) calls out to the external
 * https://ui-avatars.com service to render an initials avatar — a request our own CSP's
 * `img-src 'self' data:` already blocks, and the starter-kit's settings pages never made
 * that call in the first place (resources/views/components/desktop-user-menu.blade.php uses
 * Flux's <flux:avatar :initials="..."> — a locally-rendered badge, no network request, no
 * uploaded picture). There's no avatar upload anywhere in this app; making the admin panel's
 * avatar consistent with what the rest of the app already shows means it should also be a
 * local initials badge, not a broken/blocked image.
 *
 * This returns a `data:image/svg+xml` URI — no request ever leaves the browser, so it works
 * under `img-src 'self' data:` unchanged, and it reuses User::initials() so both surfaces
 * compute the same two letters from the same name.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = method_exists($record, 'initials') ? $record->initials() : '?';

        // htmlspecialchars, not e()/Blade — this is raw XML, not a Blade template, and a
        // name containing '<' or '&' would otherwise produce invalid/injected SVG markup.
        $initials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
                <rect width="40" height="40" rx="20" fill="#3F3F46" />
                <text x="20" y="20" fill="#FFFFFF" font-family="ui-sans-serif, system-ui, sans-serif" font-size="15" font-weight="600" text-anchor="middle" dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
