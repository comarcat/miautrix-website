<?php

namespace App\Http\Controllers\Public;

use App\Models\Tool;
use App\Support\Geo\GeoLocator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase 2 (E5-T6, §9 step 40) — 404s unless the Tool is published AND has a file attached;
 * records one ToolDownload row (ip/geo/referrer/UA — geo fields null without the GeoLite2
 * .mmdb, same null-safety as every other GeoLocator consumer) before streaming the file.
 * Registered OUTSIDE cache.public — both the row write and the binary body must happen on
 * every request.
 */
class ToolDownloadController extends Controller
{
    private const REFERRER_MAX = 255;

    public function __invoke(Request $request, string $slug, GeoLocator $geo): Response
    {
        $tool = Tool::where('slug', $slug)->published()->first();
        $media = $tool?->toolFile;

        if (! $tool || ! $media) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($media->disk);
        $path = 'uploads/' . $media->file_name;

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        $ip = (string) $request->ip();

        $tool->downloads()->create([
            'ip' => $ip,
            'country' => $geo->country($ip),
            'region' => $geo->region($ip),
            'city' => $geo->city($ip),
            'isp' => $geo->isp($ip),
            'referrer' => Str::limit((string) $request->headers->get('referer'), self::REFERRER_MAX, ''),
            'user_agent' => $request->userAgent(),
        ]);

        return $disk->download($path, $tool->slug . '-' . $media->file_name);
    }
}
