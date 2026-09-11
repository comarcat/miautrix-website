<?php

namespace App\Support\Seo;

use App\Models\Media;

/**
 * E5-T1 (§9 step 25, acceptance 5) — resolves an entity's og_image_id to a public URL,
 * falling back to the site-default OG image when it's null or the Media row is missing.
 * Deliberately a plain static helper, not a Model trait: only Project and Article currently
 * have a public detail page to put an OG image on, so there's no shared base class for this
 * to naturally live on.
 *
 * E2-T4 (§9 step 12) — the return value is now always an absolute `https://` URL. Crawlers
 * (Facebook, LinkedIn, Slack, iMessage) reject a scheme-relative or path-only `og:image`
 * outright, so both `route()` and `asset()` output is forced to the https scheme regardless
 * of the request scheme or APP_URL.
 */
class OgImage
{
    public static function resolve(?int $mediaId): string
    {
        if ($mediaId) {
            $media = Media::find($mediaId);

            if ($media) {
                return self::https(route('media.show', [$media, $media->file_name], absolute: true));
            }
        }

        return self::https(asset('images/og-default.png'));
    }

    private static function https(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return preg_replace('#^http://#i', 'https://', $url, 1) ?? $url;
        }

        // Path-only or scheme-relative — anchor it to the configured site origin.
        $origin = 'https://' . preg_replace('#^https?://#i', '', rtrim((string) config('app.url'), '/'));

        return $origin . '/' . ltrim($url, '/');
    }
}
