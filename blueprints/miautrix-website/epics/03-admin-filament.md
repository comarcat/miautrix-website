# Epic 03: Admin Portal (Filament)

> After this epic, the single administrator can log in behind MFA and manage 100% of the content
> model — every entity, every image, every resume version — through a generated Filament panel, with
> every mutation writing an audit-log row, and with no public-facing page built yet.

| | |
|---|---|
| **Epic id** | `03-admin-filament` |
| **Tasks** | `E3-T1` … `E3-T5` |
| **Depends on** | `02-schema-auth` |
| **Unlocks** | `04-public-seo-hardening` |
| **Parallel with** | nothing — each resource set depends on the panel existing, and media depends on the resources it attaches to |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · Filament 5 (requires Livewire `^4.1` — the pinned Livewire 4.4 satisfies this; **never
upgrade one without the other**) · `spatie/laravel-medialibrary` · PostgreSQL 18 · Pest 5.

| Task | Command |
|---|---|
| Local services up | `docker compose up -d --wait` |
| Generate a resource | `php artisan make:filament-resource <Model> --generate` |
| Tests (one file) | `./vendor/bin/pest tests/Feature/Admin/X.php` |
| Tinker | `php artisan tinker --execute="..."` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`
passes before any task here is marked done.

If any task verifies against the database, `docker compose up -d --wait` first — `docker-compose.yml`
is already at the project root.

## Directory subtree

```
app/
  Providers/Filament/AdminPanelProvider.php    # NEW (E3-T1)
  Filament/
    Resources/
      {Profile,Company,Experience,Education,Certification}Resource.php   # NEW (E3-T2)
      ProjectResource.php                       # NEW (E3-T3) — includes the SoftwareProject relation manager
      {ProjectCategory,Technology,Skill,SkillCategory}Resource.php        # NEW (E3-T3)
      {Document,SocialProfile,Setting}Resource.php                       # NEW (E3-T5)
    Widgets/*.php                               # NEW (E3-T5)
  Rules/SafeUpload.php                          # NEW (E3-T4)
  Http/Controllers/{MediaController,ResumeDownloadController}.php        # NEW (E3-T4, E3-T5)
  Observers/AuditLogObserver.php                # exists (stubbed in epic 02), wired for real here (E3-T5)
database/migrations/
  *_create_media_table.php                      # NEW — published by spatie/laravel-medialibrary itself (E3-T4)
  *_create_project_media_table.php               # NEW (E3-T4) — deferred from epic 02 because it needs media
database/seeders/AdminUserSeeder.php            # exists (epic 02), unchanged
tests/Feature/Admin/
  PanelAccessTest.php · ResourceSet1Test.php · ResourceSet2Test.php · AuditLogTest.php
tests/Feature/{MediaUploadTest,ResumeDownloadTest}.php
```

Everything outside this subtree is out of scope. If a task seems to require editing a file not
listed here, stop and report — it means the epic boundary is wrong.

## Data model touched here

| Entity | Fields this epic adds or reads | Notes |
|---|---|---|
| `media` | owned entirely by `spatie/laravel-medialibrary`'s own migration | Created in `E3-T4` — every `*_media_id` FK left nullable-no-constraint in epic 02 becomes constrainable here, but the constraint itself is **not retrofitted** onto epic 02's columns in this build (out of scope — the nullable FK already works correctly without the DB-level constraint; adding it is a v2 cleanup, not a v1 requirement) |
| `project_media` | `project_id`, `media_id`, `sort_order` — both cascade | Created in `E3-T4`, per §4's ordering rule |

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| `02-schema-auth` | every content model + `Publishable` trait, `EnsureTwoFactorEnabled`, 14 Policies | Filament resources attach directly — no re-implementation |

**Produced** — later epics depend on exactly these signatures. Changing one breaks them:

| Export | Signature | Used by |
|---|---|---|
| `/admin` panel, MFA-gated | Filament panel at `/admin`, every route behind `auth` + `EnsureTwoFactorEnabled` | operational use only — no later epic's tests depend on the admin UI itself |
| `MediaController::show` | `GET /media/{media}`, policy-checked per referencing entity | `04-public-seo-hardening`'s image gallery/avatar rendering |
| `ResumeDownloadController::show` | `GET /resume/{document}`, atomic `download_count` increment | `04-public-seo-hardening`'s resume page |
| `AuditLogObserver` (now live) | writes one `audit_logs` row per content-model mutation, in the same transaction | operational/audit use; no later epic depends on its signature |

## Conventions that bite in this area

- **Generate, then edit.** Every resource starts as `php artisan make:filament-resource <Model>
  --generate`. Hand-writing one from scratch is the single largest avoidable cost in this build.
- **Max 250 lines per resource.** Extract form/table schemas into
  `app/Filament/Resources/<Model>/Schemas/` before that.
- **Authorization is the Policy, never an inline check inside a resource class.** Filament calls the
  model's Policy automatically.
- **SVG is never an allowed upload type**, at any point, in any field. This is a security control
  (`blueprint.md` §14, risk 4), not a formatting preference — extension AND sniffed MIME are both
  checked.
- **`SoftwareProject` is a relation manager on `ProjectResource`, not a top-level resource** — it is
  a 1-1 extension, not an independent entity a user navigates to directly.
- **Every mutation writes exactly one audit row**, via the observer, inside the same DB transaction —
  never from the resource class itself.
- **Telescope stays behind `app()->environment('local') && config('telescope.enabled')`, both
  conditions, always** — this was set in `E3-T1` and no later task in this epic may loosen it.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/filament.md` applies to every task here;
`.claude/rules/security.md` applies specifically to `E3-T4`'s upload handling.

---

## Tasks

### `E3-T1` — Filament install, panel configuration, MFA gate

**Depends on:** `E2-T7` (bundle prerequisite: `02-schema-auth` complete) · **Priority:** p0

Install Filament, `php artisan filament:install --panels`. `AdminPanelProvider` — panel at `/admin`,
`EnsureTwoFactorEnabled` added to the panel's own middleware stack (do not rely on a global
middleware group), brand colors set from the design tokens (`--color-primary`/`--color-accent` —
final full token application is epic 04's job; here it's enough to reference the two brand colors).
Telescope registered only behind the double condition above. No resources yet.

**Files**
- `app/Providers/Filament/AdminPanelProvider.php` — new
- `tests/Feature/Admin/PanelAccessTest.php` — new

**Acceptance**

1. **WHEN** an unauthenticated request hits `/admin` **THE SYSTEM SHALL** redirect to `/admin/login`.
2. **WHEN** the seeded admin (MFA unconfirmed) logs in **THE SYSTEM SHALL** redirect to
   `/admin/two-factor-setup` before reaching the dashboard.
3. **WHEN** `APP_ENV=production` and `/telescope` is requested **THE SYSTEM SHALL** respond `404`.
4. **WHEN** `APP_ENV=local` and `TELESCOPE_ENABLED=true` and `/telescope` is requested by an
   authenticated admin **THE SYSTEM SHALL** respond `200`.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Admin/PanelAccessTest.php
APP_ENV=production php artisan tinker --execute="var_dump(config('telescope.enabled'));"
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T1: filament panel + mfa gate + telescope local-only"
git tag step-13-filament-panel
```

### `E3-T2` — Filament resources, set 1

**Depends on:** `E3-T1` · **Priority:** p0

`make:filament-resource --generate` for Profile, Company, Experience, Education, Certification, then
edit each per `.claude/rules/filament.md`: real field types (date pickers for date columns, rich text
for long-text fields), `published`/`featured` as toggles in the table, bulk actions soft-delete only.

**Files**
- `app/Filament/Resources/{Profile,Company,Experience,Education,Certification}Resource.php` — new
- `tests/Feature/Admin/ResourceSet1Test.php` — new

**Acceptance**

1. **WHEN** `/admin/profiles`, `/admin/companies`, `/admin/experiences`, `/admin/education`,
   `/admin/certifications` are requested by an authenticated `super_admin` **THE SYSTEM SHALL**
   respond `200` and list the seeded rows.
2. **WHEN** a record is created through any of these 5 resources **THE SYSTEM SHALL** persist it
   with `published=false` by default.
3. **WHEN** a bulk-delete action is invoked **THE SYSTEM SHALL** soft-delete, never hard-delete.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Admin/ResourceSet1Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T2: filament resources - profile/company/experience/education/certification"
git tag step-14-filament-set1
```

### `E3-T3` — Filament resources, set 2

**Depends on:** `E3-T2` · **Priority:** p0

`make:filament-resource --generate` for Project, ProjectCategory, Technology, Skill, SkillCategory.
`ProjectResource`'s form includes a `BelongsToMany` picker for technologies (writes
`project_technologies`) and a toggle that reveals the `SoftwareProject` relation-manager fields
(`repository_url`, `live_url`, `language`, `license`, `is_open_source`) — implemented as a relation
manager, not a separate top-level resource.

**Files**
- `app/Filament/Resources/{Project,ProjectCategory,Technology,Skill,SkillCategory}Resource.php` — new
- `tests/Feature/Admin/ResourceSet2Test.php` — new

**Acceptance**

1. **WHEN** `/admin/projects`, `/admin/project-categories`, `/admin/technologies`, `/admin/skills`,
   `/admin/skill-categories` are requested **THE SYSTEM SHALL** respond `200`.
2. **WHEN** a `Project` is edited to attach 3 technologies **THE SYSTEM SHALL** write exactly 3
   `project_technologies` rows.
3. **WHEN** the software-project toggle is enabled and saved with a `repository_url` **THE SYSTEM
   SHALL** create the corresponding `software_projects` row.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Admin/ResourceSet2Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T3: filament resources - projects/software/categories/technologies/skills"
git tag step-15-filament-set2
```

### `E3-T4` — Media library and hardened uploads

**Depends on:** `E3-T3` · **Priority:** p0

Install `spatie/laravel-medialibrary`, publish and run **its own** migration (creates `media`).
`SafeUpload` validation rule checks file extension **and** sniffed MIME against `{jpeg, png, webp,
pdf, docx, zip}` — **SVG is never in the list**, regardless of what it declares itself to be. Raster
images are re-encoded through Intervention Image (a medialibrary dependency) to strip embedded
script-bearing metadata. `MediaController::show` serves from the `media_private` disk (outside
`public/`), authorizing via the referencing model's Policy. Migration for `project_media` (deferred
from epic 02, legal now that `media` exists).

**Files**
- `app/Rules/SafeUpload.php` — new
- `app/Http/Controllers/MediaController.php` — new
- `database/migrations/*_create_project_media_table.php` — new
- `tests/Feature/MediaUploadTest.php` — new

**Acceptance**

1. **WHEN** a file with a `.svg` extension is uploaded through any Filament media field **THE SYSTEM
   SHALL** reject it, regardless of its declared MIME type.
2. **WHEN** a renamed-extension file (PNG name, script-bearing bytes) is uploaded **THE SYSTEM
   SHALL** reject it via the sniffed MIME check.
3. **WHEN** a valid JPEG is uploaded **THE SYSTEM SHALL** store it on the `media_private` disk under
   a generated filename and it **SHALL NOT** be reachable at a guessable public URL.
4. **WHEN** an authenticated non-admin request for `/media/{id}` targets a media row referenced only
   by an unpublished project **THE SYSTEM SHALL** respond `403`.
5. **WHEN** `project_media` is queried after this task's migration **THE SYSTEM SHALL** exist with
   the `(project_id, media_id, sort_order)` shape.

**Verify**

```bash
php artisan migrate --force
./vendor/bin/pest tests/Feature/MediaUploadTest.php
php artisan tinker --execute="var_dump(Schema::hasTable('project_media'));"
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T4: media library + hardened uploads (no svg) + project_media pivot"
git tag step-16-media
```

### `E3-T5` — Documents, social profiles, settings, audit log, dashboard widgets

**Depends on:** `E3-T4` · **Priority:** p1

`ResumeDownloadController::show` — the `/resume/{document}` route: 404 if unpublished and
unauthenticated, atomic `increment()` on `download_count`. Filament resources for Document,
SocialProfile, Setting. A read-only audit-log viewer resource (no create/edit/delete action anywhere
on it — the model is append-only). Wire `AuditLogObserver` for real: `created`/`updated`/`deleted` on
every content model writes one `audit_logs` row in the same transaction, `before`/`after` redacted of
any `password`/`token`/`secret`-named field. Dashboard widgets (published/draft counts, download
totals, recent audit entries) query through a cached Action, not directly in `getData()`.

**Files**
- `app/Http/Controllers/ResumeDownloadController.php` — new
- `app/Filament/Resources/{Document,SocialProfile,Setting}Resource.php` — new
- `app/Filament/Widgets/*.php` — new
- `app/Observers/AuditLogObserver.php` — edit (from stub to real writes)
- `tests/Feature/Admin/AuditLogTest.php` — new
- `tests/Feature/ResumeDownloadTest.php` — new

**Acceptance**

1. **WHEN** a `Project` is updated through Filament **THE SYSTEM SHALL** write exactly one
   `audit_logs` row in the same database transaction, with the actor id and a redacted diff.
2. **WHEN** `/resume/{document}` is requested for a published, current resume **THE SYSTEM SHALL**
   stream the file and increment `download_count` by exactly 1, atomically under concurrent requests.
3. **WHEN** the audit log viewer is opened **THE SYSTEM SHALL** show no create/edit/delete action
   anywhere on the page.
4. **WHEN** the dashboard is opened **THE SYSTEM SHALL** show the published/draft project counts
   matching a direct database count.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Admin/AuditLogTest.php
./vendor/bin/pest tests/Feature/ResumeDownloadTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E3-T5: documents/social/settings resources + audit observer + dashboard widgets"
git tag step-17-admin-complete
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a `super_admin` logs in, enrolls MFA, publishes a project with 2 technologies and 1
   image, and downloads the resume **THE SYSTEM SHALL** complete every step with a `200`/redirect
   success and leave exactly the audit-log rows those mutations produce.
2. **WHEN** an unauthenticated or unauthorized request targets any `/admin/*` resource **THE SYSTEM
   SHALL** be denied.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build
./vendor/bin/pest tests/Feature/Admin
```

## Pitfalls

- **Building `SoftwareProject` as a standalone Filament resource.** It is a relation manager on
  `ProjectResource` — a separate top-level resource for a 1-1 extension table invites the STI
  confusion §4 explicitly rejected.
- **Allowing SVG "just for icons."** There is no exception. Icons are uploaded as PNG/WebP or served
  from the bundled Heroicons/Lucide set, never as user-uploaded SVG.
- **Writing the audit row from inside the resource class.** It belongs in the model observer so it
  fires uniformly regardless of which code path mutates the model (Filament today, a future console
  command tomorrow).
- **Forgetting the double condition on Telescope.** `environment('local')` alone is not enough if
  `TELESCOPE_ENABLED` defaults true somewhere; both must be checked, always.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-13-filament-panel`
      through `step-17-admin-complete`. `git tag -l 'step-1[3-7]-*'` lists all 5.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` updated if this epic added a variable — it did not; `MEDIA_DISK` was already
      present from `01-foundation`'s Bootstrap.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
