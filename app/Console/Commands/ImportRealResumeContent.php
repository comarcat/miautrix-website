<?php

namespace App\Console\Commands;

use App\Models\Certification;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-off, manually-invoked content import — requested in production review: "use my resume
 * to fill up the sections like skills experience education etc". Not a migration on purpose
 * — a migration re-runs on every environment (every teammate's local DB, CI) forever, which
 * would mean committing this real person's real employment history to run automatically
 * everywhere; a command invoked once, by hand, on production only, avoids that.
 *
 * Deliberately excludes: street address and phone number, both on the actual resume — the
 * Profile schema has no fields for either (only `location`, a city/region string), so there
 * was nowhere to put them even if that were desirable for a public page. Certifications
 * without a stated year in the resume are intentionally NOT created here — inventing a
 * precise `issued_at` date (a required column) for a real professional credential would be
 * fabricating data on a public site; those need to be added by hand with their real dates.
 *
 * Safe to re-run: every related table (companies, experiences, education, skills,
 * skill_categories, certifications, social_profiles) is force-deleted and recreated from
 * scratch on each run — a plain soft-delete would leave the old rows physically in place
 * (still colliding with the unique slug constraints these tables have), which is fine here
 * since anything being replaced is either seeded placeholder content or an exact duplicate
 * of what's about to be recreated anyway. The Profile row itself is only ever updated, never
 * deleted.
 */
class ImportRealResumeContent extends Command
{
    protected $signature = 'app:import-real-resume-content';

    protected $description = 'One-off: replace the seeded placeholder profile/skills/experience/education with the real resume content (run once, by hand, in production)';

    public function handle(): int
    {
        $profile = Profile::first();

        if (! $profile) {
            $this->error('No Profile row exists — nothing to attach this content to.');

            return self::FAILURE;
        }

        $this->updateProfile($profile);
        $this->replaceSocialProfiles($profile);
        $this->replaceSkills($profile);
        $this->replaceExperiences($profile);
        $this->replaceEducation($profile);
        $this->createDatedCertifications($profile);

        $this->info('Done. Certifications with no year given in the resume (Dell Storage, Fortinet NSE3, PMP, Cisco CCNA, VMware vCenter/vSphere, SCRUM Fundamentals) were NOT created — add those by hand with their real dates via the admin panel.');

        return self::SUCCESS;
    }

    private function updateProfile(Profile $profile): void
    {
        $profile->update([
            'full_name' => 'Omar (Cristobal) Arboleda Teran',
            'headline' => 'IT Infrastructure & Operations Manager',
            'bio' => "Hands-on IT leader with over 15 years of experience managing IT operations, infrastructure, service delivery, and technical support across multi-site environments. Strong expertise in Microsoft 365, Azure/Entra ID, networking, Fortinet security solutions, virtualization, endpoint management, backups, VPNs, and remote connectivity.\n\nExperienced in leading daily IT operations, improving processes, supporting end users, coordinating vendors and MSPs, and delivering reliable and secure technology services. Proven success implementing operational improvements, strengthening cybersecurity practices, optimizing IT workflows, and managing projects with a 96–98% success rate.\n\nStrong background in IT service management, ticketing systems, onboarding/offboarding processes, documentation, and infrastructure standardization. Recognized for hands-on troubleshooting, team mentorship, and maintaining high service standards in fast-paced environments.",
            'location' => 'Winnipeg, MB, Canada',
            'availability_status' => null,
        ]);
    }

    private function replaceSocialProfiles(Profile $profile): void
    {
        SocialProfile::where('profile_id', $profile->id)->forceDelete();

        SocialProfile::create([
            'profile_id' => $profile->id,
            'platform' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/carboledate/',
            'sort_order' => 0,
        ]);
    }

    /**
     * Categories and groupings straight from the resume's own "Technical Proficiencies"
     * breakdown — it already comes pre-grouped, so this doesn't invent a taxonomy.
     */
    private function replaceSkills(Profile $profile): void
    {
        Skill::where('profile_id', $profile->id)->forceDelete();
        SkillCategory::query()->forceDelete();

        $groups = [
            'Microsoft Technologies' => [
                'Microsoft 365' => 'expert',
                'Azure / Entra ID' => 'expert',
                'Intune' => 'advanced',
                'Exchange Online' => 'advanced',
                'Teams' => 'advanced',
                'SharePoint' => 'advanced',
                'Microsoft Defender' => 'advanced',
            ],
            'Operating Systems & Infrastructure' => [
                'Windows Server' => 'expert',
                'Linux' => 'advanced',
                'VMware' => 'expert',
                'Hyper-V' => 'expert',
            ],
            'Networking & Security' => [
                'Fortinet' => 'expert',
                'Palo Alto Networks' => 'advanced',
                'VLANs & Routing' => 'advanced',
                'VPNs' => 'expert',
                'Firewall Management' => 'expert',
                'Endpoint Protection' => 'advanced',
                'MFA & Security Policy' => 'expert',
            ],
            'Operations & Support' => [
                'ConnectWise' => 'advanced',
                'Ticketing Systems' => 'expert',
                'Asset Management' => 'advanced',
                'Remote Connectivity' => 'expert',
                'Documentation Standards' => 'expert',
            ],
            'Systems & Databases' => [
                'MS SQL Server' => 'intermediate',
                'Systems Integration' => 'advanced',
                'Backup & Disaster Recovery' => 'expert',
            ],
            'Management & Collaboration' => [
                'Jira' => 'advanced',
                'Monday.com' => 'advanced',
                'Microsoft Project' => 'advanced',
                'Vendor Coordination' => 'expert',
                'Lifecycle Planning' => 'advanced',
                'IT Operations Leadership' => 'expert',
                'Project Management (PMP)' => 'expert',
            ],
        ];

        $categorySort = 0;

        foreach ($groups as $categoryName => $skills) {
            $category = SkillCategory::create([
                'name' => $categoryName,
                'sort_order' => $categorySort++,
            ]);

            $skillSort = 0;

            foreach ($skills as $name => $proficiency) {
                Skill::create([
                    'profile_id' => $profile->id,
                    'skill_category_id' => $category->id,
                    'name' => $name,
                    'proficiency' => $proficiency,
                    'sort_order' => $skillSort++,
                ]);
            }
        }
    }

    private function replaceExperiences(Profile $profile): void
    {
        Experience::where('profile_id', $profile->id)->forceDelete();
        Company::query()->forceDelete();

        $roles = [
            [
                'company' => ['name' => 'MSP Corp Prairies (Broadview Networks)'],
                'title' => 'Senior IT Infrastructure Specialist | Mentor & Tier 2/3 Implementation Lead',
                'description' => 'Lead daily IT operations and service delivery across multi-site client environments while mentoring Tier 1–3 technicians. Manage 4–6 concurrent IT initiatives, contributing to large-scale infrastructure upgrades at a 98% project success rate. Support Microsoft 365, Azure/Entra ID, networking, VPNs, firewalls, backups, and endpoints; coordinate vendors, MSP partners, and internal teams; lead escalation management and incident response; and support cybersecurity initiatives including MFA enforcement and secure access policies.',
                'started_at' => '2022-10-01',
                'ended_at' => null,
            ],
            [
                'company' => ['name' => 'FESAECUADOR'],
                'title' => 'IT Project Manager',
                'description' => "Owned the organization's annual IT roadmap, directing multi-site IT operations and delivering 3–4 nationwide infrastructure projects plus 5–6 smaller initiatives at a 96% success rate. Led incident response and MFA/access-control improvements; administered Microsoft 365 (Azure AD/Entra ID, Intune, Exchange Online, Teams, SharePoint); managed VMware/Hyper-V virtualization, network architecture, backups, and DR; led project teams of 3–30 people; improved IT budget efficiency by 80% through lifecycle planning and procurement optimization; and enabled fully remote operations within 4 days during COVID-19.",
                'started_at' => '2013-09-01',
                'ended_at' => '2021-04-01',
            ],
            [
                'company' => ['name' => 'Digital Solutions'],
                'title' => 'IT Project Manager',
                'description' => 'Delivered infrastructure consolidation and virtualization programs (VMware/Hyper-V), reducing hardware costs by 50–80%. Managed enterprise systems, networks, and security controls while mentoring IT staff and coordinating with leadership on technology goals. Implemented VDI environments, improving scalability and lowering operational costs.',
                'started_at' => '2011-11-01',
                'ended_at' => '2013-09-01',
            ],
            [
                'company' => ['name' => 'Nexsys'],
                'title' => 'IT Manager | Regional Project Lead',
                'description' => 'Led the creation and development of the IT department for the Ecuador regional office, establishing standards for infrastructure, security, and service delivery. Directed cross-country programs across Ecuador, Peru, and Colombia, coordinating regional teams and unifying Active Directory structures. Owned elements of the IT roadmap — platform modernization, security alignment, and network redesign — and oversaw budgeting, vendor negotiations, and multi-vendor coordination.',
                'started_at' => '2005-05-01',
                'ended_at' => '2011-08-01',
            ],
        ];

        foreach ($roles as $index => $role) {
            $company = Company::create($role['company']);

            Experience::create([
                'profile_id' => $profile->id,
                'company_id' => $company->id,
                'title' => $role['title'],
                'description' => $role['description'],
                'started_at' => $role['started_at'],
                'ended_at' => $role['ended_at'],
                'slug' => Str::slug($role['company']['name'] . '-' . $role['started_at']),
                'published' => true,
                'sort_order' => $index,
            ]);
        }
    }

    private function replaceEducation(Profile $profile): void
    {
        Education::where('profile_id', $profile->id)->forceDelete();

        $entries = [
            [
                'institution' => 'University of Winnipeg (Professional, Applied & Continuing Education)',
                'degree' => 'Project Management Diploma',
                'field_of_study' => 'Project Management',
                // Resume gives only "2022" for this program — no separate start/end month, so
                // both ends of the span are pinned to that year rather than guessing an exact
                // start date not stated anywhere in the source.
                'started_at' => '2022-01-01',
                'ended_at' => '2022-12-31',
            ],
            [
                'institution' => 'University of Winnipeg (Professional, Applied & Continuing Education)',
                'degree' => 'Management Certificate',
                'field_of_study' => 'Management',
                'started_at' => '2022-01-01',
                'ended_at' => '2022-12-31',
            ],
            [
                'institution' => 'Army Polytechnic School (ESPE), Quito, Ecuador',
                'degree' => 'BS in Computer Science',
                'field_of_study' => 'Systems Engineering',
                // Resume gives only the graduation year (2005) — same reasoning as above.
                'started_at' => '2005-01-01',
                'ended_at' => '2005-12-31',
            ],
        ];

        foreach ($entries as $index => $entry) {
            Education::create([
                'profile_id' => $profile->id,
                'institution' => $entry['institution'],
                'degree' => $entry['degree'],
                'field_of_study' => $entry['field_of_study'],
                'started_at' => $entry['started_at'],
                'ended_at' => $entry['ended_at'],
                'slug' => Str::slug($entry['degree'] . '-' . $entry['ended_at']),
                'published' => true,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Only the 2 certifications the resume actually gives a year for. The other 6
     * (Dell Storage, Fortinet NSE3, PMP Training, Cisco CCNA, VMware vCenter/vSphere, SCRUM
     * Fundamentals) have no date anywhere in the source — `issued_at` is a required column,
     * and guessing one would be fabricating a real credential's date on a public site.
     */
    private function createDatedCertifications(Profile $profile): void
    {
        Certification::where('profile_id', $profile->id)->forceDelete();

        $certifications = [
            [
                'name' => 'Microsoft Expert Level Gold',
                'issuer' => 'Microsoft',
                'issued_at' => '2022-01-01',
            ],
            [
                'name' => 'Azure Stack HCI Accreditation',
                'issuer' => 'Microsoft',
                'issued_at' => '2021-01-01',
            ],
        ];

        foreach ($certifications as $index => $certification) {
            Certification::create([
                'profile_id' => $profile->id,
                'name' => $certification['name'],
                'issuer' => $certification['issuer'],
                // credential_url is a required column, but the resume gives no verification
                // link for either — left blank rather than invented; add the real one by
                // hand if/when it exists.
                'credential_url' => '',
                'issued_at' => $certification['issued_at'],
                'slug' => Str::slug($certification['name'] . '-' . $certification['issued_at']),
                'published' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
