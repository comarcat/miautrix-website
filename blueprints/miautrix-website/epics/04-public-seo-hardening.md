# Epic 04: Public Site, SEO & Hardening

> After this epic, the public portfolio is live at the production domain, fully styled to the
> technical/precise design system in both themes, discoverable by search and answer engines, cached
> and fast, and hardened with security headers, a supervised queue, and a proven backup/restore path.
> This is the last epic — when it's done, the project is done.

| | |
|---|---|
| **Epic id** | `04-public-seo-hardening` |
| **Tasks** | `E4-T1` … `E4-T7` |
| **Depends on** | `03-admin-filament` |
| **Unlocks** | nothing — this is the last epic |
| **Parallel with** | nothing — layout precedes components precedes pages precedes SEO precedes caching precedes hardening |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · Blade + Tailwind v4 + Alpine.js (public site) · Redis 8 (page cache) · `spatie/laravel-
sitemap` · Horizon (supervised queue) · Pest 5, plus shell-driven checks (`curl`, `jq`, Lighthouse CI).

| Task | Command |
|---|---|
| Local services up | `docker compose up -d --wait` |
| Build assets | `npm run build` |
| Tests (one file) | `./vendor/bin/pest tests/Feature/Public/X.php` |
| SEO audit | `php artisan seo:audit` |
| Lighthouse gate | `bash scripts/lighthouse-gate.sh` |
| Deploy (final) | `bash infra/deploy.sh` |
| Restore drill | `bash infra/backup/restore-drill.sh` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`
passes before any task here is marked done.

## Directory subtree

```
resources/
  css/app.css                                  # edit (E4-T1) — full @theme block, finalized from the stub
  views/
    components/layouts/app.blade.php           # NEW (E4-T1)
    components/ui/*.blade.php                  # NEW (E4-T2) — button/card/badge/timeline/table/tabs/modal/alert/toast/file-upload/image-gallery/breadcrumb
    components/seo/*.blade.php                 # NEW (E4-T5)
    pages/{home,about,experience,skills}.blade.php               # NEW (E4-T3)
    pages/projects/{index,show}.blade.php, pages/software/*, pages/certifications.blade.php, pages/resume.blade.php   # NEW (E4-T4)
  js/{reveal.js,theme.js}                       # NEW (E4-T2)
public/fonts/*.woff2                            # NEW (E4-T1) — self-hosted IBM Plex Sans + JetBrains Mono
app/
  Http/Controllers/Public/{Home,About,Experience,Skills}Controller.php          # NEW (E4-T3)
  Http/Controllers/Public/{Projects,Software,Certifications,Resume}Controller.php   # NEW (E4-T4)
  Livewire/ContactForm.php                      # NEW (E4-T4)
  Support/Seo/JsonLd.php                        # NEW (E4-T5)
  Http/Controllers/SitemapController.php        # NEW (E4-T5)
  Console/Commands/SeoAudit.php                 # NEW (E4-T5)
  Http/Middleware/CachePublicResponse.php       # NEW (E4-T6)
  Http/Middleware/SecurityHeaders.php           # NEW (E4-T7)
  Http/Controllers/HealthController.php         # NEW (E4-T7)
infra/
  supervisor/horizon.conf                       # NEW (E4-T7)
  backup/{pg-backup,media-backup,restore-drill}.sh   # NEW (E4-T7)
  deploy.sh                                     # edit (E4-T7) — adds migrate --force, cache warming, queue restart to the epic-01 stub
scripts/lighthouse-gate.sh                      # NEW (E4-T6)
tests/Feature/
  LayoutTest.php · ComponentsPageTest.php
  Public/{PagesSet1Test,PagesSet2Test,ContactFormTest}.php
  SeoTest.php · CacheInvalidationTest.php
```

Everything outside this subtree is out of scope. If a task seems to require editing a file not
listed here, stop and report — it means the epic boundary is wrong.

## Data model touched here

None new. Every controller here reads existing entities via `Model::published()->orderBy(...)` —
no migration in this epic.

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| `02-schema-auth` | every content model + `Publishable` scope | Every public query filters `published=true` |
| `03-admin-filament` | `MediaController::show`, `ResumeDownloadController::show` | The public image gallery and resume page link directly to these, no reimplementation |

**Produced** — nothing further consumes this; it is the last epic. The final external contract is
the production URL itself: `https://$APP_DOMAIN/` responding `200`, `/health` responding `200` with
`status:ok`.

## Conventions that bite in this area

- **Tokens only, no raw hex/px.** Every color resolves to a `--color-*` custom property from
  `resources/css/app.css`; the three-block dark-mode pattern (`:root`, the media-query block guarded
  `:not([data-theme="light"])`, then `[data-theme="dark"]`) is the only correct one.
- **Zero requests to `fonts.googleapis.com`/`fonts.gstatic.com`, in any environment.** Fonts are
  self-hosted `woff2` files.
- **A public entity with zero published rows renders nothing** — never a placeholder card or "coming
  soon" stub.
- **No queries in Blade.** View data arrives from the controller; a Blade file that calls
  `Model::where(...)` directly is a defect.
- **`wire:model.blur` by default on the contact form**, not `.live` — Livewire re-renders on every
  action, and the form has no need for per-keystroke updates.
- **Migrations are expand-only in `infra/deploy.sh`**, and `migrate:fresh`/`db:wipe` must never
  appear in it — checked mechanically in `E4-T7`'s own `Verify`.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/frontend.md` applies to `E4-T1`–`E4-T4`;
`.claude/rules/security.md` applies to `E4-T7`.

---

## Tasks

### `E4-T1` — Design tokens and base layout

**Depends on:** `E3-T5` (bundle prerequisite: `03-admin-filament` complete) · **Priority:** p0

Finalize `resources/css/app.css`'s `@theme` block against the full token table (light/dark, every
`--color-*`, the type scale, spacing scale, radius) — it was a minimal stub from epic 01's scaffold
task. Self-host IBM Plex Sans (400/500/600) and JetBrains Mono (400/500) as `woff2` under
`public/fonts/`. `components/layouts/app.blade.php` — `<head>` with two `<link rel="preload">` tags
for the above-the-fold weights, a blocking inline script reading the dark-mode preference from
`localStorage` before first paint, header/footer/skip-link, exactly one `<h1>` slot per page.

**Files**
- `resources/css/app.css` — edit (full token set)
- `resources/views/components/layouts/app.blade.php` — new
- `public/fonts/*.woff2` — new
- `tests/Feature/LayoutTest.php` — new

**Acceptance**

1. **WHEN** the built CSS is grepped **THE SYSTEM SHALL** contain zero occurrences of
   `fonts.googleapis.com` or `fonts.gstatic.com`.
2. **WHEN** any Blade view is grepped **THE SYSTEM SHALL** contain zero occurrences of the same two
   hosts.
3. **WHEN** a page loads with the dark preference stored **THE SYSTEM SHALL** apply
   `data-theme="dark"` before the first paint.
4. **WHEN** the layout is rendered **THE SYSTEM SHALL** contain exactly one `<h1>` and a
   skip-to-content link as the first focusable element.

**Verify**

```bash
npm run build
! grep -rE "fonts\.(googleapis|gstatic)\.com" public/build/assets/*.css
! grep -rE "fonts\.(googleapis|gstatic)\.com" resources/views/
./vendor/bin/pest tests/Feature/LayoutTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T1: design tokens + self-hosted fonts + base layout"
git tag step-18-layout
```

### `E4-T2` — Component library

**Depends on:** `E4-T1` · **Priority:** p0

Every UI primitive from `blueprint.md` §7's list: button, card, badge, timeline, table, tabs, modal,
alert, toast, file-upload, image-gallery, breadcrumb — tokens only, no arbitrary Tailwind values.
`resources/js/reveal.js` — the one shared `IntersectionObserver` for scroll reveal, disabled under
`prefers-reduced-motion: reduce`. `resources/js/theme.js` — dark-mode toggle, persists to
`localStorage`. A `/components` route (local/testing only) renders every primitive in both themes.

**Files**
- `resources/views/components/ui/*.blade.php` — new
- `resources/js/reveal.js` — new
- `resources/js/theme.js` — new
- `tests/Feature/ComponentsPageTest.php` — new

**Acceptance**

1. **WHEN** `/components` (local/testing env only) is requested **THE SYSTEM SHALL** render every
   listed component with no unstyled element.
2. **WHEN** `prefers-reduced-motion: reduce` is emulated **THE SYSTEM SHALL** render every reveal
   target already at its final opacity/position with no transition applied.
3. **WHEN** the theme toggle is clicked **THE SYSTEM SHALL** flip `data-theme` on `<html>` and
   persist the choice to `localStorage`.

**Verify**

```bash
./vendor/bin/pest tests/Feature/ComponentsPageTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T2: component library"
git tag step-19-components
```

### `E4-T3` — Public pages, set 1

**Depends on:** `E4-T2` · **Priority:** p0

`Home`, `About`, `Experience`, `Skills` controllers — one invokable class per page, querying
`Model::published()->orderBy('sort_order')`, passing view data only. Replaces the scaffold's
placeholder `/` route from epic 01.

**Files**
- `app/Http/Controllers/Public/{Home,About,Experience,Skills}Controller.php` — new
- `resources/views/pages/{home,about,experience,skills}.blade.php` — new
- `tests/Feature/Public/PagesSet1Test.php` — new

**Acceptance**

1. **WHEN** `/`, `/about`, `/experience`, `/skills` are requested **THE SYSTEM SHALL** respond `200`
   and render only `published=true` rows.
2. **WHEN** every `experience` row is unpublished **THE SYSTEM SHALL** render the experience page
   with no timeline section — never a placeholder stub.
3. **WHEN** `/` is requested **THE SYSTEM SHALL** contain the seeded profile's `headline` in the
   rendered server HTML.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Public/PagesSet1Test.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T3: public pages - home/about/experience/skills"
git tag step-20-public-set1
```

### `E4-T4` — Public pages, set 2, and the contact form

**Depends on:** `E4-T3` · **Priority:** p0

`Projects`, `Software`, `Certifications`, `Resume` controllers, including slug-bound `show` actions.
`ContactForm` Livewire component — `wire:model.blur` fields, honeypot `_gotcha`, mails
`CONTACT_TO_ADDRESS` and writes zero database rows, rate-limited 5/hour per IP.

**Files**
- `app/Http/Controllers/Public/{Projects,Software,Certifications,Resume}Controller.php` — new
- `resources/views/pages/projects/{index,show}.blade.php`, `pages/software/*.blade.php`,
  `pages/certifications.blade.php`, `pages/resume.blade.php` — new
- `app/Livewire/ContactForm.php` — new
- `tests/Feature/Public/PagesSet2Test.php` — new
- `tests/Feature/Public/ContactFormTest.php` — new

**Acceptance**

1. **WHEN** `/projects/{slug}` is requested for a published project **THE SYSTEM SHALL** render its
   technologies, media gallery and documents.
2. **WHEN** `/projects/{slug}` is requested for an unpublished project **THE SYSTEM SHALL** respond
   `404`.
3. **WHEN** the contact form is submitted with a valid payload and empty honeypot **THE SYSTEM
   SHALL** send exactly one mail and write zero database rows.
4. **WHEN** the honeypot field is non-empty **THE SYSTEM SHALL** return the same success state and
   send zero mail.
5. **WHEN** the form is submitted 6 times in one hour from the same IP **THE SYSTEM SHALL** respond
   `429` on the 6th.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Public/PagesSet2Test.php
./vendor/bin/pest tests/Feature/Public/ContactFormTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T4: public pages - projects/software/certifications/resume + contact form"
git tag step-21-public-set2
```

### `E4-T5` — SEO surface

**Depends on:** `E4-T4` · **Priority:** p0

`JsonLd` support class emits `Person`, `Organization`, `CreativeWork` structured data.
`components/seo/meta.blade.php` renders per-page title/description/canonical/OG from each entity's
SEO block. `SitemapController` (via `spatie/laravel-sitemap`) + `robots.txt`. `SeoAudit` console
command crawls every published route, asserting non-empty/non-duplicate titles/descriptions and
matching canonicals. This is also the first task where every public route exists, so it authors
`A11yTest.php` — a DomCrawler-based structural accessibility sweep (one `<h1>` per page, every image
has `alt`, every form control has a label, a skip-link is present, no inline color style). This is a
mechanically-checkable subset of WCAG 2.2 AA, not a full axe-core browser audit — the project has no
headless-browser dependency to run one (`playwright-cli` stays optional per §18).

**Files**
- `app/Support/Seo/JsonLd.php` — new
- `app/Http/Controllers/SitemapController.php` — new
- `app/Console/Commands/SeoAudit.php` — new
- `resources/views/components/seo/*.blade.php` — new
- `tests/Feature/SeoTest.php` — new
- `tests/Feature/A11yTest.php` — new

**Acceptance**

1. **WHEN** `php artisan seo:audit` runs over every published route **THE SYSTEM SHALL** report zero
   missing titles, zero duplicate meta descriptions, zero canonical mismatches.
2. **WHEN** `/sitemap.xml` is requested **THE SYSTEM SHALL** respond `200` and list every published
   entity's canonical URL, and zero unpublished ones.
3. **WHEN** `/robots.txt` is requested **THE SYSTEM SHALL** respond `200` and reference
   `/sitemap.xml`.
4. **WHEN** a project detail page's HTML is parsed **THE SYSTEM SHALL** contain one valid
   `CreativeWork` JSON-LD block.
5. **WHEN** `tests/Feature/A11yTest.php` runs its DomCrawler sweep over every published route **THE
   SYSTEM SHALL** report zero pages with a missing `alt`, a missing form label, more than one `<h1>`,
   or an inline color style.

**Verify**

```bash
php artisan seo:audit
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8123/sitemap.xml)" = 200
./vendor/bin/pest tests/Feature/SeoTest.php
./vendor/bin/pest tests/Feature/A11yTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T5: seo surface - meta/og/json-ld/sitemap/robots"
git tag step-22-seo
```

### `E4-T6` — Caching and performance

**Depends on:** `E4-T5` · **Priority:** p1

`CachePublicResponse` middleware — Redis-backed full-page cache on public GET routes, invalidated by
`AuditLogObserver`'s `updated`/`deleted` events, keyed/tagged per entity so one publish invalidates
only its own cache entry. Image optimization via the medialibrary conversion pipeline (WebP,
installed in epic 03). `scripts/lighthouse-gate.sh` runs Lighthouse CI against the home page and one
project page on a throttled mobile profile.

**Files**
- `app/Http/Middleware/CachePublicResponse.php` — new
- `scripts/lighthouse-gate.sh` — new
- `tests/Feature/CacheInvalidationTest.php` — new

**Acceptance**

1. **WHEN** the same public GET is requested twice **THE SYSTEM SHALL** serve the second response
   from Redis without re-querying the database.
2. **WHEN** a cached project is published/unpublished/edited **THE SYSTEM SHALL** invalidate exactly
   that entity's cache entry.
3. **WHEN** `bash scripts/lighthouse-gate.sh` runs against the home page and one project page **THE
   SYSTEM SHALL** report performance ≥95 and SEO ≥95 on both, throttled mobile profile.

**Verify**

```bash
bash scripts/lighthouse-gate.sh
./vendor/bin/pest tests/Feature/CacheInvalidationTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T6: redis page cache + image optimization + lighthouse gate"
git tag step-23-caching
```

### `E4-T7` — Hardening, backups and operations

**Depends on:** `E4-T6` · **Priority:** p0

`SecurityHeaders` middleware — CSP (nonce-based `script-src`, no `unsafe-inline`), HSTS,
`X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: DENY`, `Permissions-Policy`, all literal
values from `.claude/rules/security.md`. `infra/supervisor/horizon.conf` — the supervised worker.
`infra/backup/{pg-backup,media-backup,restore-drill}.sh` — nightly `pg_dump` + media rsync to
`$BACKUP_TARGET`, and a drill that restores into a scratch database and asserts every table's row
count matches, then drops the scratch database. `HealthController` — the `/health` endpoint. Final
`infra/deploy.sh` — adds `config:cache route:cache view:cache`, `migrate --force` (expand-only,
**never** `migrate:fresh`), and `queue:restart` to the epic-01 stub.

**Files**
- `app/Http/Middleware/SecurityHeaders.php` — new
- `infra/supervisor/horizon.conf` — new
- `infra/backup/pg-backup.sh` — new
- `infra/backup/media-backup.sh` — new
- `infra/backup/restore-drill.sh` — new
- `app/Http/Controllers/HealthController.php` — new
- `infra/deploy.sh` — edit (final version)

**Acceptance**

1. **WHEN** any response is inspected **THE SYSTEM SHALL** carry all 6 security headers with the
   exact values `.claude/rules/security.md` specifies.
2. **WHEN** `bash infra/backup/restore-drill.sh` runs **THE SYSTEM SHALL** restore the latest dump
   into a scratch database and report every production table's row count matching, then drop the
   scratch database.
3. **WHEN** the Horizon worker process is killed **THE SYSTEM SHALL** be restarted by supervisor and
   queued jobs **SHALL** resume processing.
4. **WHEN** `infra/deploy.sh` is grepped **THE SYSTEM SHALL** contain zero occurrences of
   `migrate:fresh` or `db:wipe`.
5. **WHEN** `/health` is requested with the database and Redis both reachable and no pending
   migration **THE SYSTEM SHALL** respond `200` with `{"status":"ok",...}`.

**Verify**

```bash
curl -sSI "https://$APP_DOMAIN/" | grep -qi '^strict-transport-security:'
curl -sSI "https://$APP_DOMAIN/" | grep -qi '^content-security-policy:'
test -f infra/deploy.sh && ! grep -q "migrate:fresh\|db:wipe" infra/deploy.sh
bash infra/backup/restore-drill.sh
curl -sS "https://$APP_DOMAIN/health" | jq -e '.status == "ok"'
test "$(git tag -l 'step-*' | wc -l)" -eq 24
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T7: security headers + supervised worker + backup/restore drill + health check"
git tag step-24-hardening
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** the full acceptance gate in `blueprint.md` §20.1 is run against `https://$APP_DOMAIN/`
   **THE SYSTEM SHALL** pass every command.
2. **WHEN** `git tag -l 'step-*' | wc -l` runs **THE SYSTEM SHALL** report `24`.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest --ci && npm run build
bash scripts/lighthouse-gate.sh
bash infra/backup/restore-drill.sh
```

## Pitfalls

- **Adding `migrate:fresh` "temporarily" to `infra/deploy.sh` while debugging a production issue.**
  There is no temporary version of this — the gate greps for it and the risk register names the
  actual outage it causes.
- **Full-page caching a route that renders per-visitor state.** Every cached route here is a
  published-content page with no session-specific rendering; if a future page needs one, it is
  excluded from `CachePublicResponse`, not force-fit into it.
- **Writing the restore drill but never running it for real.** `blueprint.md` §20.1's manual gate is
  explicit: the drill must be executed once, not merely exist as a script — a script nobody ran is
  not evidence of recoverability.
- **Skipping the Lighthouse gate because "the components look right."** §7's tokens produce a fast
  page by construction, but the gate is what proves it — a subjective look is not the acceptance
  criterion.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-18-layout`
      through `step-24-hardening`. `git tag -l 'step-1[8-9]-*' 'step-2[0-4]-*'` lists all 7.
- [ ] Gate command passes clean, run from the project root.
- [ ] `git tag -l 'step-*' | wc -l` reports **24** — every checkpoint across all four epics exists.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` updated if this epic added a variable — it did not; `MAIL_*`, `CONTACT_TO_ADDRESS`,
      `HORIZON_PATH`, `BACKUP_TARGET` were already present from `01-foundation`'s Bootstrap.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
- [ ] `blueprint.md` §20.1's full manual checklist is walked once, for real, before calling the
      project done.
