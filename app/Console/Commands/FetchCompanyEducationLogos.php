<?php

namespace App\Console\Commands;

use App\Filament\Support\MediaUploadField;
use App\Models\Company;
use App\Models\Education;
use Illuminate\Console\Command;

/**
 * One-off, manually-invoked import — requested in production review: "add the logos of the
 * companies / education institutions and pull that information from the web and save it to
 * our site". Not a migration, for the same reason as ImportRealResumeContent: this fetches
 * real third-party logo images over HTTP, which has no business running automatically in
 * every environment (every teammate's local DB, CI) forever.
 *
 * Uses Google's public favicon service (https://www.google.com/s2/favicons) as the fetch
 * source, not a random image-search result — it resolves an institution's own favicon
 * straight from its own domain, which is far more likely to actually BE that institution's
 * real mark than picking whatever an image search ranks first. Each URL was verified by hand
 * (fetched and visually confirmed) before being hardcoded here — this command doesn't guess
 * at company identity or search results itself.
 *
 * Deliberately skips two entries rather than attaching a wrong or fake logo:
 * - "Digital Solutions" (Nov 2011 - Sep 2013, Quito, Ecuador): too generic a name to
 *   identify with any confidence — dozens of companies share it. Guessing would risk
 *   attaching a stranger's logo to this resume.
 * - Army Polytechnic School (ESPE): its real domain (espe.edu.ec) has no favicon Google's
 *   service can resolve (confirmed 404, not a transient failure) — no image to fetch at all,
 *   not a naming ambiguity like the one above.
 * Both entries still show correctly by name; they simply render without a logo, the same as
 * any entry an admin hasn't uploaded one for.
 */
class FetchCompanyEducationLogos extends Command
{
    protected $signature = 'app:fetch-company-education-logos';

    protected $description = 'One-off: fetch and attach real logos for the companies/institutions on the resume (run once, by hand)';

    public function handle(): int
    {
        $this->fetchForCompany('MSP Corp Prairies (Broadview Networks)', 'mspcorp.ca');
        $this->fetchForCompany('FESAECUADOR', 'fesaecuador.com.ec');
        $this->fetchForCompany('Nexsys', 'nexsysla.com');

        $this->fetchForEducation('University of Winnipeg (Professional, Applied & Continuing Education)', 'uwinnipeg.ca');

        $this->info('Done. Skipped "Digital Solutions" (name too generic to identify with confidence) and Army Polytechnic School / ESPE (no favicon available at espe.edu.ec) — see this command\'s own docblock.');

        return self::SUCCESS;
    }

    private function fetchForCompany(string $name, string $domain): void
    {
        $company = Company::where('name', $name)->first();

        if (! $company) {
            $this->warn("No Company row named \"{$name}\" — skipping.");

            return;
        }

        $path = MediaUploadField::fetchAndStore("https://www.google.com/s2/favicons?domain={$domain}&sz=128");

        if (! $path) {
            $this->warn("Could not fetch a logo for \"{$name}\" from {$domain} — leaving it without one.");

            return;
        }

        $media = MediaUploadField::createMediaRecord($path, Company::class, $company->id);
        $company->update(['logo_media_id' => $media->id]);

        $this->info("Attached a logo to \"{$name}\".");
    }

    private function fetchForEducation(string $institution, string $domain): void
    {
        $education = Education::where('institution', $institution)->first();

        if (! $education) {
            $this->warn("No Education row for \"{$institution}\" — skipping.");

            return;
        }

        $path = MediaUploadField::fetchAndStore("https://www.google.com/s2/favicons?domain={$domain}&sz=128");

        if (! $path) {
            $this->warn("Could not fetch a logo for \"{$institution}\" from {$domain} — leaving it without one.");

            return;
        }

        $media = MediaUploadField::createMediaRecord($path, Education::class, $education->id);
        $education->update(['logo_media_id' => $media->id]);

        $this->info("Attached a logo to \"{$institution}\".");
    }
}
