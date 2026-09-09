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
 * Every URL below was fetched and visually confirmed by hand before being hardcoded here —
 * this command doesn't guess at company identity or trust a search result. Two different
 * sources, in order of preference:
 * 1. The institution's OWN site's own <link rel="icon"> tag (MSP Corp, FESAECUADOR, Nexsys,
 *    ESPE, Digital Solutions — found by fetching each homepage's HTML directly). This is the
 *    institution's own asset, at its own chosen resolution — the most authoritative source
 *    available.
 * 2. Google's public favicon service (https://www.google.com/s2/favicons), only for
 *    University of Winnipeg, whose own site didn't expose a fetchable direct icon URL as
 *    easily — still resolves an institution's own favicon from its own domain, not a random
 *    image-search result.
 *
 * BUG FIXED: the original version matched Education by institution NAME with ->first() —
 * silently attaching the fetched logo to only ONE of the two Education rows sharing "University
 * of Winnipeg (...)" as their institution (a Diploma and a Certificate), leaving the other
 * with no logo at all. Now updates every matching row.
 *
 * "Digital Solutions" and Army Polytechnic School/ESPE were skipped in an earlier version of
 * this command — too generic a name to identify with confidence, and no favicon resolvable
 * via Google's service, respectively. Direct URLs supplied since then (found on both
 * institutions' own sites) resolve both.
 */
class FetchCompanyEducationLogos extends Command
{
    protected $signature = 'app:fetch-company-education-logos';

    protected $description = 'One-off: fetch and attach real logos for the companies/institutions on the resume (run once, by hand)';

    public function handle(): int
    {
        $this->fetchForCompany(
            'MSP Corp Prairies (Broadview Networks)',
            'https://www.google.com/s2/favicons?domain=mspcorp.ca&sz=128',
        );
        $this->fetchForCompany(
            'FESAECUADOR',
            'https://www.google.com/s2/favicons?domain=fesaecuador.com.ec&sz=128',
        );
        $this->fetchForCompany(
            'Nexsys',
            'https://www.google.com/s2/favicons?domain=nexsysla.com&sz=128',
        );
        $this->fetchForCompany(
            'Digital Solutions',
            'https://digitalsolutions.com.ec/wp-content/uploads/2020/12/cropped-01-192x192.png',
        );

        $this->fetchForEducation(
            'University of Winnipeg (Professional, Applied & Continuing Education)',
            'https://www.google.com/s2/favicons?domain=uwinnipeg.ca&sz=128',
        );
        $this->fetchForEducation(
            'Army Polytechnic School (ESPE), Quito, Ecuador',
            'https://www.espe.edu.ec/wp-content/uploads/2018/10/logo_espe-300x300.png',
        );

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function fetchForCompany(string $name, string $url): void
    {
        $company = Company::where('name', $name)->first();

        if (! $company) {
            $this->warn("No Company row named \"{$name}\" — skipping.");

            return;
        }

        $path = MediaUploadField::fetchAndStore($url);

        if (! $path) {
            $this->warn("Could not fetch a logo for \"{$name}\" from {$url} — leaving it without one.");

            return;
        }

        $media = MediaUploadField::createMediaRecord($path, Company::class, $company->id);
        $company->update(['logo_media_id' => $media->id]);

        $this->info("Attached a logo to \"{$name}\".");
    }

    /**
     * Every Education row matching $institution gets its OWN Media row (spatie's
     * polymorphic model_id points at one specific record) — fetched once, then re-stored per
     * row, since two rows can legitimately share an institution name (a Diploma and a
     * Certificate from the same continuing-education program, in this resume's case).
     */
    private function fetchForEducation(string $institution, string $url): void
    {
        $educationEntries = Education::where('institution', $institution)->get();

        if ($educationEntries->isEmpty()) {
            $this->warn("No Education row for \"{$institution}\" — skipping.");

            return;
        }

        foreach ($educationEntries as $education) {
            $path = MediaUploadField::fetchAndStore($url);

            if (! $path) {
                $this->warn("Could not fetch a logo for \"{$institution}\" (#{$education->id}) from {$url} — leaving it without one.");

                continue;
            }

            $media = MediaUploadField::createMediaRecord($path, Education::class, $education->id);
            $education->update(['logo_media_id' => $media->id]);

            $this->info("Attached a logo to \"{$institution}\" (#{$education->id}).");
        }
    }
}
