<?php

namespace App\Http\Controllers\Public;

use App\Models\Document;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams the current version of a document and increments its download_count by exactly 1
 * per request (§5's media-serving contract, §9 step 17). Public only for
 * documents.published = true.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document): Response
    {
        $media = $document->media()->first();

        if (! $document->published || ! $media) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($media->disk);
        $path = 'uploads/' . $media->file_name;

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        $document->increment('download_count');

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);

        return $disk->download($path, $document->title . ($extension ? ".{$extension}" : ''));
    }
}
