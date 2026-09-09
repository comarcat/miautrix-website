<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Found in review: "still nothing on the config section" — HomeController::index() reads
 * home_hero_eyebrow/heading/subheading via Setting::get($key, $default), falling back to
 * the original copy when no row exists. That fallback meant the home page kept working with
 * no Setting rows at all, but it also meant nothing showed up in the admin's Settings list
 * for an admin to actually edit — there was no row to click into, and no way to discover the
 * expected key names without reading the source.
 *
 * A data migration (not a one-off command, unlike ImportRealResumeContent/
 * FetchCompanyEducationLogos) is the right tool here: this is generic marketing copy, not
 * personal data, so running it in every environment (fresh local DBs, CI) is completely
 * fine — the same way DatabaseSeeder's own Setting::put() calls (site_title, theme_default,
 * contact_email) already work. Setting::put() is an upsert, so this is safe to run again if
 * a future migration ever needs to touch these same keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::put('home_hero_eyebrow', 'Portfolio · Blog · CMS');
        Setting::put('home_hero_heading', 'Building reliable systems, end to end.');
        Setting::put(
            'home_hero_subheading',
            'A professional IT portfolio covering backend architecture, infrastructure, and the projects behind it.',
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['home_hero_eyebrow', 'home_hero_heading', 'home_hero_subheading'])->delete();
    }
};
