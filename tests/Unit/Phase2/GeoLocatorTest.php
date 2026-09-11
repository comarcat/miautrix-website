<?php

namespace Tests\Unit\Phase2;

use App\Support\Geo\GeoLocator;
use Tests\TestCase;

/**
 * E5-T2 (Phase 2, p2-step-36) — every GeoLocator method returns null, never throws, when the
 * .mmdb file is absent (the default state of this repo — nothing commits the real database).
 */
class GeoLocatorTest extends TestCase
{
    public function test_country_is_null_without_an_mmdb_file(): void
    {
        config(['geoip.database_path' => storage_path('app/geoip/does-not-exist.mmdb')]);

        $this->assertNull((new GeoLocator)->country('8.8.8.8'));
    }

    public function test_region_is_null_without_an_mmdb_file(): void
    {
        config(['geoip.database_path' => storage_path('app/geoip/does-not-exist.mmdb')]);

        $this->assertNull((new GeoLocator)->region('8.8.8.8'));
    }

    public function test_city_is_null_without_an_mmdb_file(): void
    {
        config(['geoip.database_path' => storage_path('app/geoip/does-not-exist.mmdb')]);

        $this->assertNull((new GeoLocator)->city('8.8.8.8'));
    }

    public function test_isp_is_always_null(): void
    {
        config(['geoip.database_path' => storage_path('app/geoip/does-not-exist.mmdb')]);

        $this->assertNull((new GeoLocator)->isp('8.8.8.8'));
    }
}
