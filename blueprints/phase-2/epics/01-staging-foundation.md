# Epic 01: Staging Environment & Maintenance Foundation

> After this epic the repo has `config/site.php` with three OFF feature flags and a canonical-host
> setting, a styled maintenance page with a live countdown, an `infra/deploy-staging.sh` that mirrors
> production deploy against `staging.miautrix.tech`, an operator provisioning checklist, a `staging`
> branch wired into the unchanged `ci` check, and a `noindex` guarantee whenever `APP_ENV=staging`.
> Nothing user-visible changes on production yet.

| | |
|---|---|
| **Epic id** | `01-staging-foundation` |
| **Tasks** | `E1-T1` … `E1-T8` |
| **Depends on** | nothing — start here (Phase 1 is already shipped and live) |
| **Unlocks** | every later Phase-2 epic — they all need the flags, the staging deploy, and the release sub-flow |
| **Parallel with** | nothing |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` (hard floor from Pest `^5.1`) · Livewire 4 · Filament 5 · Blade + Tailwind
CSS v4 + Alpine · Vite · PostgreSQL (tests: network `miautrix_test`, or `docker-compose.yml`
`postgres:18.6` on port 5433 as the offline fallback) · Pest · Pint · Larastan. **No Redis** — queue,
cache and session use the `database` driver. Self-hosted on a Debian LXC behind Nginx + Cloudflare.
Package manager: Composer + npm. Dependency versions live in `composer.lock` / `package-lock.json` —
read them, never guess. Phase 2 adds **no** package in this epic.

| Task | Command |
|---|---|
| Format check | `./vendor/bin/pint --test` |
| Static analysis | `./vendor/bin/phpstan analyse` |
| Build assets | `npm run build` (always before `pest` — the Vite manifest) |
| Test (one file) | `./vendor/bin/pest tests/Feature/Phase2/<Name>Test.php` |
| Migrate / seed | `php artisan migrate` · `php artisan db:seed` |
| Shell-syntax check | `bash -n infra/<script>.sh` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task here is marked done.

**Release sub-flow (every task, after its Checkpoint):** PR → CI `ci` green → merge to `main` →
`bash infra/deploy-staging.sh` → **sponsor review & approval on `staging.miautrix.tech`
(`review/<id>.md` = `APROBADO`)** → `bash infra/deploy.sh` (production) → live verify. The sponsor
gate is a human step; it is never a task's "Done when".

## Directory subtree

```
config/
  site.php                 # NEW E1-T1 — canonical_host + 3 flags (all default OFF)
.env.example               # EDIT E1-T2 — Phase-2 keys, all optional/defaulted
resources/views/errors/
  503.blade.php            # NEW E1-T3, E1-T4 — styled maintenance page + JS countdown to ETA
infra/
  deploy-staging.sh        # NEW E1-T5 — parameter-swapped copy of deploy.sh
  provision-staging.md     # NEW E1-T6 — operator checklist (NOT a script), incl. Cloudflare 301 rule text
  host-parity-check.sh     # NEW E1-T6 — curl both hosts, diff (used in blueprint §9.1)
.github/workflows/ci.yml   # EDIT E1-T7 — add `staging` to on.push.branches; job `name: ci` untouched
app/Http/Middleware/
  SecurityHeaders.php      # EDIT E1-T8 — X-Robots-Tag noindex when APP_ENV=staging
tests/Feature/Phase2/
  SiteConfigTest.php FlagToggleHelperTest.php MaintenancePageTest.php StagingNoindexTest.php
  Concerns/TogglesSiteFlags.php
```

Everything outside this subtree is out of scope. If a task seems to need a file not listed here, stop
and report — the epic boundary is wrong.

## Data model touched here

NOT APPLICABLE — no schema work in this epic. Schema starts in `02-reach-sharing`.

## Contracts

**Consumed** — already exists in the live repo, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `infra/deploy.sh` | Production deploy: local gate → GitHub pull on server → `migrate --force` (expand-only) → atomic symlink cutover → `cache:clear` → supervisor + fpm reload. **Not edited by Phase 2.** |
| Phase 1 | `.github/workflows/ci.yml` | Job `name: ci`; branch protection requires the check literally named `ci`. |
| Phase 1 | `app/Http/Middleware/SecurityHeaders.php` | Per-route CSP (`PUBLIC_CSP` vs `PANEL_CSP` on `$request->is('admin*')`), `Vite::useCspNonce()`, HSTS behind Cloudflare. |

**Produced** — later epics depend on exactly these:

| Export | Signature | Used by |
|---|---|---|
| `config('site.themes.dynamic')` `config('site.analytics.record_page_views')` `config('site.csp.youtube_on_life')` | booleans, default `false` | E3-T3/T9 (themes), E5-T8/T9 (analytics), E4-T9 (YouTube CSP) |
| `config('site.canonical_host')` | string, default `miautrix.tech`, env `CANONICAL_HOST` | E3-T7 (cookie domain, canonical link), blueprint §9.1 |
| `infra/deploy-staging.sh` | `bash infra/deploy-staging.sh` — deploys `main` to `staging.miautrix.tech`, `APP_ENV=staging` | every later task's release sub-flow |
| `infra/host-parity-check.sh` | `bash infra/host-parity-check.sh` — 0 diffs = parity | blueprint §9.1 cutover |
| `tests/Feature/Phase2/Concerns/TogglesSiteFlags.php` | a Pest helper to flip a `site.*` flag within one test | every later feature test that needs a flag ON |

## Conventions that bite in this area

- **`config/*.php` is committed** and read at boot — a `Verify` that boots the framework needs it.
  `.claude/rules/security.md` governs `config/**` and `infra/**`.
- **A flag defaults OFF.** Every task that flips one ON does it as its epic's *final* task, never
  mid-epic, so merges are always safe (blueprint §9 rule 9 / §12 rollback).
- **`infra/*.sh` must be non-interactive and safe to re-run.** `deploy-staging.sh` runs the local
  gate *before* touching the server (copy `deploy.sh`'s "no `.env` loaded yet" comment verbatim — see
  `infra/deploy.sh` lines 12–17).
- **Never let a `! grep` run before the file it greps exists.** Order `bash -n <script>` or
  `test -f <file>` first in a `Verify` so a missing file fails loudly, not vacuously.
- **`ci.yml`'s job `name: ci` is load-bearing.** Only touch `on.push.branches`. `grep -qx '    name: ci'`
  proves it is intact.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/security.md`.

---

## Tasks

Listed in `tasks.json` order — the build order. Work top to bottom; do not re-rank by priority.

### `E1-T1` — Add `config/site.php` with `canonical_host` and three OFF feature flags

**Depends on:** nothing · **Priority:** p0

Create `config/site.php` returning `canonical_host` (env `CANONICAL_HOST`, default `miautrix.tech`)
and three nested booleans — `themes.dynamic`, `analytics.record_page_views`, `csp.youtube_on_life` —
each `env('SITE_…', false)`. Keep every default `false`/the literal host string so a plain checkout
behaves exactly as it does today. Do not read these anywhere yet; later tasks consume them.

**Files**
- `config/site.php` — new
- `tests/Feature/Phase2/SiteConfigTest.php` — new

**Acceptance**

1. **WHEN** `config('site.themes.dynamic')`, `config('site.analytics.record_page_views')` and `config('site.csp.youtube_on_life')` are read with no env override **THE SYSTEM SHALL** each return boolean false.
2. **WHEN** `CANONICAL_HOST` is set in the environment **THE SYSTEM SHALL** make `config('site.canonical_host')` return that value, otherwise the string `miautrix.tech`.
3. **WHEN** `tests/Feature/Phase2/SiteConfigTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/SiteConfigTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: config/site.php with canonical host and three OFF flags"
git tag p2-step-01-site-config
```

### `E1-T2` — Add Phase-2 env keys to `.env.example` and a flag-toggle test helper

**Depends on:** `E1-T1` · **Priority:** p0

Add every Phase-2 key from blueprint §10 to `.env.example` with a blank or obviously-fake value
(`CANONICAL_HOST=miautrix.tech`, the three `SITE_*` flags `=false`, `GEOIP_DATABASE_PATH=`,
`GITHUB_TOKEN=`, and the `STAGING_*` set). Add a Pest concern
`tests/Feature/Phase2/Concerns/TogglesSiteFlags.php` exposing a helper that sets a `site.*` config
value for the duration of one test (via `config()->set()`), so later feature tests can exercise a
flag-ON path without an env change.

**Files**
- `.env.example` — edit: add the Phase-2 keys
- `tests/Feature/Phase2/Concerns/TogglesSiteFlags.php` — new
- `tests/Feature/Phase2/FlagToggleHelperTest.php` — new

**Acceptance**

1. **WHEN** `.env.example` is parsed **THE SYSTEM SHALL** contain `CANONICAL_HOST`, `SITE_THEMES_DYNAMIC`, `SITE_ANALYTICS_RECORD_PAGE_VIEWS`, `SITE_CSP_YOUTUBE_ON_LIFE`, `GEOIP_DATABASE_PATH` and `GITHUB_TOKEN`, each with a blank or obviously-fake value.
2. **WHEN** a test calls the helper to set `site.themes.dynamic` true and then reads the config **THE SYSTEM SHALL** return true within that test only.
3. **WHEN** `tests/Feature/Phase2/FlagToggleHelperTest.php` runs **THE SYSTEM SHALL** report 2 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
grep -q '^CANONICAL_HOST=' .env.example
grep -q '^SITE_ANALYTICS_RECORD_PAGE_VIEWS=' .env.example
./vendor/bin/pest tests/Feature/Phase2/FlagToggleHelperTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] chore: .env.example Phase-2 keys + flag-toggle test helper"
git tag p2-step-02-env-and-flag-helpers
```

### `E1-T3` — Add a styled `errors/503.blade.php` maintenance page with a JS countdown

**Depends on:** `E1-T1` · **Priority:** p1

Build `resources/views/errors/503.blade.php` in the site's own visual language (reuse the Phase-1
layout tokens — `--color-*`, no raw hex). Include a "We'll be back soon" heading, a
`data-countdown` element the JS ticks down, and a plain-text "we expect to be back around {time}"
that reads correctly with JS disabled. Any script is inline and nonce'd. No external asset.

**Files**
- `resources/views/errors/503.blade.php` — new

**Acceptance**

1. **WHEN** `resources/views/errors/503.blade.php` is rendered **THE SYSTEM SHALL** produce a page with a visible "We'll be back soon" heading and an element carrying a `data-countdown` attribute.
2. **WHEN** the page is rendered with `prefers-reduced-motion` in mind **THE SYSTEM SHALL** still show a plain-text estimated return time that does not depend on JavaScript.
3. **WHEN** the page is rendered **THE SYSTEM SHALL** contain no inline `style="color:` attribute and no third-party asset URL.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
grep -q 'data-countdown' resources/views/errors/503.blade.php
! grep -q 'style="color:' resources/views/errors/503.blade.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: styled 503 maintenance page with countdown"
git tag p2-step-03-maintenance-view
```

### `E1-T4` — Wire the maintenance-page ETA/retry value the 503 view reads

**Depends on:** `E1-T3` · **Priority:** p1

Make the view read the maintenance ETA from the value `php artisan down --render="errors::503" --retry=…`
makes available (Laravel exposes the `retry` seconds and the maintenance payload). Render the
countdown target as an ISO-8601 timestamp inside `data-countdown`; when no retry/ETA is present, show
a static fallback line and no half-initialised countdown. Author `MaintenancePageTest.php` rendering
the view both ways.

**Files**
- `resources/views/errors/503.blade.php` — edit: read the retry/ETA value
- `tests/Feature/Phase2/MaintenancePageTest.php` — new

**Acceptance**

1. **WHEN** the 503 view renders with a `retry` value available **THE SYSTEM SHALL** render the countdown target as an ISO-8601 timestamp inside the `data-countdown` element.
2. **WHEN** no `retry`/ETA value is available **THE SYSTEM SHALL** render a static fallback message and no broken countdown.
3. **WHEN** `tests/Feature/Phase2/MaintenancePageTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/MaintenancePageTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: maintenance ETA countdown reads the retry value"
git tag p2-step-04-maintenance-eta
```

### `E1-T5` — Add `infra/deploy-staging.sh` as a parameter-swapped copy of `deploy.sh`

**Depends on:** `E1-T1` · **Priority:** p0

Copy `infra/deploy.sh` to `infra/deploy-staging.sh` and swap: read `STAGING_SSH_HOST`,
`STAGING_DEPLOY_PATH`, `STAGING_DEPLOY_USER`, `STAGING_DEPLOY_PORT`, `STAGING_APP_DOMAIN` (mirror the
`LXC_*`/`DEPLOY_*` set); target `staging.miautrix.tech`; export `APP_ENV=staging` for the deployed
release; point at the staging database. Keep the structure identical — same local gate first, same
GitHub-pull-on-server, `migrate --force` (expand-only), atomic symlink cutover, `cache:clear`,
supervisor + fpm reload. Keep `deploy.sh`'s comment about **not** `source .env` before the gate.
**Never** add `migrate:fresh` or `db:wipe`.

**Files**
- `infra/deploy-staging.sh` — new

**Acceptance**

1. **WHEN** `bash -n infra/deploy-staging.sh` runs **THE SYSTEM SHALL** exit 0.
2. **WHEN** the script is inspected **THE SYSTEM SHALL** read `STAGING_SSH_HOST`, `STAGING_DEPLOY_PATH`, `STAGING_DEPLOY_USER`, `STAGING_DEPLOY_PORT` and `STAGING_APP_DOMAIN`, run the local gate before touching the server, and set `APP_ENV=staging` for the deployed release.
3. **WHEN** the script is inspected **THE SYSTEM SHALL** contain neither `migrate:fresh` nor `db:wipe`.

**Verify**

```bash
./vendor/bin/pint --test
bash -n infra/deploy-staging.sh
grep -q 'APP_ENV=staging' infra/deploy-staging.sh
grep -q 'STAGING_APP_DOMAIN' infra/deploy-staging.sh
! grep -qE 'migrate:fresh|db:wipe' infra/deploy-staging.sh
```

**Checkpoint**

```bash
git add -A && git commit -m "[builder] feat: infra/deploy-staging.sh mirroring production deploy"
git tag p2-step-05-deploy-staging-script
```

### `E1-T6` — Add `infra/provision-staging.md` operator checklist and the host-parity harness

**Depends on:** `E1-T5` · **Priority:** p1

Write `infra/provision-staging.md` as an operator checklist (mirror the shape of `infra/provision.sh`,
but as prose steps — server provisioning is operator-run, never an autonomous task). Cover: the
`staging.miautrix.tech` DNS record + Nginx vhost, HTTP Basic auth on that vhost, a dedicated staging
database (never production's), `APP_ENV=staging` and the noindex behaviour, the MaxMind GeoLite2
`.mmdb` placement, the Cloudflare bot-fight-mode exception for OG image URLs, and **the exact
Cloudflare Redirect Rule text** that 301s the non-canonical host to `config('site.canonical_host')`.
Write `infra/host-parity-check.sh`: for each listed public path, `curl -s` both `www.` and the apex
host, strip the per-request CSP nonce and CSRF token, `diff` the rest; exit non-zero on any diff.

**Files**
- `infra/provision-staging.md` — new
- `infra/host-parity-check.sh` — new

**Acceptance**

1. **WHEN** `infra/provision-staging.md` is read **THE SYSTEM SHALL** name the `staging.miautrix.tech` vhost, HTTP Basic auth, a dedicated staging database, the `APP_ENV=staging` noindex behaviour, and the exact Cloudflare apex-to-www 301 Redirect Rule text.
2. **WHEN** `bash -n infra/host-parity-check.sh` runs **THE SYSTEM SHALL** exit 0.
3. **WHEN** `infra/host-parity-check.sh` is inspected **THE SYSTEM SHALL** curl both `www.` and the apex host for each listed path and diff the responses after stripping the per-request nonce and CSRF token.

**Verify**

```bash
test -f infra/provision-staging.md
grep -qi 'basic auth' infra/provision-staging.md
grep -qi 'redirect rule' infra/provision-staging.md
bash -n infra/host-parity-check.sh
```

**Checkpoint**

```bash
git add -A && git commit -m "[builder] docs: staging provisioning checklist + host-parity harness"
git tag p2-step-06-staging-provision-checklist
```

### `E1-T7` — Add the `staging` branch trigger to `ci.yml` without changing the check name

**Depends on:** `E1-T5` · **Priority:** p0

Edit **only** `on.push.branches` in `.github/workflows/ci.yml` to include `staging` alongside `main`,
`develop` and `task/**`. Do not touch the job's `name: ci`, the services block, or any step — branch
protection's required check matches the literal string `ci`. Create the `staging` branch from `main`
and push it so CI runs there under the same check.

**Files**
- `.github/workflows/ci.yml` — edit: `on.push.branches` only

**Acceptance**

1. **WHEN** `.github/workflows/ci.yml` is parsed as YAML **THE SYSTEM SHALL** be syntactically valid.
2. **WHEN** the workflow's `on.push.branches` list is read **THE SYSTEM SHALL** include `staging` alongside `main` and `develop`.
3. **WHEN** the workflow's job name is read **THE SYSTEM SHALL** still be exactly `ci`, unchanged, so branch protection's required check keeps matching.

**Verify**

```bash
php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/ci.yml'); echo 'yaml-ok';"   # symfony/yaml ships with Laravel
php -r "require 'vendor/autoload.php'; \$y = Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/ci.yml'); exit(in_array('staging', \$y['on']['push']['branches'] ?? [], true) ? 0 : 1);"   # 'staging' is in on.push.branches specifically, not merely somewhere in the file
grep -qx '    name: ci' .github/workflows/ci.yml
```

**Checkpoint**

```bash
git add -A && git commit -m "[builder] ci: run the staging branch under the unchanged ci check"
git tag p2-step-07-staging-branch-ci
```

### `E1-T8` — Emit `noindex` headers when `APP_ENV` is `staging`

**Depends on:** `E1-T7` · **Priority:** p0

In `app/Http/Middleware/SecurityHeaders.php`, when `app()->environment('staging')`, add
`X-Robots-Tag: noindex, nofollow` to every response (in addition to the existing `/admin`-only
`X-Robots-Tag`). Do not change any header on `production` or `local`. Author `StagingNoindexTest.php`
exercising both environments (`$this->app['env']` override or a config fake).

**Files**
- `app/Http/Middleware/SecurityHeaders.php` — edit: staging noindex branch
- `tests/Feature/Phase2/StagingNoindexTest.php` — new

**Acceptance**

1. **WHEN** a response is produced while `app()->environment('staging')` is true **THE SYSTEM SHALL** carry `X-Robots-Tag: noindex, nofollow`.
2. **WHEN** a response is produced while the environment is `production` **THE SYSTEM SHALL** NOT carry a staging `X-Robots-Tag` on a public route.
3. **WHEN** `tests/Feature/Phase2/StagingNoindexTest.php` runs **THE SYSTEM SHALL** report 2 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/StagingNoindexTest.php
./vendor/bin/pest tests/Feature/Security/HeadersTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[security-auditor] feat: X-Robots-Tag noindex on APP_ENV=staging"
git tag p2-step-08-staging-noindex
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** `bash infra/deploy-staging.sh` is dry-checked (`bash -n`) and its parameters inspected **THE SYSTEM SHALL** be a faithful mirror of `infra/deploy.sh` targeting `staging.miautrix.tech` with `APP_ENV=staging` and no destructive Artisan command.
2. **WHEN** `config('site.*')` is read with no env override **THE SYSTEM SHALL** return the three flags as `false` and `canonical_host` as `miautrix.tech`, so a production deploy of this epic changes nothing a visitor sees.
3. **WHEN** the maintenance page is rendered **THE SYSTEM SHALL** show a working countdown and a JS-free fallback time.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
bash -n infra/deploy-staging.sh && bash -n infra/host-parity-check.sh
grep -qx '    name: ci' .github/workflows/ci.yml
```

## Pitfalls

- **A flag that is not `false` by default.** Every default is OFF; the ON flip is another epic's
  final task. Shipping a flag ON here breaks the "additive, safe to merge" guarantee.
- **Editing more of `ci.yml` than `on.push.branches`.** The check name `ci` is a branch-protection
  dependency; renaming the job (or letting a matrix re-append a suffix) breaks merges site-wide.
- **A `! grep` that runs before its file exists** — it passes vacuously. Order `bash -n`/`test -f`
  first.
- **`deploy-staging.sh` sourcing `.env` before the local gate.** `deploy.sh` documents a reproducible
  Pest failure from exactly that; keep the "no `.env` loaded yet" ordering.
- **Provisioning as an autonomous task.** `infra/provision-staging.md` is a checklist the operator
  runs — never a script a build step executes against a real host.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — none left `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] Every task has its `p2-step-*` checkpoint tag in git (`git tag -l 'p2-step-0[1-8]-*'` lists 8).
- [ ] `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest` passes clean from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` lists every Phase-2 key, each blank or obviously fake.
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote.
