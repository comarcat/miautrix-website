# Epic 02: Schema, Auth & Authorization

> After this epic, every content table in the data model exists with real Eloquent models, the
> single administrator can log in with mandatory TOTP MFA, every content model has a Policy, and a
> realistic seeded database — including sample blog articles — is one command away.

| | |
|---|---|
| **Epic id** | `02-schema-auth` |
| **Tasks** | `E2-T1` … `E2-T7` |
| **Depends on** | `01-foundation` |
| **Unlocks** | `03-admin-filament` |
| **Parallel with** | nothing — every later epic reads this schema |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · Eloquent (no repository layer — `app/Actions/` for domain logic) · PostgreSQL 18 ·
laravel/fortify `^1.39` (native TOTP MFA, no extra package) · spatie/laravel-permission `^8.3` ·
Pest `^5.1`.

| Task | Command |
|---|---|
| Migrate | `php artisan migrate` |
| Fresh migrate (local/test only, never in deploy) | `php artisan migrate:fresh` |
| Seed | `php artisan db:seed` |
| Test (one file) | `./vendor/bin/pest {path}` |
| Local Postgres up/down | `docker compose up -d` / `docker compose down` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest` passes
before any task here is marked done.

The local Postgres 18 service (`docker-compose.yml`) is already running from epic 01 — start it
first with the command above if it is not.

## Directory subtree

```
database/
  migrations/            # one file per task in this epic (E2-T1..T4)
  factories/              # one per entity — E2-T7
  seeders/
    DatabaseSeeder.php     # skeleton in E2-T1, filled in E2-T7
    RolesSeeder.php         # NEW E2-T6
app/
  Models/                 # one Eloquent model per entity — E2-T1..T4
  Contracts/
    PortfolioIntegration.php        # NEW E2-T4 — unimplemented seam
    AnalyticsProviderInterface.php  # NEW E2-T4 — null implementation
  Actions/Fortify/         # NEW E2-T5
  Http/Middleware/EnsureMfaConfirmed.php   # NEW E2-T5
  Listeners/LogLoginAttempt.php             # NEW E2-T5
  Policies/                # one per content model — E2-T6
tests/Feature/
  Schema/                  # E2-T1..T4, E2-T7
  Auth/                    # E2-T5, E2-T6
```

Everything outside this subtree is out of scope.

## Data model touched here

Every entity in blueprint §4 except `articles` (deferred to epic 03's blog admin, step 18):
`users`, `profiles`, `companies`, `experiences`, `education`, `certifications`, `skill_categories`,
`skills`, `technologies`, `projects`, `project_categories`, `software_projects`, the three project
pivots, `media` (spatie's table), `documents`, `social_profiles`, `settings`, `audit_logs`,
`imports`/`import_records`, spatie's permission tables. See blueprint §4 for the full column list
per entity — it is not repeated here field-by-field to keep this epic file readable; every migration
task's acceptance criteria state the specific constraints that matter (slug uniqueness, append-only
audit logs, the 1:1 software-project extension, etc).

## Contracts

**Consumed:**

| From | Interface | Guarantee |
|---|---|---|
| `01-foundation` | A working Laravel app, `composer.json`/`package.json` authored, local Postgres 18 running | Migrations can run against it |

**Produced:**

| Export | Signature | Used by |
|---|---|---|
| Every Eloquent model in §4 (minus `Article`) | standard Eloquent model classes under `app/Models/` | `03-admin-filament`'s resources, `04-public-themes`'s controllers |
| `EnsureMfaConfirmed` middleware | blocks `/admin/*` until `two_factor_confirmed_at` is set | `03-admin-filament`'s panel provider |
| One Policy per content model | `app/Policies/{Model}Policy.php`, checks `hasRole('super_admin')` | Filament resource authorization |
| A seeded database with 2-3 sample Articles | `php artisan db:seed` | `04-public-themes`'s public pages never render an empty stub |

## Conventions that bite in this area

- **The publishable-entity convention is identical across 4 of these entities** (`experiences`,
  `education`, `certifications`, `projects`) plus `articles` later: `slug` (unique, indexed),
  `published`, `featured`, `sort_order`, and 6 SEO fields. Do not reinvent it per table.
- **`software_projects` is a 1:1 extension table, never single-table inheritance.** `Project::softwareProject()` is a `hasOne`.
- **`audit_logs` has no `updated_at`** — it is append-only. Do not let Eloquent's default timestamps add one.
- **`migrate:fresh` is for local/test only** — never let it slip into a script this epic writes that
  could run in production; that guard is enforced in epic 05, but do not create the habit here.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/database.md`, `.claude/rules/security.md`.

---

## Tasks

### `E2-T1` — Core identity and profile schema + seeder skeleton

**Depends on:** nothing in this epic (epic-level: `E1-T5`) · **Priority:** p0

Create `users` (with `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) and
`profiles` migrations per blueprint §4. Create `app/Models/User.php` and `app/Models/Profile.php`.
Create an empty `database/seeders/DatabaseSeeder.php` skeleton for `E2-T7` to fill in.

**Files**
- the migration generated by `php artisan make:migration add_two_factor_columns_to_users_table` (timestamp-prefixed) — new
- the migration generated by `php artisan make:migration create_profiles_table` (timestamp-prefixed) — new
- `app/Models/User.php` — new
- `app/Models/Profile.php` — new
- `tests/Feature/Schema/CoreIdentityTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs against an empty database **THE SYSTEM SHALL** create `users` and `profiles` tables with the columns in blueprint §4.
2. **WHEN** a `User` is created via the Eloquent model **THE SYSTEM SHALL** persist and retrieve `two_factor_confirmed_at` as null by default.
3. **WHEN** `profiles.user_id` is queried **THE SYSTEM SHALL** enforce uniqueness at the database level.
4. **WHEN** `tests/Feature/Schema/CoreIdentityTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate:fresh
./vendor/bin/pest tests/Feature/Schema/CoreIdentityTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T1: core identity and profile schema"
git tag step-06-identity-schema
```

### `E2-T2` — Career schema: companies, experiences, education, certifications, skills, technologies

**Depends on:** `E2-T1` · **Priority:** p0

Create migrations and models for the seven career entities (companies, experiences, education,
certifications, skills, skill_categories, technologies), applying the publishable-entity convention
to `experiences`, `education`, `certifications`, and wiring `skills.skill_category_id`→`skill_categories`.

**Files**
- the migrations generated by `php artisan make:migration create_{companies,experiences,education,certifications,skills,skill_categories,technologies}_table` (timestamp-prefixed, one file per table) — new
- `app/Models/Company.php` — new
- `app/Models/Experience.php` — new
- `app/Models/Education.php` — new
- `app/Models/Certification.php` — new
- `app/Models/Skill.php` — new
- `app/Models/SkillCategory.php` — new
- `app/Models/Technology.php` — new
- `tests/Feature/Schema/CareerSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create all 7 tables with their documented columns.
2. **WHEN** an `Experience` is queried via `Profile::experiences()` **THE SYSTEM SHALL** return only that profile's rows, ordered by `started_at` desc.
3. **WHEN** a duplicate `slug` is inserted into `experiences` **THE SYSTEM SHALL** raise a unique-constraint violation.
4. **WHEN** `tests/Feature/Schema/CareerSchemaTest.php` runs **THE SYSTEM SHALL** report 7 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate:fresh
./vendor/bin/pest tests/Feature/Schema/CareerSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T2: career schema"
git tag step-07-career-schema
```

### `E2-T3` — Project schema: projects, project_categories, software_projects, pivots

**Depends on:** `E2-T2` · **Priority:** p0

Create `projects`, `project_categories`, `software_projects` (1:1 extension), and the three pivot
tables.

**Files**
- the migrations generated by `php artisan make:migration create_{projects,project_categories,software_projects,project_technology,project_media,project_documents}_table` (timestamp-prefixed, one file per table) — new
- `app/Models/Project.php` — new
- `app/Models/SoftwareProject.php` — new
- `app/Models/ProjectCategory.php` — new
- `tests/Feature/Schema/ProjectSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `projects`, `project_categories`, `software_projects`, and the 3 pivot tables.
2. **WHEN** a `Project` has a `SoftwareProject` extension row **THE SYSTEM SHALL** expose it via `Project::softwareProject()` as a `hasOne`.
3. **WHEN** a `Project` is attached to a `Technology` via the pivot **THE SYSTEM SHALL** enforce the composite primary key.
4. **WHEN** `tests/Feature/Schema/ProjectSchemaTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate:fresh
./vendor/bin/pest tests/Feature/Schema/ProjectSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T3: project schema and pivots"
git tag step-08-project-schema
```

### `E2-T4` — Media/document/social/settings/audit/imports schema

**Depends on:** `E2-T3` · **Priority:** p0

Install `spatie/laravel-medialibrary:^11.23`, publish its migration. Create `documents`,
`social_profiles`, `settings`, `audit_logs` (append-only), `imports`/`import_records`. Create the two
contract interfaces (`PortfolioIntegration`, `AnalyticsProviderInterface` + `NullAnalyticsProvider`).

**Files**
- the migrations generated by `php artisan make:migration create_{media,documents,social_profiles,settings,audit_logs,imports,import_records}_table` (timestamp-prefixed, one file per table) — new
- `app/Models/Media.php` — new
- `app/Models/Document.php` — new
- `app/Models/SocialProfile.php` — new
- `app/Models/Setting.php` — new
- `app/Models/AuditLog.php` — new
- `app/Models/Import.php` — new
- `app/Models/ImportRecord.php` — new
- `app/Contracts/PortfolioIntegration.php` — new
- `app/Contracts/AnalyticsProviderInterface.php` — new
- `tests/Feature/Schema/MediaDocsAuditTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `media`, `documents`, `social_profiles`, `settings`, `audit_logs`, `imports`, `import_records`.
2. **WHEN** an `AuditLog` row is inserted **THE SYSTEM SHALL** accept no `updated_at` column write.
3. **WHEN** `App\Contracts\AnalyticsProviderInterface` is resolved from the container **THE SYSTEM SHALL** return the `NullAnalyticsProvider` binding.
4. **WHEN** a published entity's referenced `Media` row is deleted via `Media::destroy()` **THE SYSTEM SHALL** throw a named `MediaInUseException` and leave the row intact.
5. **WHEN** `tests/Feature/Schema/MediaDocsAuditTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate:fresh
./vendor/bin/pest tests/Feature/Schema/MediaDocsAuditTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T4: media, documents, social, settings, audit, imports schema"
git tag step-09-media-audit-schema
```

### `E2-T5` — Auth: Fortify + mandatory TOTP MFA, rate limiting, session expiry, login audit

**Depends on:** `E2-T4` · **Priority:** p0

`composer require laravel/fortify:^1.39`, enable native two-factor with `confirm: true`. Add
`EnsureMfaConfirmed` middleware. Rate limit login (5/15min per email+IP). Log every
success/failure to `audit_logs` via `LogLoginAttempt`.

**Files**
- `app/Actions/Fortify/CreateNewUser.php` — new
- `app/Http/Middleware/EnsureMfaConfirmed.php` — new
- `app/Listeners/LogLoginAttempt.php` — new
- `tests/Feature/Auth/MfaTest.php` — new

**Acceptance**

1. **WHEN** a user without a confirmed `two_factor_confirmed_at` requests `/admin` **THE SYSTEM SHALL** redirect to the MFA enrolment screen.
2. **WHEN** a valid TOTP code is submitted at enrolment **THE SYSTEM SHALL** set `two_factor_confirmed_at` and display recovery codes exactly once.
3. **WHEN** the 6th failed login for the same email+IP occurs within 15 minutes **THE SYSTEM SHALL** respond 429 and log the attempt.
4. **WHEN** a successful login occurs **THE SYSTEM SHALL** write one `audit_logs` row with `action='login_success'`.
5. **WHEN** a failed login occurs **THE SYSTEM SHALL** write one `audit_logs` row with `action='login_failed'`.
6. **WHEN** `tests/Feature/Auth/MfaTest.php` runs **THE SYSTEM SHALL** report 6 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Auth/MfaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T5: fortify auth with mandatory totp mfa and login audit"
git tag step-10-auth-mfa
```

### `E2-T6` — Authorization: spatie/laravel-permission, Policies, seeded super_admin

**Depends on:** `E2-T5` · **Priority:** p0

`composer require spatie/laravel-permission:^8.3`. Seed a `super_admin` role. One Policy per content
model, checking `hasRole('super_admin')`.

**Files**
- `database/seeders/RolesSeeder.php` — new
- `app/Policies/ProfilePolicy.php` — new
- `app/Policies/ProjectPolicy.php` — new
- `app/Policies/ArticlePolicy.php` — new
- `tests/Feature/Auth/PolicyTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create the spatie permission tables.
2. **WHEN** `RolesSeeder` runs **THE SYSTEM SHALL** create exactly one `super_admin` role and assign it to the seeded admin user.
3. **WHEN** a user without `super_admin` calls `Gate::authorize('update', $project)` **THE SYSTEM SHALL** throw an `AuthorizationException`.
4. **WHEN** `tests/Feature/Auth/PolicyTest.php` runs **THE SYSTEM SHALL** report at least 10 passing assertions, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Auth/PolicyTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T6: authorization via spatie/laravel-permission and policies"
git tag step-11-authorization
```

### `E2-T7` — Realistic content seeder + factories for every entity, incl. sample Articles

**Depends on:** `E2-T6` · **Priority:** p0

A Model Factory for every entity, and a completed `DatabaseSeeder` per blueprint §4's "Seed data"
spec — including the seeded `super_admin` (from `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD`) and 2-3
sample Articles.

**Files**
- `database/factories/ProjectFactory.php` — new
- `database/factories/ArticleFactory.php` — new
- `database/seeders/DatabaseSeeder.php` — edit: fill in the skeleton from `E2-T1`
- `tests/Feature/Schema/SeederTest.php` — new

**Acceptance**

1. **WHEN** `php artisan db:seed` runs against a migrated empty database **THE SYSTEM SHALL** exit 0.
2. **WHEN** the seed completes **THE SYSTEM SHALL** have created exactly 1 row in `users` with the `super_admin` role.
3. **WHEN** the seed completes **THE SYSTEM SHALL** have created at least 2 and at most 3 rows in `articles`, all `published = true`.
4. **WHEN** the seed completes **THE SYSTEM SHALL** have created rows for every entity blueprint §4 defines.
5. **WHEN** `tests/Feature/Schema/SeederTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
php artisan migrate:fresh
php artisan db:seed
./vendor/bin/pest tests/Feature/Schema/SeederTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E2-T7: content seeder and factories, incl. sample articles"
git tag step-12-seeder
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** `php artisan migrate:fresh && php artisan db:seed` runs **THE SYSTEM SHALL** exit 0 and populate every table blueprint §4 defines except `articles`' Filament resource (that lands in epic 03).
2. **WHEN** the seeded `super_admin` attempts to log in **THE SYSTEM SHALL** be forced through MFA enrolment before any authorization check runs.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
php artisan migrate:fresh && php artisan db:seed
```

## Pitfalls

- **Treating `software_projects` as STI.** It is a 1:1 extension table with its own `id`, never a
  shared base table with a `type` discriminator.
- **Giving `audit_logs` Eloquent's default timestamps.** Override `$timestamps` handling so no
  `updated_at` column is ever written.
- **Forgetting the seam tables are unpopulated.** `imports`/`import_records` exist from `E2-T4` but
  nothing in this epic (or ever, in v1) writes to them — that's correct, not a gap.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-06-identity-schema`
      through `step-12-seeder`.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` updated with `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD`.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
