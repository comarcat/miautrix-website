# Epic 02: Schema, Auth & Authorization

> After this epic, the full data model exists in PostgreSQL, a single administrator can log in behind
> mandatory TOTP MFA, every content model is guarded by a Policy, and a realistic demo portfolio is
> seeded — with nothing yet exposed through any UI.

| | |
|---|---|
| **Epic id** | `02-schema-auth` |
| **Tasks** | `E2-T1` … `E2-T7` |
| **Depends on** | `01-foundation` |
| **Unlocks** | `03-admin-filament` |
| **Parallel with** | nothing — every task here builds on the previous migration |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP 8.4+ · PostgreSQL 18 · Eloquent (no repository layer) · Laravel Fortify (headless
auth, TOTP MFA) · `spatie/laravel-permission` (roles/policies) · Pest 5, run against a real
PostgreSQL `miautrix_test` database (never SQLite).

| Task | Command |
|---|---|
| Local services up | `docker compose up -d --wait` |
| Full reset (**local only**) | `php artisan migrate:rollback --step=1000 --force && php artisan migrate --force` |
| Migrate (normal) | `php artisan migrate --force` |
| Seed | `php artisan db:seed --force` |
| Tests (one file) | `./vendor/bin/pest tests/Feature/X.php` |
| Tinker (inspect state) | `php artisan tinker --execute="..."` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`
passes before any task here is marked done. The full-reset command above (rollback everything, then
re-migrate) is used only against the local/test database, never against anything resembling
production — `.claude/settings.json` separately denies `php artisan db:wipe` outright and denies any
`migrate:fresh` invocation, so this project standardizes on rollback+migrate for every "start clean"
need in this epic's `Verify` blocks.

`docker-compose.yml` provisions PostgreSQL 18 and Redis 8 locally and is already at the project root.
Start it before any task below if it is not already running.

## Directory subtree

```
database/
  migrations/                       # NEW — one per table, filenames chosen by `make:migration`
  factories/                        # NEW — one per content model (E2-T7)
  seeders/
    RoleSeeder.php                  # NEW (E2-T6)
    AdminUserSeeder.php              # NEW (E2-T7)
    DemoContentSeeder.php            # NEW (E2-T7)
app/
  Models/
    Concerns/Publishable.php        # NEW (E2-T1) — the shared slug/published/featured/sort_order/SEO trait
    User.php                        # exists (scaffold), edited (E2-T1, E2-T5)
    Profile.php                     # NEW (E2-T1)
    {Company,Experience,Education,Certification,SkillCategory,Skill,Technology}.php   # NEW (E2-T2)
    {ProjectCategory,Project,SoftwareProject}.php                                      # NEW (E2-T3)
    {Document,SocialProfile,Setting,AuditLog,Import,ImportRecord}.php                  # NEW (E2-T4)
  Contracts/
    PortfolioIntegration.php         # NEW (E2-T4) — zero implementations, the v1.1 seam
    AnalyticsProviderInterface.php   # NEW (E2-T4)
  Support/Analytics/NullAnalyticsProvider.php   # NEW (E2-T4)
  Observers/AuditLogObserver.php     # NEW, stubbed (E2-T4) — wired for real in epic 03
  Providers/
    AppServiceProvider.php           # edited (E2-T1) — registers the publishable() Blueprint macro
    FortifyServiceProvider.php       # NEW (E2-T5)
  Http/Middleware/EnsureTwoFactorEnabled.php   # NEW (E2-T5)
  Listeners/LogLoginAttempt.php       # NEW (E2-T5)
  Policies/*.php                     # NEW (E2-T6) — 14 total, one per content model
tests/Feature/
  ProfileSchemaTest.php · CareerSchemaTest.php · ProjectSchemaTest.php · MediaDocumentSchemaTest.php
  Auth/{MfaEnforcementTest,RateLimitTest,LoginAuditTest}.php
  Authorization/{PolicyDenialTest,PolicyCoverageTest}.php
```

Everything outside this subtree is out of scope. If a task seems to require editing a file not
listed here, stop and report — it means the epic boundary is wrong.

## Data model touched here

Every entity in `blueprint.md` §4 except `media` (created by `spatie/laravel-medialibrary`'s own
migration in epic 03) and `project_media`/`project_documents` (deferred to epic 03 for the same
reason). See §4 for the full field list, constraints and indexes — this epic implements it exactly,
table by table, in the order the step map fixes (identity → career → projects → support tables).

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| `01-foundation` | `https://$APP_DOMAIN/` deployed, CI green on `main` | Every migration this epic writes is deployable and CI-tested from the first task onward |

**Produced** — later epics depend on exactly these signatures. Changing one breaks them:

| Export | Signature | Used by |
|---|---|---|
| `Profile`, `Publishable` trait | every content model applies it: `slug`, `published`, `featured`, `sort_order`, SEO fields | `03-admin-filament` (every resource), `04-public-seo-hardening` (every public query) |
| `EnsureTwoFactorEnabled` middleware | gates any route it's attached to on `two_factor_confirmed_at` non-null | `03-admin-filament`'s `AdminPanelProvider` |
| 14 `app/Policies/*Policy.php` | one per content model, `super_admin` full access, everyone else denied | `03-admin-filament` — Filament auto-discovers and enforces these |
| `AnalyticsProviderInterface` → `NullAnalyticsProvider` | no-op, no HTTP call | `04-public-seo-hardening` binds nothing further in v1 |
| `PortfolioIntegration` interface | zero implementations | left open for `blueprint.md` §20.4's v2 backlog, never implemented in this build |

## Conventions that bite in this area

- **Never invent a migration filename.** Create with `php artisan make:migration`, refer to it as
  "the migration that command emitted" in any commit message or comment.
- **The `publishable()` Blueprint macro is registered once** (`E2-T1`, in `AppServiceProvider::boot()`)
  and every subsequent content migration calls it — never repeat its 9 columns by hand in a later
  migration.
- **`media`-referencing foreign keys wait.** Any column that would reference `media.id`
  (`avatar_media_id`, `og_image_id`, etc.) is declared **nullable with no FK constraint yet** in this
  epic's migrations — the constraint is added once `media` exists, in epic 03's `E3-T4`. Do not add a
  `foreignId(...)->constrained('media')` here; `media` does not exist and the migration will fail.
- **Every model sets `$fillable` explicitly.** `$guarded = []` is banned.
- **No repository layer.** Real logic that isn't a simple Eloquent operation goes in `app/Actions/`
  (not used yet in this epic — the schema/auth/authz layer needs none).
- **Delete behavior is per §4, not by convention.** `restrict` and `cascade` are declared explicitly
  per foreign key in the migration — do not default to cascade because it's less code.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/database.md` applies to every task here.

---

## Tasks

### `E2-T1` — Core identity and profile schema

**Depends on:** `E1-T5` (bundle prerequisite: `01-foundation` complete) · **Priority:** p0

Extend the scaffold's `users` migration with `last_login_at`/`last_login_ip` (Fortify's own
`two_factor_*` columns are added when Fortify installs in `E2-T5` — do not add them here). Create
`profiles` with every column `blueprint.md` §4 lists. Register the `publishable()` Blueprint macro in
`AppServiceProvider::boot()` exactly as specified in §4's Schema subsection. `Profile` model:
`belongsTo(User)`, applies the `Publishable` concern trait, explicit `$fillable`.

**Files**
- `database/migrations/*_create_profiles_table.php` — new
- `app/Models/User.php` — edit (add `last_login_at`/`last_login_ip` migration + accessors)
- `app/Models/Profile.php` — new
- `app/Models/Concerns/Publishable.php` — new
- `app/Providers/AppServiceProvider.php` — edit (register the macro)
- `tests/Feature/ProfileSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate --force` runs on an empty database **THE SYSTEM SHALL** create
   `profiles` with every column §4 lists, plus the `publishable()` macro columns.
2. **WHEN** a `Profile` is saved without a `slug` **THE SYSTEM SHALL** fail at the database's
   not-null constraint.
3. **WHEN** `Profile::published()` is queried on a table with one published and one unpublished row
   **THE SYSTEM SHALL** return exactly the published one.

**Verify**

```bash
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force
php artisan tinker --execute="var_dump(Schema::hasColumns('profiles', ['slug','published','featured','sort_order','seo_title','og_image_id']));"
./vendor/bin/pest --filter=ProfileSchemaTest
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T1: core identity + profile schema"
git tag step-06-schema-core
```

### `E2-T2` — Career schema

**Depends on:** `E2-T1` · **Priority:** p0

Migrations + models for `companies`, `experiences`, `education`, `certifications`, `skill_categories`,
`skills`, `technologies` — exact columns, FKs and indexes from §4, including the `level` 1-5 database
check constraint on `skills` and the `(profile_id, started_on desc)` index on `experiences`. Every
publishable entity here applies the same `publishable()` macro from `E2-T1` — do not redefine it.

**Files**
- `database/migrations/*_create_companies_table.php` — new
- `database/migrations/*_create_experiences_table.php` — new
- `database/migrations/*_create_education_table.php` — new
- `database/migrations/*_create_certifications_table.php` — new
- `database/migrations/*_create_skills_tables.php` — new (skill_categories + skills)
- `database/migrations/*_create_technologies_table.php` — new
- `app/Models/{Company,Experience,Education,Certification,SkillCategory,Skill,Technology}.php` — new
- `tests/Feature/CareerSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate --force` runs **THE SYSTEM SHALL** create all 7 tables with every
   FK from §4's relationship list.
2. **WHEN** a `Skill` is saved with `level=6` **THE SYSTEM SHALL** fail the database check
   constraint.
3. **WHEN** a `Company` with a related `Experience` is deleted **THE SYSTEM SHALL** fail (restrict),
   not cascade.

**Verify**

```bash
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force
./vendor/bin/pest --filter=CareerSchemaTest
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T2: career schema"
git tag step-07-schema-career
```

### `E2-T3` — Project schema

**Depends on:** `E2-T2` · **Priority:** p0

Migrations + models for `project_categories`, `projects`, `software_projects` (the 1-1 **extension**
table — a project is a software project when a matching row exists here, not via STI), and the
`project_technologies` pivot. `project_media` and `project_documents` are **not** created here — §4
requires them to wait until `media` exists (epic 03).

**Files**
- `database/migrations/*_create_project_categories_table.php` — new
- `database/migrations/*_create_projects_table.php` — new
- `database/migrations/*_create_software_projects_table.php` — new
- `database/migrations/*_create_project_technologies_table.php` — new
- `app/Models/{ProjectCategory,Project,SoftwareProject}.php` — new
- `tests/Feature/ProjectSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate --force` runs **THE SYSTEM SHALL** create `project_categories`,
   `projects`, `software_projects` (unique `project_id`), `project_technologies`, and **SHALL NOT**
   create `project_media` or `project_documents` at this step.
2. **WHEN** a `Project` is deleted **THE SYSTEM SHALL** cascade-delete its `software_projects` row
   and its `project_technologies` rows.
3. **WHEN** a `ProjectCategory` with a related `Project` is deleted **THE SYSTEM SHALL** fail
   (restrict).

**Verify**

```bash
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force
php artisan tinker --execute="var_dump(Schema::hasTable('project_media'));"
./vendor/bin/pest --filter=ProjectSchemaTest
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T3: project schema"
git tag step-08-schema-projects
```

### `E2-T4` — Media/document/social/settings/audit/import schema

**Depends on:** `E2-T3` · **Priority:** p0

Migrations + models for `documents` (partial unique index on `(profile_id, kind) where is_current`),
`social_profiles`, `settings`, `audit_logs` (append-only — no `updated_at`, no soft-delete),
`imports`, `import_records`. `PortfolioIntegration` and `AnalyticsProviderInterface` contracts with a
`NullAnalyticsProvider` bound in the container. Stub `AuditLogObserver` (registered, no-op — wired
for real writes in epic 03's `E3-T5`).

**Files**
- `database/migrations/*_create_documents_table.php` — new
- `database/migrations/*_create_social_profiles_table.php` — new
- `database/migrations/*_create_settings_table.php` — new
- `database/migrations/*_create_audit_logs_table.php` — new
- `database/migrations/*_create_imports_tables.php` — new (imports + import_records)
- `app/Models/{Document,SocialProfile,Setting,AuditLog,Import,ImportRecord}.php` — new
- `app/Contracts/{PortfolioIntegration,AnalyticsProviderInterface}.php` — new
- `app/Support/Analytics/NullAnalyticsProvider.php` — new
- `app/Observers/AuditLogObserver.php` — new, stubbed
- `tests/Feature/MediaDocumentSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate --force` runs **THE SYSTEM SHALL** create all 6 tables, and
   `audit_logs` **SHALL** have no `deleted_at` column.
2. **WHEN** two `documents` rows for the same `(profile_id, kind)` both set `is_current=true` **THE
   SYSTEM SHALL** fail the partial unique index.
3. **WHEN** `app()->make(AnalyticsProviderInterface::class)` resolves **THE SYSTEM SHALL** return
   `NullAnalyticsProvider`, and calling any of its methods **SHALL** be a no-op with no HTTP call.
4. **WHEN** `PortfolioIntegration` is checked for concrete implementations **THE SYSTEM SHALL** find
   zero.

**Verify**

```bash
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force
./vendor/bin/pest --filter=MediaDocumentSchemaTest
php artisan tinker --execute="var_dump(get_class(app(App\Contracts\AnalyticsProviderInterface::class)));"
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T4: media/document/social/settings/audit/import schema"
git tag step-09-schema-support
```

### `E2-T5` — Authentication: Fortify and mandatory MFA

**Depends on:** `E2-T4` · **Priority:** p0

Install Fortify, configure TOTP two-factor as **required** (never optional), login rate limiting
(5/minute per IP+email), custom login/two-factor Blade views (matching the design tokens — final
styling lands in epic 04, plain markup is fine here). `EnsureTwoFactorEnabled` middleware redirects to
`/admin/two-factor-setup` when unconfirmed. `LogLoginAttempt` listener writes one `audit_logs` row per
login success/failure via the (now-wired) observer/event path.

**Files**
- `app/Providers/FortifyServiceProvider.php` — new
- `app/Http/Middleware/EnsureTwoFactorEnabled.php` — new
- `app/Listeners/LogLoginAttempt.php` — new
- `tests/Feature/Auth/MfaEnforcementTest.php` — new
- `tests/Feature/Auth/RateLimitTest.php` — new
- `tests/Feature/Auth/LoginAuditTest.php` — new

**Acceptance**

1. **WHEN** a user without `two_factor_confirmed_at` set requests any `/admin/*` route **THE SYSTEM
   SHALL** redirect to `/admin/two-factor-setup`.
2. **WHEN** 6 login attempts occur within one minute for the same email+IP **THE SYSTEM SHALL**
   respond `429` on the 6th.
3. **WHEN** a login fails **THE SYSTEM SHALL** write exactly one `audit_logs` row with
   `action=login_failed` and the request's IP and user agent.
4. **WHEN** a login succeeds with TOTP confirmed **THE SYSTEM SHALL** write exactly one `audit_logs`
   row with `action=login` and update `users.last_login_at`/`last_login_ip`.
5. **WHEN** the login form is submitted with a wrong password **THE SYSTEM SHALL** respond with a
   generic message that does not reveal whether the email exists.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Auth/MfaEnforcementTest.php
./vendor/bin/pest tests/Feature/Auth/RateLimitTest.php
./vendor/bin/pest tests/Feature/Auth/LoginAuditTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T5: fortify auth + mandatory mfa"
git tag step-10-auth
```

### `E2-T6` — Authorization: roles and Policies

**Depends on:** `E2-T5` · **Priority:** p0

Install `spatie/laravel-permission`, run its migration. `RoleSeeder` creates the `super_admin` role
and every permission from `blueprint.md` §1's authorization table, idempotent via `updateOrCreate`.
One Policy per content model — 14 total (Profile, Company, Experience, Education, Certification,
SkillCategory, Skill, Technology, ProjectCategory, Project, SoftwareProject, Document, SocialProfile,
Setting) — each grants every ability to `super_admin`, denies everyone else, registered in
`AuthServiceProvider::$policies`. `AuditLog` has no Policy (no create/update/delete surface).

**Files**
- `app/Policies/*.php` — new, 14 files
- `database/seeders/RoleSeeder.php` — new
- `tests/Feature/Authorization/PolicyDenialTest.php` — new
- `tests/Feature/Authorization/PolicyCoverageTest.php` — new

**Acceptance**

1. **WHEN** `php artisan db:seed --class=RoleSeeder --force` runs twice **THE SYSTEM SHALL** leave
   exactly one `super_admin` role row both times.
2. **WHEN** a user with no role attempts to update a `Project` **THE SYSTEM SHALL** be denied by the
   Policy.
3. **WHEN** a user with `super_admin` attempts the same update **THE SYSTEM SHALL** be authorized.
4. **WHEN** every content model in §4 is checked **THE SYSTEM SHALL** have exactly 14 registered
   Policies (counted from §4's entity list).

**Verify**

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan tinker --execute="var_dump(Spatie\Permission\Models\Role::count());"
./vendor/bin/pest tests/Feature/Authorization/PolicyDenialTest.php
./vendor/bin/pest tests/Feature/Authorization/PolicyCoverageTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T6: roles + policies"
git tag step-11-authz
```

### `E2-T7` — Realistic content seeder and factories

**Depends on:** `E2-T6` · **Priority:** p1

`database/factories/*.php` — one per content model. `AdminUserSeeder` creates one `User` + `Profile`;
`two_factor_confirmed_at` is left **null** on purpose so the enrolment flow is exercised on first real
login. `DemoContentSeeder` creates exactly: 4 companies, 6 experiences, 2 education, 8 certifications,
5 skill categories × 24 skills, 20 technologies, 3 project categories, 9 projects (4 with a
`software_projects` extension), 3 documents (1 current resume), 5 social profiles — every row
`updateOrCreate` keyed on `slug`.

**Files**
- `database/factories/*.php` — new
- `database/seeders/AdminUserSeeder.php` — new
- `database/seeders/DemoContentSeeder.php` — new

**Acceptance**

1. **WHEN** `php artisan db:seed --force` runs on an empty database **THE SYSTEM SHALL** create
   exactly 4 companies, 6 experiences, 2 education, 8 certifications, 24 skills, 20 technologies,
   9 projects, 4 software_projects, 3 documents, 5 social_profiles.
2. **WHEN** `php artisan db:seed --force` runs a second time **THE SYSTEM SHALL** leave every count
   unchanged.
3. **WHEN** the seeded admin's `two_factor_confirmed_at` is read **THE SYSTEM SHALL** be null.

**Verify**

```bash
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force
php artisan db:seed --force
php artisan tinker --execute="echo Company::count(),' ',Experience::count(),' ',Education::count(),' ',Certification::count(),' ',Skill::count(),' ',Technology::count(),' ',Project::count(),' ',SoftwareProject::count(),' ',Document::count(),' ',SocialProfile::count();"
php artisan db:seed --force
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T7: realistic demo content seeder + factories"
git tag step-12-seeder
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** `php artisan migrate:rollback --step=1000 --force && php artisan migrate --force && php artisan db:seed --force` runs on a clean local
   database **THE SYSTEM SHALL** exit 0 with the exact row counts from `E2-T7`.
2. **WHEN** an unauthenticated request is made to any `/admin/*`-style route this epic protects **THE
   SYSTEM SHALL** be denied, and an authorized `super_admin` request **THE SYSTEM SHALL** succeed.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build
php artisan migrate:rollback --step=1000 --force && php artisan migrate --force && php artisan db:seed --force
```

## Pitfalls

- **Adding a `media`-referencing foreign key constraint too early.** `media` does not exist until
  epic 03. Every `*_media_id` column here is nullable with no FK constraint; the constraint is added
  when `media` is created.
- **Redefining the `publishable()` macro.** It exists once, from `E2-T1`. Every later migration in
  this epic (and beyond) calls it — copy-pasting its 9 columns by hand is the defect this rule exists
  to prevent.
- **Treating `SoftwareProject` as single-table inheritance.** It is a 1-1 extension table with a
  unique `project_id` FK — a `Project` is not a subclass, it optionally *has* a `SoftwareProject`.
- **Skipping the "seeded MFA is unconfirmed" requirement.** Seeding a fully-enrolled admin defeats the
  whole point of exercising the enrolment flow in epic 03/step 13.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-06-schema-core`
      through `step-12-seeder`. `git tag -l 'step-0[6-9]-*' 'step-1[0-2]-*'` lists all 7.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` updated if this epic added a variable — it did not; `DB_*`/`REDIS_*` were already
      present from `01-foundation`'s Bootstrap.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
