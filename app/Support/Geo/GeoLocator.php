<?php

namespace App\Support\Geo;

use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Throwable;

/**
 * Phase 2 (E5-T2, §9 step 36) — IP → country/region/city/ISP, entirely null-safe. The
 * GeoLite2-City `.mmdb` is an un-committed operator prerequisite (config('geoip.database_path'),
 * GEOIP_DATABASE_PATH) — every method here returns null rather than throwing when it's
 * missing, unreadable, corrupt, or the address simply isn't found in it. `isp()` is always
 * null against a City-edition database (ISP/ASN data lives in a separate MaxMind product);
 * this is intentionally the same "not available" null every other gap here returns, not a
 * distinct error path a caller would need to handle differently.
 *
 * The Reader is opened once per instance, lazily, on first use — a request that never looks
 * up an IP never touches the filesystem for this at all.
 */
class GeoLocator
{
    private ?Reader $reader = null;

    private bool $failedToOpen = false;

    public function country(string $ip): ?string
    {
        return $this->lookup($ip)?->country?->isoCode;
    }

    public function region(string $ip): ?string
    {
        $record = $this->lookup($ip);

        if ($record === null) {
            return null;
        }

        return $record->subdivisions[0]->isoCode ?? null;
    }

    public function city(string $ip): ?string
    {
        return $this->lookup($ip)?->city?->name;
    }

    /**
     * Always null — GeoLite2-City carries no ISP/ASN data (see class docblock).
     */
    public function isp(string $ip): ?string
    {
        return null;
    }

    private function lookup(string $ip): ?City
    {
        $reader = $this->reader();

        if ($reader === null) {
            return null;
        }

        try {
            return $reader->city($ip);
        } catch (Throwable) {
            return null;
        }
    }

    private function reader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        if ($this->failedToOpen) {
            return null;
        }

        $path = (string) config('geoip.database_path');

        if ($path === '' || ! is_readable($path)) {
            $this->failedToOpen = true;

            return null;
        }

        try {
            return $this->reader = new Reader($path);
        } catch (Throwable) {
            $this->failedToOpen = true;

            return null;
        }
    }
}
