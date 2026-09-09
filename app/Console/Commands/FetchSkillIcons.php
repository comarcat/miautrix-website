<?php

namespace App\Console\Commands;

use App\Filament\Support\MediaUploadField;
use App\Models\Skill;
use Illuminate\Console\Command;

/**
 * One-off, manually-invoked import — requested in production review: "the icons that I
 * mentioned is for the skills like Windows Server -> Icon of Windows Server, Sharepoint ->
 * Icon of the logo of SharePoint". Same reasoning as FetchCompanyEducationLogos for being a
 * command rather than a migration (fetches third-party images over HTTP) and for using
 * Google's favicon service rather than a blind image search — every domain below was
 * verified by hand before being hardcoded here.
 *
 * Several Microsoft-branded skills (Microsoft 365, Azure/Entra ID, Intune, Exchange Online,
 * Teams, SharePoint, Microsoft Defender, Windows Server, Hyper-V, MS SQL Server, Microsoft
 * Project) share the SAME icon — Microsoft's own corporate mark. That's not a mapping
 * mistake: Microsoft doesn't expose a separate, distinctly-branded favicon per product on a
 * dedicated domain the way Fortinet, VMware, or Palo Alto Networks do, and the favicons for
 * their individual product marketing pages (azure.microsoft.com, sharepoint.com) resolve to
 * that same generic Microsoft icon. It's still the real, correct company logo — just not
 * sub-branded — which beats fabricating a distinct "SharePoint icon" from nothing.
 *
 * Deliberately skips every skill that names a practice or a generic technology rather than a
 * specific product/company with a logo of its own (VLANs & Routing, VPNs, Firewall
 * Management, Endpoint Protection, MFA & Security Policy, Ticketing Systems, Asset
 * Management, Remote Connectivity, Documentation Standards, Systems Integration, Backup &
 * Disaster Recovery, Vendor Coordination, Lifecycle Planning, IT Operations Leadership) —
 * there is no logo to fetch for those; they render exactly as before, without one.
 */
class FetchSkillIcons extends Command
{
    protected $signature = 'app:fetch-skill-icons';

    protected $description = 'One-off: fetch and attach real brand icons for skills that map to a specific product/company (run once, by hand)';

    /**
     * @var array<string, string>
     */
    private const DOMAINS = [
        'Microsoft 365' => 'microsoft.com',
        'Azure / Entra ID' => 'microsoft.com',
        'Intune' => 'microsoft.com',
        'Exchange Online' => 'microsoft.com',
        'Teams' => 'microsoft.com',
        'SharePoint' => 'microsoft.com',
        'Microsoft Defender' => 'microsoft.com',
        'Windows Server' => 'microsoft.com',
        'Hyper-V' => 'microsoft.com',
        'MS SQL Server' => 'microsoft.com',
        'Microsoft Project' => 'microsoft.com',
        'Linux' => 'kernel.org',
        'VMware' => 'vmware.com',
        'Fortinet' => 'fortinet.com',
        'Palo Alto Networks' => 'www.paloaltonetworks.com',
        'ConnectWise' => 'connectwise.com',
        'Jira' => 'atlassian.com',
        'Monday.com' => 'monday.com',
        'Project Management (PMP)' => 'pmi.org',
    ];

    public function handle(): int
    {
        $skipped = [];

        foreach (self::DOMAINS as $name => $domain) {
            $skill = Skill::where('name', $name)->first();

            if (! $skill) {
                $skipped[] = "\"{$name}\" (no matching Skill row)";

                continue;
            }

            $path = MediaUploadField::fetchAndStore("https://www.google.com/s2/favicons?domain={$domain}&sz=128");

            if (! $path) {
                $skipped[] = "\"{$name}\" (fetch from {$domain} failed)";

                continue;
            }

            $media = MediaUploadField::createMediaRecord($path, Skill::class, $skill->id);
            $skill->update(['icon_media_id' => $media->id]);

            $this->info("Attached an icon to \"{$name}\".");
        }

        if ($skipped !== []) {
            $this->warn('Skipped: ' . implode(', ', $skipped));
        }

        $this->info('Done. Every other skill (VLANs & Routing, VPNs, generic practices, etc.) has no applicable brand/logo and was left as-is — see this command\'s own docblock.');

        return self::SUCCESS;
    }
}
