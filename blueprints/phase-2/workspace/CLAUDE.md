<!--
  PHASE 2 — MERGE BLOCK, NOT A REPLACEMENT.

  The target repo already has a full CLAUDE.md. Do NOT overwrite it. APPEND the sections
  below (from "## Phase 2 — governance and conventions" onward) to the end of the repo's
  existing CLAUDE.md, before the <laravel-boost-guidelines> block if one is present, or at
  the very end otherwise. Bootstrap's guarded `rsync --ignore-existing` will NOT copy this
  file over the existing one — the merge is a manual, one-time edit.

  Keep the combined file under 200 lines of net new content. Everything here is stable
  (it changes only when Phase 2 is re-planned), so duplication risk is near zero.
-->

## Phase 2 — governance and conventions

Phase 2 is an **additive** change to the live site. Every Phase-2 migration is expand-only and has a
real `down()`. The three cross-cutting features each sit behind a `config()` boolean that defaults
**OFF**, so every task merges safely and the feature turns on only in its epic's final task.

Build order: `blueprints/phase-2/tasks.json`; execution detail: `blueprints/phase-2/epics/`. Read
those, not the blueprint narrative, during the build. Phase-2 checkpoint tags are `p2-step-01` …
`p2-step-48` (namespaced to not collide with Phase 1's `step-*`).

### Release flow — the sponsor-approval gate is NEW this phase

Phase 1 auto-promoted a merged PR to production. **Phase 2 does not.** Every task:

```
implement on task/<id>-<slug>
  -> local gate: ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
  -> PR to main
  -> CI: the check literally named `ci` must be green
  -> merge to main
  -> bash infra/deploy-staging.sh          # NEW — deploys main to staging.miautrix.tech (APP_ENV=staging, noindex, HTTP Basic auth, own DB)
  -> SPONSOR REVIEW & APPROVAL on staging  # NEW — the owner reviews and writes review/<id>.md = APROBADO
  -> bash infra/deploy.sh                  # production promote — ONLY after APROBADO. infra/deploy.sh is unchanged.
  -> live verify on https://miautrix.tech
```

The sponsor gate waits on a human — it is never a task's "Done when". A merged task with no
`review/<id>.md` = `APROBADO` dated before its production promote is not shipped.

### Feature flags — `config/site.php` (all default OFF until their epic's final task)

| Key | OFF means | Turned ON by |
|---|---|---|
| `site.themes.dynamic` | `ResolveTheme` uses the pre-Phase-2 literal path (`matrix` only when the cookie equals `matrix`, else `technical`) — byte-identical to before. Dynamic/date-windowed themes are inert. | `p2-step-25` (default flips to `true`) |
| `site.analytics.record_page_views` | `RecordPageView` early-returns before any DB write; `page_views` gets no rows. | `p2-step-43` (default flips to `true`) |
| `site.csp.youtube_on_life` | `SecurityHeaders` adds no YouTube `frame-src`; every route's CSP is exactly today's. | `p2-step-34` (default flips to `true`) |

`config('site.canonical_host')` (env `CANONICAL_HOST`, default `miautrix.tech`) drives the shared
cookie domain and the `<link rel="canonical">` on both `www.` and apex.

Each flag disables its feature with **no redeploy** — set it `false` in the environment. That is the
Phase-2 rollback lever alongside a PR revert + `php artisan migrate:rollback`.

### New conventions

- **`App\Support\Theming\ThemeResolver` is the one theme-resolution path** when `site.themes.dynamic`
  is on. `ResolveTheme` middleware delegates to it. Never re-add a literal `=== 'matrix'` check
  outside the flag-OFF branch. `technical` and `matrix` are seeded `themes` rows with `tokens = {}`
  and keep rendering from `resources/css/app.css` — the injected token `<style>` block is for
  non-seed event themes only, and it carries `Vite::cspNonce()`.
- **Per-route CSP additions are scoped to `/life*`.** The YouTube `frame-src` /
  `img-src https://i.ytimg.com` is added in `SecurityHeaders` only when
  `config('site.csp.youtube_on_life')` AND `$request->is('life*')`. `/blog/*` and `/` must stay
  byte-identical. `Permissions-Policy` sends `geolocation=(self)` on public routes only; `/admin`
  keeps `geolocation=()`.
- **The GeoLite2 `.mmdb` is an un-committed operator prerequisite.** It lives at
  `storage/app/geoip/GeoLite2-City.mmdb` (path via `GEOIP_DATABASE_PATH`); `storage/app/geoip/` is
  git-ignored. `App\Support\Geo\GeoLocator` returns `null` for every field when the file is absent —
  **no test may require it**, and every geo column (`tool_downloads`, `page_views`, `whoami`) is
  null-safe.
- **Logging tables are append-only:** `share_clicks`, `tool_downloads`, `page_views` have
  `created_at` only, no `updated_at`, no soft delete, no FK to the shared subject (`share_clicks` /
  `page_views`). Set `public $timestamps = false` on their models.
- **The page-cache key gained a leading host segment:** `public-page:{host}:{theme}:{path}`. Every
  `InvalidatePublicPageCache` method forgets across known hosts (canonical, `www.`, staging) and
  seeded themes. Never reshape the `{theme}:{path}` tail.
- **The Life / Gaming blog is theme-gated** in `LifeController` (`abort(404)` when the active theme's
  `shows_life_blog` is false) **and** in `<x-nav-menu>`. **The Life channel never appears in
  `/feed.xml`** — the feed query carries `->professional()`. There is no Life feed (Non-Goal).
- **PDF and download routes are never cached** — register `/…/pdf`, `/tools/{slug}/download`,
  `/projects/{project}/files/{media}`, `/s/{network}/{type}/{id}`, `/whoami` **outside** the
  `cache.public` group.
- **`MailSettings` and `Analytics` are Filament Pages, not Resources.** SMTP settings are `Setting`
  rows; the `mail.password` row uses the `encrypted` cast and is never rendered back into the form.
- **No third-party JS anywhere** — the terminal widget, nav menu, YouTube facade and Analytics
  dashboard all load only `'self'` scripts. Share links are plain `href`s; the `/s/…` redirect is the
  first-party click log.
- **No `mac` field in `whoami`** — a web page cannot obtain a client MAC. GPS is client-only, after
  the browser prompt; "permission denied" is printed explicitly, never omitted silently.

### Two new Composer packages (added in-task, not pre-installed)

- `barryvdh/laravel-dompdf` — `composer require barryvdh/laravel-dompdf:^3.1` in `p2-step-26` (PDF export).
- `geoip2/geoip2` — `composer require geoip2/geoip2:^3.4` in `p2-step-36` (offline IP geo).

Nothing else changes in `composer.json` / `package.json`. Versions live in the lockfiles.

### `staging.miautrix.tech`

`infra/deploy-staging.sh` is a parameter-swapped copy of `infra/deploy.sh` (reads `STAGING_*` env
vars, `APP_ENV=staging`, own database). `infra/provision-staging.md` is the operator checklist
(vhost, HTTP Basic auth, the Cloudflare apex↔www 301 Redirect Rule text, the OG-image bot-fight-mode
exception, the `.mmdb` placement). The www/apex 301 is applied by the owner only once
`bash infra/host-parity-check.sh` reports 0 diffs for 48h (blueprint §9.1).
