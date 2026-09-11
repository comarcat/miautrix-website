<?php

namespace App\Http\Controllers\Public;

use App\Support\Geo\GeoLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Phase 2 (E5-T4, §9 step 38) — the "whoami" payload (backlog item 4), consumed by the
 * terminal widget's `whoami` command and reachable directly at GET /whoami. IP-derived
 * fields come from GeoLocator (null without the GeoLite2-City .mmdb — never throws);
 * `timezone`/`screen`/`gps` are null here by design — only the client's own browser knows
 * them, so the terminal widget fills those in client-side after this response lands.
 *
 * Deliberately no `mac` key: no website can ever observe a visitor's MAC address (it never
 * leaves the visitor's local network segment) — see backlog discussion; faking one here
 * would be actively misleading.
 */
class WhoamiController extends Controller
{
    public function __invoke(Request $request, GeoLocator $geo): JsonResponse
    {
        $ip = (string) $request->ip();

        return response()->json([
            'ip' => $ip,
            'isp' => $geo->isp($ip),
            'city' => $geo->city($ip),
            'region' => $geo->region($ip),
            'country' => $geo->country($ip),
            'ua' => $request->userAgent(),
            'timezone' => null,
            'screen' => null,
            'gps' => null,
        ]);
    }
}
