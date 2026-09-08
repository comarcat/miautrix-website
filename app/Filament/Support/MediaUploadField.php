<?php

namespace App\Filament\Support;

use App\Rules\AllowedMediaMime;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A pre-hardened FileUpload factory (§9 step 16) — every real upload field in the admin
 * should be built from this rather than a bare `FileUpload::make()`, so the allowlist,
 * disk, filename, and re-encode behavior can't drift resource by resource.
 *
 * - Extension + sniffed-MIME allowlist via AllowedMediaMime (JPEG/PNG/WebP/PDF/DOCX/ZIP —
 *   no SVG).
 * - Stored on the `private-media` disk (outside the web root, config/filesystems.php),
 *   never `public`.
 * - UUID-prefixed filenames — never the client-supplied name.
 * - Images (JPEG/PNG/WebP) are re-encoded through GD before being written to disk, which
 *   discards anything that isn't real pixel data (an embedded script in a polyglot file, for
 *   instance) regardless of what the original bytes contained.
 *
 * Returns a normal storage path from saveUploadedFileUsing (not a Media row/id) — Filament's
 * own upload-preview and removal machinery expects that. Turning an uploaded file into a
 * spatie Media row tied to a specific owning record is the resource's own job (e.g. E3-T5's
 * DocumentResource), not this shared field's.
 *
 * The actual storage logic lives in the public static store() method, not inline in the
 * saveUploadedFileUsing closure, specifically so it can be exercised directly in tests
 * without needing a mounted Livewire/Filament form.
 */
class MediaUploadField
{
    public const DIRECTORY = 'uploads';

    public const DISK = 'private-media';

    /**
     * @var list<string>
     */
    private const ACCEPTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];

    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->disk(self::DISK)
            ->directory(self::DIRECTORY)
            ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
            ->rules([new AllowedMediaMime])
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => self::uuidNameFor($file),
            )
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $file): ?string => self::store($file),
            );
    }

    /**
     * Re-encodes the file (if it's an image) and writes it to the private-media disk under a
     * UUID-prefixed name. Returns the stored path (relative to the disk root), or null if the
     * temporary file is already gone.
     */
    public static function store(TemporaryUploadedFile $file): ?string
    {
        if (! $file->exists()) {
            return null;
        }

        $storedName = self::uuidNameFor($file);
        $path = self::DIRECTORY . '/' . $storedName;
        $reencoded = self::reencodeIfImage($file->getRealPath(), $file->getMimeType());

        if ($reencoded !== null) {
            Storage::disk(self::DISK)->put($path, $reencoded);
        } else {
            $file->storeAs(self::DIRECTORY, $storedName, self::DISK);
        }

        return $path;
    }

    private static function uuidNameFor(TemporaryUploadedFile $file): string
    {
        return (string) Str::uuid() . '.' . strtolower((string) $file->getClientOriginalExtension());
    }

    /**
     * Re-encodes JPEG/PNG/WebP through GD, stripping anything that isn't real pixel data.
     * Returns null for non-image types (PDF/DOCX/ZIP pass through unmodified — GD can't and
     * shouldn't touch those).
     */
    private static function reencodeIfImage(string $path, string $mime): ?string
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if (! $image) {
            return null;
        }

        ob_start();

        match ($mime) {
            'image/jpeg' => imagejpeg($image, quality: 90),
            'image/png' => imagepng($image),
            'image/webp' => imagewebp($image, quality: 90),
        };

        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes === false ? null : $bytes;
    }
}
