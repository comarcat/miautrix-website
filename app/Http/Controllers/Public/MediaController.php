<?php

namespace App\Http\Controllers\Public;

use App\Models\Media;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a media file from the private-media disk only if its owning entity is published
 * (blueprint §5's media-serving contract, §9 step 16). Files here are never served directly
 * by the disk itself (config/filesystems.php's private-media disk has no `url`/`serve`) —
 * this is the only path to one.
 */
class MediaController extends Controller
{
    public function show(Media $media, string $filename): Response
    {
        // The filename in the URL must match exactly — prevents enumerating other media
        // rows' files by id alone, and 404s the same way a wrong id would.
        if ($filename !== $media->file_name) {
            throw new NotFoundHttpException;
        }

        if (! $this->ownerIsPublished($media)) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($media->disk);
        // Matches MediaUploadField's fixed ->directory('uploads') — the media table itself
        // has no directory column, so this is the one place both sides need to agree on it.
        $path = 'uploads/' . $media->file_name;

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        return $disk->response($path, $media->file_name, [
            'Content-Type' => $disk->mimeType($path) ?: $media->mime_type,
        ]);
    }

    /**
     * A Media row's owner (spatie's polymorphic model_type/model_id) is only gated on
     * publication when that owner is itself a publishable entity (has a `published`
     * attribute) — Profile/Company aren't, and always allow their media through.
     */
    private function ownerIsPublished(Media $media): bool
    {
        $owner = $media->model;

        if (! $owner) {
            return false;
        }

        if (! array_key_exists('published', $owner->getAttributes())) {
            return true;
        }

        return (bool) $owner->getAttribute('published');
    }
}
