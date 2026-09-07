# Epic 05: SEO, Performance & Hardening

> After this epic, every published route carries correct meta/JSON-LD, passes a structural
> accessibility sweep in both themes, meets the Lighthouse performance/SEO budget, and the
> application is hardened for production: security headers, an executed backup+restore drill, a
> supervised queue worker, and a zero-downtime deploy script that can never wipe the database.

| | |
|---|---|
| **Epic id** | `05-seo-hardening` |
| **Tasks** | `E5-T1` … `E5-T3` |
| **Depends on** | `04-public-themes` |
| **Unlocks** | nothing — this is the last epic |
| **Parallel with** | nothing — each task gates on the previous one's surface being complete |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

spatie/laravel-sitemap `^8.2` · Symfony DomCrawler (transitive via `illuminate/testing`, no new
package) · Lighthouse CI (`npx lighthouse`) · `supervisor` on the LXC · Pest `^5.1`.

| Task | Command |
|---|---|
| SEO audit | `php artisan seo:audit` |
| Test (one file) | `./vendor/bin/pest {path}` |
| Deploy | `bash infra/deploy.sh` |
| Backup / restore drill | `bash infra/backup.sh --restore-to-scratch-and-verify` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest &&
php artisan seo:audit` passes before any task here is marked done.

## Directory subtree

```
app/
  Console/Commands/SeoAudit.php          # NEW E5-T1
  Actions/Cache/CachePublicPage.php       # NEW E5-T2
  Actions/Cache/InvalidatePublicPageCache.php  # NEW E5-T2
  Http/Middleware/SecurityHeaders.php     # NEW E5-T3
resources/views/components/json-ld.blade.php  # NEW E5-T1
public/robots.txt                         # NEW E5-T1
tests/Feature/
  A11yTest.php SeoTest.php                # E5-T1
  Cache/PageCacheTest.php                 # E5-T2
  Security/HeadersTest.php                # E5-T3
infra/
  deploy.sh backup.sh                     # NEW E5-T3
```

Everything outside this subtree is out of scope.

## Data model touched here

NOT APPLICABLE — no schema changes. This epic reads every entity created in epics 02-04.

## Contracts

**Consumed:**

| From | Interface | Guarantee |
|---|---|---|
| `04-public-themes` | Every public route, both themes | The a11y/SEO sweep has a complete route surface to walk |
| `03-admin-filament` | Filament model-publish events | The cache-invalidation listener has an event to hook |

**Produced:**

| Export | Signature | Used by |
|---|---|---|
| `php artisan seo:audit` | exits 0 iff no missing/duplicate/mismatched SEO field | The global acceptance gate (blueprint §20.1) |
| `infra/deploy.sh` | zero-downtime deploy, never `migrate:fresh`/`db:wipe` | Every future release |
| An executed backup+restore drill | row counts match per table | Production data-loss protection (Risk #5) |

## Conventions that bite in this area

- **`seo:audit` and `A11yTest.php` assert properties, not counts** — "every published entity has a
  non-empty title/description" not "there are 47 SEO fields".
- **`infra/deploy.sh` is guarded by a file-existence check before the grep** — `test -f infra/deploy.sh && ! grep ...`,
  never a bare `! grep` that would pass vacuously if the file did not exist.
- **The backup/restore drill is executed, not merely scripted.** A cron entry that has never actually
  run once against a scratch database is not proof of anything.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/security.md`.

---

## Tasks

### `E5-T1` — SEO surface (meta, JSON-LD, sitemap) + A11yTest structural sweep

**Depends on:** nothing in this epic (epic-level: `E4-T6`) · **Priority:** p0

Wire per-entity/per-article meta, OG image resolution, canonical URLs, JSON-LD (`Person`,
`Organization`, `CreativeWork`, `BlogPosting`). Install `spatie/laravel-sitemap:^8.2` including
articles. Add `robots.txt`. Add `SeoAudit` console command. Author `A11yTest.php` — a DomCrawler
structural sweep over every published route in both themes.

**Files**
- `app/Console/Commands/SeoAudit.php` — new
- `tests/Feature/A11yTest.php` — new
- `tests/Feature/SeoTest.php` — new
- `resources/views/components/json-ld.blade.php` — new
- `public/robots.txt` — new

**Acceptance**

1. **WHEN** `php artisan seo:audit` runs against the seeded database **THE SYSTEM SHALL** exit 0 with 0 missing/duplicate/mismatched fields reported.
2. **WHEN** `/sitemap.xml` is requested **THE SYSTEM SHALL** return 200 with one url entry per published entity including articles.
3. **WHEN** `./vendor/bin/pest tests/Feature/SeoTest.php` runs **THE SYSTEM SHALL** exit 0.
4. **WHEN** `./vendor/bin/pest tests/Feature/A11yTest.php` runs over every published route in both `data-theme` values **THE SYSTEM SHALL** exit 0 with zero structural violations.
5. **WHEN** a published `Project` has no `og_image_id` set **THE SYSTEM SHALL** fall back to a site-default OG image.

**Verify**

```bash
php artisan seo:audit
php artisan serve --port=8123 & SERVE_PID=$!; sleep 1; test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8123/sitemap.xml)" = 200; kill "$SERVE_PID"
./vendor/bin/pest tests/Feature/SeoTest.php
./vendor/bin/pest tests/Feature/A11yTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E5-T1: seo surface (meta, jsonld, sitemap) and a11y structural sweep"
git tag step-25-seo-a11y
```

### `E5-T2` — Database-driven page caching, image optimization, Lighthouse gate

**Depends on:** `E5-T1` · **Priority:** p0

Add cache/invalidate Actions keyed by route + query string, using the database cache driver. Wire a
Filament model-event listener to invalidate on publish/unpublish. Add WebP conversion to the media
pipeline. Run Lighthouse CI against Home, one project page, one article page.

**Files**
- `app/Actions/Cache/CachePublicPage.php` — new
- `app/Actions/Cache/InvalidatePublicPageCache.php` — new
- `tests/Feature/Cache/PageCacheTest.php` — new

**Acceptance**

1. **WHEN** the same public route is requested twice within the cache TTL **THE SYSTEM SHALL** serve the second request from the database cache.
2. **WHEN** a `Project` is published or unpublished in Filament **THE SYSTEM SHALL** invalidate that project's detail-page cache entry immediately.
3. **WHEN** an image is uploaded **THE SYSTEM SHALL** generate a WebP conversion alongside the original.
4. **WHEN** Lighthouse CI runs against Home, one project page, and one article page **THE SYSTEM SHALL** report Performance >= 95 and SEO >= 95 on all three.
5. **WHEN** `tests/Feature/Cache/PageCacheTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Cache/PageCacheTest.php
set -a; source .env; set +a
npx lighthouse https://$APP_DOMAIN/ --output=json --output-path=/tmp/lh-home.json --chrome-flags="--headless" && jq -e '.categories.performance.score >= 0.95 and .categories.seo.score >= 0.95' /tmp/lh-home.json
```

**Checkpoint**

```bash
git add -A && git commit -m "E5-T2: database-driven page cache, image optimization, lighthouse gate"
git tag step-26-cache-performance
```

### `E5-T3` — Hardening: headers/CSP, security audit, backup+restore drill, supervised queue, deploy script

**Depends on:** `E5-T2` · **Priority:** p0

Add `SecurityHeaders` middleware (CSP, `X-Content-Type-Options`, `Referrer-Policy`, HSTS). Run a
`security-auditor` pass and resolve any high-severity finding. Write `infra/backup.sh` and execute a
restore drill with per-table row-count assertions. Add a supervisor config for `queue:work`. Add
`/up`. Write `infra/deploy.sh`, guarded against `migrate:fresh`/`db:wipe`.

**Files**
- `infra/deploy.sh` — new
- `infra/backup.sh` — new
- `app/Http/Middleware/SecurityHeaders.php` — new
- `tests/Feature/Security/HeadersTest.php` — new

**Acceptance**

1. **WHEN** any response is inspected **THE SYSTEM SHALL** carry `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy` headers.
2. **WHEN** `security-auditor` completes its pass **THE SYSTEM SHALL** report zero open high-severity findings. (Satisfied by the team's own Definition of Done — `CLAUDE.md`'s subagent review gate — not by a command in this task's `Verify` block; there is no automated proxy for a subagent's judgment.)
3. **WHEN** the backup script runs and its output is restored to a scratch database **THE SYSTEM SHALL** match production's row count for every table.
4. **WHEN** `supervisorctl status` is queried on the LXC **THE SYSTEM SHALL** report the `queue-worker` program as `RUNNING`.
5. **WHEN** `infra/deploy.sh` is inspected **THE SYSTEM SHALL** contain neither `migrate:fresh` nor `db:wipe`.
6. **WHEN** this task's Checkpoint runs **THE SYSTEM SHALL** find exactly 27 `step-*` tags in the repository.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Security/HeadersTest.php
test -f infra/deploy.sh && ! grep -q "migrate:fresh\|db:wipe" infra/deploy.sh
ssh -i "$LXC_SSH_KEY" "root@$LXC_HOST" 'supervisorctl status queue-worker' | grep -q RUNNING
set -a; source .env; set +a
test "$(curl -s -o /dev/null -w '%{http_code}' https://$APP_DOMAIN/up)" = 200
bash infra/backup.sh --restore-to-scratch-and-verify
```

**Checkpoint**

```bash
git add -A && git commit -m "E5-T3: hardening — headers, security audit, backup drill, supervised queue, deploy script"
git tag step-27-hardening
test "$(git tag -l 'step-*' | wc -l)" -eq 27
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** the full acceptance gate in blueprint §20.1 runs **THE SYSTEM SHALL** exit 0 on every line, on a clean checkout, with the bundle present.
2. **WHEN** `git tag -l 'step-*' | wc -l` is checked **THE SYSTEM SHALL** report exactly 27.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build
php artisan seo:audit
./vendor/bin/pest tests/Feature/A11yTest.php
test "$(git tag -l 'step-*' | wc -l)" -eq 27
```

## Pitfalls

- **A scripted-but-never-run restore drill.** The drill must actually execute against a scratch
  database with a real row-count assertion, not just exist as a script nobody invoked.
- **A bare `! grep` guard with no file-existence check.** If `infra/deploy.sh` does not exist, a bare
  `! grep -q ... infra/deploy.sh` can pass vacuously depending on the shell's error handling — always
  pair it with `test -f` first.
- **A CSP so strict it blocks the site's own self-hosted fonts/assets.** Test the header against a
  real page load, not just its presence.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-25-seo-a11y`
      through `step-27-hardening`, and the total tag count across the whole build is 27.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged unless this epic added a variable.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
