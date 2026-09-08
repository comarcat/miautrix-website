<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when deletion of a Media row is blocked because a published entity still
 * references it (blueprint §4: "referenced-by-published-entity media cannot be
 * hard-deleted"). Media not referenced by anything published degrades gracefully via
 * nullOnDelete foreign keys instead.
 */
class MediaInUseException extends RuntimeException
{
    public static function referencedByPublishedEntity(int $mediaId): self
    {
        return new self("Media #{$mediaId} is referenced by a published entity and cannot be deleted.");
    }
}
