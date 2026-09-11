<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GeoLite2-City database path
    |--------------------------------------------------------------------------
    |
    | Phase 2 (E5-T2) — an un-committed operator prerequisite. GeoLocator returns
    | null for every field (country/region/city/isp) when the file at this path
    | is missing or unreadable — no test, and no code path, depends on it
    | actually existing.
    |
    */

    'database_path' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),

];
