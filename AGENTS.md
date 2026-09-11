# miautrix-website — agent instructions

Self-hosted IT portfolio: a public Blade/Tailwind site backed by a Filament 5 admin CMS, on
Laravel 13 / PHP `^8.4` / PostgreSQL 18. One administrator, no public signup. No Redis — queue,
cache, and session all use the `database` driver.

## Commands

| Task | Command |
|---|---|
| Local Postgres up | `docker compose up -d` |
| Install | `composer install` · `npm install` |
| Dev server | `php artisan serve` — http://127.0.0.1:8000 |
| Build | `npm run build` |
| Format check | `./vendor/bin/pint --test` |
| Static analysis | `./vendor/bin/phpstan analyse` |
| Tests | `./vendor/bin/pest` · one file: `./vendor/bin/pest tests/Feature/X.php` |
| Migrate / seed | `php artisan migrate` · `php artisan db:seed` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`

## Non-negotiable

1. Never commit `.env`, a key, a token, or any real credential.
2. Never run `php artisan migrate:fresh` or `db:wipe` outside a local/test reset — never in a deploy script.
3. Never accept SVG uploads. Allowed types: JPEG, PNG, WebP, PDF, DOCX, ZIP.
4. Never expose Telescope outside `local`, and never remove the MFA requirement on `/admin`.
5. Never add Redis, `predis/predis`, or `laravel/horizon`.
6. Validation lives in FormRequests, authorization in Policies — never inline in a controller.
7. `SoftwareProject` is a Filament relation manager on `ProjectResource` — never a standalone resource file.
8. Icons are Heroicons or Lucide SVG. Never emoji.
9. Never mark a task done with a failing gate command.

PHP `^8.4` is a hard floor (Pest 5 requires it). Filament 5 and Livewire 4 are upgraded together,
never one alone.

Full architecture, boundaries, design tokens, and team workflow: see `CLAUDE.md` in this directory.
Build order: `blueprints/miautrix-website/tasks.json` and `blueprints/miautrix-website/epics/`.

## Phase 2

Phase 2 is an additive change to the live site. Build order: `blueprints/phase-2/tasks.json`;
detail: `blueprints/phase-2/epics/`. Checkpoint tags are `p2-step-01` … `p2-step-48`.

**Release flow (changed this phase):** implement → `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
→ PR → CI check `ci` green → merge → `bash infra/deploy-staging.sh` → **owner reviews on
`staging.miautrix.tech` and writes `review/<id>.md` = `APROBADO`** → `bash infra/deploy.sh`
(production) → live verify. No merged change reaches production without `APROBADO`. `infra/deploy.sh`
is unchanged.

**Three Phase-2 non-negotiables (in addition to the list above):**

1. The three `config/site.php` flags (`themes.dynamic`, `analytics.record_page_views`,
   `csp.youtube_on_life`) default OFF and are flipped ON only in their epic's final task. A flag OFF
   must leave the site byte-identical to before Phase 2.
2. The GeoLite2 `.mmdb` is an un-committed operator prerequisite; `storage/app/geoip/` is git-ignored;
   every geo consumer (`GeoLocator`, `whoami`, `tool_downloads`, `page_views`) is null-safe without
   it, and **no test may require it**.
3. The Life channel never appears in `/feed.xml`; there is no Life feed. `/life` is theme-gated (404
   unless the active theme's `shows_life_blog` is true). No `mac` field in `whoami`. No third-party
   analytics or tracking JS anywhere.

Full Phase-2 governance, feature flags, and conventions: see `CLAUDE.md`.
