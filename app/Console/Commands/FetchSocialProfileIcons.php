<?php

namespace App\Console\Commands;

use App\Filament\Support\MediaUploadField;
use App\Models\SocialProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-off, manually-invoked import — requested in production review: "a new section to
 * publish all social profiles there with their logos near to the links". Same reasoning as
 * FetchCompanyEducationLogos/FetchSkillIcons for being a command rather than a migration
 * (fetches third-party images over HTTP) and for using Google's favicon service (every
 * domain below verified by hand before being hardcoded here, not a blind image search).
 *
 * Matched by a case-insensitive match against `platform` (not an exact string) so this
 * still finds "linkedin", "LinkedIn", or "Linked In" without needing the exact casing
 * whoever entered the row used. A platform whose name doesn't match any entry here (a
 * niche or self-hosted platform) is left without an icon — there's no domain to guess at.
 */
class FetchSocialProfileIcons extends Command
{
    protected $signature = 'app:fetch-social-profile-icons';

    protected $description = 'One-off: fetch and attach real brand icons for known social platforms (run once, by hand)';

    /**
     * @var array<string, string>
     */
    private const DOMAINS = [
        'linkedin' => 'linkedin.com',
        'github' => 'github.com',
        'gitlab' => 'gitlab.com',
        'x' => 'x.com',
        'twitter' => 'x.com',
        'facebook' => 'facebook.com',
        'instagram' => 'instagram.com',
        'youtube' => 'youtube.com',
        'mastodon' => 'joinmastodon.org',
        'devto' => 'dev.to',
        'dev.to' => 'dev.to',
        'stackoverflow' => 'stackoverflow.com',
        'medium' => 'medium.com',
        'tiktok' => 'tiktok.com',
        'threads' => 'threads.net',
        'bluesky' => 'bsky.app',
        'dribbble' => 'dribbble.com',
        'behance' => 'behance.net',
    ];

    public function handle(): int
    {
        $socialProfiles = SocialProfile::all();

        if ($socialProfiles->isEmpty()) {
            $this->warn('No SocialProfile rows at all — nothing to do.');

            return self::SUCCESS;
        }

        foreach ($socialProfiles as $socialProfile) {
            $key = Str::of($socialProfile->platform)->lower()->replace(' ', '')->toString();
            $domain = self::DOMAINS[$key] ?? null;

            if (! $domain) {
                $this->warn("\"{$socialProfile->platform}\" doesn't match a known platform — leaving it without an icon.");

                continue;
            }

            $path = MediaUploadField::fetchAndStore("https://www.google.com/s2/favicons?domain={$domain}&sz=128");

            if (! $path) {
                $this->warn("Could not fetch an icon for \"{$socialProfile->platform}\" from {$domain} — leaving it without one.");

                continue;
            }

            $media = MediaUploadField::createMediaRecord($path, SocialProfile::class, $socialProfile->id);
            $socialProfile->update(['icon_media_id' => $media->id]);

            $this->info("Attached an icon to \"{$socialProfile->platform}\".");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
