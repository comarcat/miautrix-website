<?php

namespace App\Support\Seo;

use App\Models\Media;

/**
 * E5-T1 (§9 step 25, acceptance 5) — resolves an entity's og_image_id to a public URL,
 * falling back to the site-default OG image when it's null or the Media row is missing.
 * Deliberately a plain static helper, not a Model trait: only Project and Article currently
 * have a public detail page to put an OG image on, so there's no shared base class for this
 * to naturally live on.
 */
class OgImage
{
    public static function resolve(?int $mediaId): string
    {
        if ($mediaId) {
            $media = Media::find($mediaId);

            if ($media) {
                return route('media.show', [$media, $media->file_name]);
            }
        }

        return asset('images/og-default.png');
    }
}
