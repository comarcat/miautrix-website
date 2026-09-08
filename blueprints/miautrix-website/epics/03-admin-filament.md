# Epic 03: Admin Portal (Filament)

> After this epic, the single administrator can log in behind mandatory MFA and manage every content
> entity — career, projects, media, documents, settings, and the blog — through a generated Filament
> panel, with hardened uploads and no public-facing page yet built.

| | |
|---|---|
| **Epic id** | `03-admin-filament` |
| **Tasks** | `E3-T1` … `E3-T6` |
| **Depends on** | `02-schema-auth` |
| **Unlocks** | `04-public-themes` |
| **Parallel with** | nothing — the public site's content comes from what this epic lets the admin publish |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

filament/filament `^5.7` (requires livewire/livewire `^4.1`; pinned `^4.4` satisfies it) ·
laravel/telescope `^5.23` (local-only) · spatie/laravel-medialibrary `^11.23` · Pest `^5.1`.

| Task | Command |
|---|---|
| Generate a resource | `php artisan make:filament-resource {Model} --generate` |
| Generate a relation manager | `php artisan make:filament-relation-manager {Resource} {relation} {Model}` |
| Test (one file) | `./vendor/bin/pest {path}` |
| Local Postgres up/down | `docker compose up -d` / `docker compose down` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest` passes
before any task here is marked done.

## Directory subtree

```
app/
  Providers/Filament/AdminPanelProvider.php   # NEW E3-T1
  Filament/
    Resources/
      ProfileResource.php CompanyResource.php ExperienceResource.php
      EducationResource.php CertificationResource.php                # E3-T2
      ProjectResource.php ProjectResource/RelationManagers/SoftwareProjectRelationManager.php
      ProjectCategoryResource.php TechnologyResource.php SkillResource.php  # E3-T3 — NEVER a
                                                                             # standalone SoftwareProjectResource.php
      DocumentResource.php SocialProfileResource.php SettingResource.php AuditLogResource.php  # E3-T5
      ArticleResource.php                                                  # E3-T6
    Widgets/RecentActivityWidget.php                                       # E3-T5
    Schemas/HasSeoFields.php                                               # E3-T6 — shared trait, retrofit into E3-T2's resources
  Http/Controllers/Public/MediaController.php   # NEW E3-T4
  Rules/AllowedMediaMime.php                    # NEW E3-T4
tests/Feature/
  Filament/          # E3-T1, T2, T3, T5, T6
  Media/             # E3-T4
```

Everything outside this subtree is out of scope.

## Data model touched here

`articles` (new in `E3-T6`, per blueprint §4). Every other entity was created in epic 02 — this epic
only adds the Filament resources on top; no other schema changes.

## Contracts

**Consumed:**

| From | Interface | Guarantee |
|---|---|---|
| `02-schema-auth` | Every Eloquent model + Policy + `EnsureMfaConfirmed` middleware | Resources can be generated and authorized safely |

**Produced:**

| Export | Signature | Used by |
|---|---|---|
| A fully functional `/admin` panel | Filament resources for every content entity | The administrator, from launch onward |
| `MediaController` | `GET /media/{media}/{filename}` — 404s unless the owning entity is published | `04-public-themes`'s public pages that render images |
| `Article` model + `ArticleResource` | RichEditor body, publish toggle | `04-public-themes`'s blog pages, `05-seo-hardening`'s sitemap/feed |

## Conventions that bite in this area

- **SoftwareProject is a Filament relation manager on `ProjectResource`, never a standalone resource
  file.** `E3-T3`'s Verify explicitly asserts no `SoftwareProjectResource.php` exists.
- **Resources are generated, then edited — never hand-written from scratch.** Run the generator
  first every time.
- **No SVG anywhere in an upload field.** `E3-T4` removes it entirely, not just discourages it.
- **`HasSeoFields` is extracted once (`E3-T6`) and retrofitted into the earlier resources** (Profile,
  Experience, Education, Certification, Project) so the 6 SEO fields are never duplicated 5+ times.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/filament.md`, `.claude/rules/security.md`.

---

## Tasks

### `E3-T1` — Filament install, panel config, admin user, MFA enforced, Telescope local-only

**Depends on:** nothing in this epic (epic-level: `E2-T7`) · **Priority:** p0

`composer require filament/filament:^5.7`, `php artisan filament:install --panels`. Register
`EnsureMfaConfirmed` as panel middleware. Gate Telescope's `TelescopeServiceProvider::register()`
behind `app()->environment('local')`.

**Files**
- `app/Providers/Filament/AdminPanelProvider.php` — new
- `tests/Feature/Filament/PanelBootTest.php` — new

**Acceptance**

1. **WHEN** `/admin/login` is requested **THE SYSTEM SHALL** render the Filament login form.
2. **WHEN** the seeded `super_admin` logs in with MFA unconfirmed **THE SYSTEM SHALL** be redirected to MFA enrolment before reaching the dashboard.
3. **WHEN** `/telescope` is requested in a non-local environment **THE SYSTEM SHALL** return 404.
4. **WHEN** `tests/Feature/Filament/PanelBootTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Filament/PanelBootTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T1: filament panel install with mfa enforcement, telescope local-only"
git tag step-13-filament-install
```

### `E3-T2` — Filament resources set 1: Profile, Companies, Experience, Education, Certifications

**Depends on:** `E3-T1` · **Priority:** p0

Generate the 5 resources, then edit each to wire the publishable-entity fields (slug, published
toggle, featured toggle, SEO fields section).

**Files**
- `app/Filament/Resources/ProfileResource.php` — new (generated then edited)
- `app/Filament/Resources/CompanyResource.php` — new
- `app/Filament/Resources/ExperienceResource.php` — new
- `app/Filament/Resources/EducationResource.php` — new
- `app/Filament/Resources/CertificationResource.php` — new
- `tests/Feature/Filament/ResourceSet1Test.php` — new

**Acceptance**

1. **WHEN** each of the 5 resources' index page is requested by the authenticated `super_admin` **THE SYSTEM SHALL** return 200.
2. **WHEN** a new `Experience` is created through the Filament form **THE SYSTEM SHALL** persist a row with a non-null `slug` derived from `title`.
3. **WHEN** the `published` toggle is switched off **THE SYSTEM SHALL** cause the entity to disappear from any public listing query.
4. **WHEN** `tests/Feature/Filament/ResourceSet1Test.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Filament/ResourceSet1Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T2: filament resources — profile, companies, experience, education, certifications"
git tag step-14-filament-resources-1
```

### `E3-T3` — Filament resources set 2: Projects (+SoftwareProject relation manager), categories, tech, skills

**Depends on:** `E3-T2` · **Priority:** p0

Generate `ProjectResource` and add a relation manager for `SoftwareProject` on it — never a
standalone resource. Generate `ProjectCategoryResource`, `TechnologyResource`, `SkillResource`,
`SkillCategoryResource`.

**Files**
- `app/Filament/Resources/ProjectResource.php` — new
- `app/Filament/Resources/ProjectResource/RelationManagers/SoftwareProjectRelationManager.php` — new
- `app/Filament/Resources/ProjectCategoryResource.php` — new
- `app/Filament/Resources/TechnologyResource.php` — new
- `app/Filament/Resources/SkillResource.php` — new
- `app/Filament/Resources/SkillCategoryResource.php` — new
- `tests/Feature/Filament/ResourceSet2Test.php` — new

**Acceptance**

1. **WHEN** `app/Filament/Resources/` is listed **THE SYSTEM SHALL** contain no file named `SoftwareProjectResource.php`.
2. **WHEN** `ProjectResource`'s edit page is requested **THE SYSTEM SHALL** render a Software Details relation-manager tab.
3. **WHEN** a `Project` is attached to `Technology` rows through the Filament form **THE SYSTEM SHALL** persist the pivot rows.
4. **WHEN** `tests/Feature/Filament/ResourceSet2Test.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped, including one asserting `SoftwareProjectResource` is not a registered Filament resource class.

**Verify**

```bash
! find app/Filament/Resources -iname "SoftwareProjectResource.php" | grep -q .
./vendor/bin/pest tests/Feature/Filament/ResourceSet2Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T3: filament resources — projects (software relation manager), categories, tech, skills"
git tag step-15-filament-resources-2
```

### `E3-T4` — Media library: hardened upload validation, storage outside web root

**Depends on:** `E3-T3` · **Priority:** p0

Configure the media disk under `storage/app/private-media/`. Allowed types: JPEG, PNG, WebP, PDF,
DOCX, ZIP only — no SVG. Validation: extension → sniffed MIME → re-encode. `MediaController` serves
files only for published owning entities.

**Files**
- `app/Http/Controllers/Public/MediaController.php` — new
- `app/Rules/AllowedMediaMime.php` — new
- `config/filesystems.php` — edit: add the `private-media` disk
- `tests/Feature/Media/UploadValidationTest.php` — new

**Acceptance**

1. **WHEN** a `.svg` file is uploaded through any Filament FileUpload field **THE SYSTEM SHALL** reject it, never storing it.
2. **WHEN** a file with a spoofed extension is uploaded **THE SYSTEM SHALL** reject it based on sniffed MIME.
3. **WHEN** a valid JPEG is uploaded **THE SYSTEM SHALL** store it re-encoded under `storage/app/private-media/` with a UUID-prefixed filename.
4. **WHEN** `/media/{media}/{filename}` is requested for media belonging to an unpublished entity **THE SYSTEM SHALL** return 404.
5. **WHEN** `tests/Feature/Media/UploadValidationTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Media/UploadValidationTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T4: hardened media library — no svg, sniffed mime, storage outside webroot"
git tag step-16-media-hardening
```

### `E3-T5` — Documents/Resume, Social Profiles, Settings, Audit Log viewer, dashboard widgets

**Depends on:** `E3-T4` · **Priority:** p0

Generate `DocumentResource` (versioned resume), `SocialProfileResource`, `SettingResource`, a
read-only `AuditLogResource`. Add 2 dashboard widgets.

**Files**
- `app/Filament/Resources/DocumentResource.php` — new
- `app/Filament/Resources/SocialProfileResource.php` — new
- `app/Filament/Resources/SettingResource.php` — new
- `app/Filament/Resources/AuditLogResource.php` — new
- `app/Filament/Widgets/RecentActivityWidget.php` — new
- `tests/Feature/Filament/ResourceSet3Test.php` — new

**Acceptance**

1. **WHEN** a new `Document` version is uploaded **THE SYSTEM SHALL** increment `version` and keep the prior version's media row intact.
2. **WHEN** `/documents/{document}/download` is requested for a published document **THE SYSTEM SHALL** increment `download_count` by exactly 1 per request.
3. **WHEN** a `super_admin` attempts to delete an `AuditLog` row via Filament **THE SYSTEM SHALL** deny the action.
4. **WHEN** the admin dashboard is requested **THE SYSTEM SHALL** render both widgets with real counts.
5. **WHEN** `tests/Feature/Filament/ResourceSet3Test.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Filament/ResourceSet3Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T5: documents, social profiles, settings, audit log viewer, dashboard widgets"
git tag step-17-admin-remainder
```

### `E3-T6` — Blog admin: Filament Article resource

**Depends on:** `E3-T5` · **Priority:** p0

**2026-09-08 note:** the `articles` migration, `App\Models\Article`, and its `published()`
scope were built in E2-T6 instead — E2-T6's own `ArticlePolicy` and E2-T7's article seeding
both needed the table to exist much earlier than this task's original position in the bundle.
Already verified there (migrate creates `articles` per §4; `Article::published()` correctly
gates on `published_at`, null/future excluded, past included). This task is Filament-resource-only.

Generate `ArticleResource` with a RichEditor, a publish/unpublish toggle bound to
`published_at`, `featured` toggle. Extract `HasSeoFields` and retrofit it into the 5 resources
from `E3-T2`/`E3-T3` that need it.

**Files**
- `app/Filament/Resources/ArticleResource.php` — new
- `app/Filament/Schemas/HasSeoFields.php` — new
- `tests/Feature/Filament/ArticleResourceTest.php` — new

**Acceptance**

1. **WHEN** the RichEditor body is saved **THE SYSTEM SHALL** persist sanitized HTML with script tags stripped.
2. **WHEN** `tests/Feature/Filament/ArticleResourceTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Filament/ArticleResourceTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T6: blog article schema and filament resource"
git tag step-18-blog-admin
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** the seeded `super_admin` logs in behind MFA **THE SYSTEM SHALL** be able to create, publish, and unpublish an instance of every content entity in blueprint §4, including an Article.
2. **WHEN** an SVG is offered to any upload field anywhere in the panel **THE SYSTEM SHALL** reject it.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
```

## Pitfalls

- **Hand-writing a Filament resource from scratch.** Always generate first, then edit.
- **A standalone `SoftwareProjectResource.php`.** This is the single most explicitly-guarded
  convention in this blueprint — `E3-T3`'s Verify fails the build if that file exists.
- **Letting Telescope run in production.** It fills the database fast; the gate is local-only.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-13-filament-install`
      through `step-18-blog-admin`.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged unless this epic added a variable.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
