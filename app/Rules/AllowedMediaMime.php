<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Extension → sniffed-MIME allowlist (§9 step 16). JPEG, PNG, WebP, PDF, DOCX, ZIP only — SVG
 * is explicitly excluded (it can carry embedded <script>). The MIME check reads
 * UploadedFile::getMimeType(), which sniffs the actual bytes via PHP's fileinfo extension —
 * never the client-supplied Content-Type header — so a script renamed to `.jpg` fails here
 * even though its extension looks fine.
 */
class AllowedMediaMime implements ValidationRule
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        // A .docx is itself a zip container — some fileinfo/libmagic versions sniff it as
        // application/zip rather than the OOXML-specific type. Both are accepted for .docx.
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');

            return;
        }

        $extension = strtolower((string) $value->getClientOriginalExtension());

        if (! array_key_exists($extension, self::ALLOWED)) {
            $fail('The :attribute must be a JPEG, PNG, WebP, PDF, DOCX, or ZIP file.');

            return;
        }

        $sniffedMime = $value->getMimeType();

        if (! in_array($sniffedMime, self::ALLOWED[$extension], true)) {
            $fail('The :attribute content does not match its file extension.');
        }
    }
}
