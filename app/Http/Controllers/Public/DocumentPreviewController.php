<?php

namespace App\Http\Controllers\Public;

use App\Models\Document;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Found in review: "please show the PDF in a section not just put the link for download" —
 * the /resume page only ever linked to DocumentDownloadController, which forces
 * Content-Disposition: attachment (so an <iframe> pointed at it triggers a save dialog
 * instead of rendering) and increments download_count on every request — wrong for a page
 * embed that reloads/renders on every visit, since that would inflate the counter for
 * viewing, not downloading.
 *
 * This streams the same file with Content-Disposition: inline instead (Storage::response(),
 * not ::download()) and never touches download_count — that counter stays a measure of
 * actual downloads (DocumentDownloadController, unchanged) rather than page views.
 */
class DocumentPreviewController extends Controller
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

        return $disk->response($path, $document->title . '.' . pathinfo($media->file_name, PATHINFO_EXTENSION));
    }
}
