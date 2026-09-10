# Epic 05: Platform, Admin & Analytics

> After this epic, outbound SMTP is admin-configurable (encrypted, with a send-test action), a
> terminal command widget sits on public pages, `whoami` returns IP-derived data (null-safe without
> the GeoLite2 file), a "Tools" section serves downloads with first-party geo/referrer analytics and
> cached GitHub repo stats, a flag-gated `RecordPageView` middleware writes page views, and a
> self-hosted Filament Analytics dashboard consolidates it all. Backlog items 3, 4, 13, 13.1, 14.

| | |
|---|---|
| **Epic id** | `05-platform-analytics` |
| **Tasks** | `E5-T1` … `E5-T9` |
| **Depends on** | `04-content-portfolio` |
| **Unlocks** | `06-testimonials` |
| **Parallel with** | nothing |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` · Livewire 4 · Filament 5 · Blade + Tailwind v4 + Alpine · Vite · PostgreSQL ·
`spatie/laravel-medialibrary` · Pest · Pint · Larastan. **No Redis.** Package manager: Composer + npm.
**This epic installs one Composer package: `geoip2/geoip2` v3.4.0** (pin/provenance in blueprint §11),
via `composer require geoip2/geoip2:^3.4` in `E5-T2`. The MaxMind **GeoLite2-City `.mmdb`** is an
**un-committed operator prerequisite** at `storage/app/geoip/GeoLite2-City.mmdb` (path via
`GEOIP_DATABASE_PATH`) — **no test requires it**; every geo consumer returns null when it is absent.

| Task | Command |
|---|---|
| Install the geo package | `composer require geoip2/geoip2:^3.4 --no-interaction` (E5-T2 only) |
| Format / analyse | `./vendor/bin/pint --test` · `./vendor/bin/phpstan analyse` |
| Build assets | `npm run build` (before `pest`) |
| Test (one file) | `./vendor/bin/pest tests/{Feature,Unit}/Phase2/<Name>Test.php` |
| Migrate | `php artisan migrate` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task is marked done.

**Release sub-flow (every task):** PR → CI `ci` green → merge → `bash infra/deploy-staging.sh` →
**sponsor review & approval on staging** → `bash infra/deploy.sh` → live verify. The sponsor gate is
human. The "Send test email" action is exercised once on staging with real SMTP (Risk #5). Batches:
SMTP (T1), geo + terminal + whoami (T2–T4), tools (T5–T7), analytics (T8–T9).

## Directory subtree

```
composer.json                              # EDIT E5-T2 — composer require adds geoip2/geoip2
config/geoip.php                            # NEW E5-T2 — database_path (env GEOIP_DATABASE_PATH)
config/site.php                             # EDIT E5-T9 — site.analytics.record_page_views default -> true
.gitignore                                  # EDIT E5-T2 — exclude storage/app/geoip/
database/migrations/                        # tools; tool_downloads; tools.gh_*; page_views
app/Models/
  Setting.php                               # EDIT E5-T1 — encrypted cast for the mail.password row
  Tool.php ToolDownload.php PageView.php    # NEW E5-T5, E5-T8
app/Support/
  Mail/ConfiguresMailFromSettings.php       # NEW E5-T1
  Geo/GeoLocator.php                         # NEW E5-T2
  Github/RepoStats.php                       # NEW E5-T7
app/Mail/TestMail.php                        # NEW E5-T1
app/Filament/
  Pages/MailSettings.php Analytics.php       # NEW E5-T1, E5-T9 (custom Pages, NOT Resources)
  Resources/Tools/ToolResource.php + RelationManagers/  # NEW E5-T6
  Widgets/AnalyticsOverviewWidget.php AnalyticsTopPathsWidget.php   # NEW E5-T9
app/Livewire/Terminal.php                    # NEW E5-T3
app/Http/Controllers/Public/
  WhoamiController.php                       # NEW E5-T4
  ToolController.php ToolDownloadController.php   # NEW E5-T6
app/Http/Middleware/
  SecurityHeaders.php                        # EDIT E5-T4 — geolocation=(self) on public routes only
  RecordPageView.php                         # NEW E5-T8 — global, flag-gated, early-return
bootstrap/app.php                            # EDIT E5-T8 — append RecordPageView to the global stack
app/Providers/AppServiceProvider.php         # EDIT E5-T1 (ConfiguresMailFromSettings), observers
resources/views/
  livewire/terminal.blade.php                # NEW E5-T3
  layouts/app.blade.php                      # EDIT E5-T3 — <livewire:terminal> on public pages only
  public/tools/index.blade.php show.blade.php # NEW E5-T6, E5-T7
routes/web.php                               # EDIT — /whoami; /tools, /tools/{slug} (cached); /tools/{slug}/download (uncached)
database/factories/                          # ToolFactory, ToolDownloadFactory, PageViewFactory
tests/{Feature,Unit}/Phase2/                 # MailSettings, GeoLocator(unit), TerminalWidget, Whoami,
                                             # ToolSchema, ToolsPublicAdmin, RepoStats, RecordPageView,
                                             # AnalyticsDashboard
```

Everything outside this subtree is out of scope.

## Data model touched here

| Entity | Fields | Notes |
|---|---|---|
| `Setting` rows (existing model) | `mail.host`, `mail.port`, `mail.username`, `mail.password` (**encrypted cast**), `mail.encryption`, `mail.from_address`, `mail.from_name` | key/value rows; the password row uses Laravel's `encrypted` cast; never rendered back into the form |
| `tools` (new) | `title`, `slug` (unique), `summary`, `description`, `version`, `published`, `repo_url`, `sort_order`, `gh_stars`/`gh_forks` (int nullable), `gh_language`/`gh_license` (nullable), `gh_pushed_at`/`gh_fetched_at` (timestamp nullable), timestamps, soft deletes | `tool_file` single-file media collection on `private-media`. Index `(slug)` unique, `(published)`, `(sort_order)`. `gh_*` columns added in E5-T7. |
| `tool_downloads` (new) | `tool_id` FK → tools `cascadeOnDelete`, nullable `ip`/`country`/`region`/`city`/`isp`/`referrer`/`user_agent`, `created_at` only | append-only; index `(tool_id, created_at)`, `(country)` |
| `page_views` (new) | `path`, nullable `referrer_host`, nullable `country` (2), nullable `device` (`desktop`/`mobile`/`bot`), `channel` (`professional`/`life`/`other`), `created_at` only | append-only; index `(created_at)`, `(path)`, `(channel)`, `(country)` |

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `App\Models\Setting` | key/value app settings model |
| Phase 1 | `App\Http\Middleware\SecurityHeaders` (`PERMISSIONS_POLICY`, `$request->is('admin*')`) | currently sends `geolocation=()` on every response |
| Phase 1 | `App\Http\Controllers\Public\ThemeController` | sets `miautrix_theme` cookie (the terminal `matrix` command reuses this) |
| Phase 1 | `bootstrap/app.php` middleware config | `web(append: [ResolveTheme])`, `alias('cache.public' => CachePublicPage)`, `append(SecurityHeaders)` |
| Phase 1 | `App\Http\Controllers\Public\DocumentDownloadController` | published-gated file stream pattern the tool download controller mirrors |
| Epic 03 | `App\Support\Theming\ThemeResolver`, `themes.shows_life_blog`, `<x-nav-menu>` | active theme; terminal `dir` lists `/life` only when visible |
| Epic 03 | `InvalidatePublicPageCache::forAllThemes()` | host+theme-aware forget for `ToolObserver` |
| Epic 02 | `share_clicks` table | aggregated by the Analytics dashboard |
| Epic 04 | `articles.channel` | the professional-vs-life split |
| Epic 01 | `config('site.analytics.record_page_views')` | flag, default `false` until E5-T9 |

**Produced** — later epics / features depend on these:

| Export | Signature | Used by |
|---|---|---|
| `App\Support\Geo\GeoLocator` | `->country($ip)`, `->region($ip)`, `->city($ip)`, `->isp($ip)` — each `?string`, null when the `.mmdb` is absent | E5-T4 (`whoami`), E5-T6 (`tool_downloads`), E5-T8 (`page_views`) |
| `App\Support\Mail\ConfiguresMailFromSettings` | called in `AppServiceProvider::boot()`; overrides `config('mail.*')` when settings exist | the whole mail stack, incl. Phase-1 `ContactMessageMail` and Epic-06 `TestimonialSubmitted` |
| `page_views` / `tool_downloads` / `tools` | first-party analytics tables | E5-T9 Analytics dashboard; E6 leaves them alone |

## Conventions that bite in this area

- **`GeoLocator` is null-safe by contract.** When `GEOIP_DATABASE_PATH` does not point at a readable
  file, every method returns `null` and logs nothing at error level. Tests run with no `.mmdb`.
- **The encrypted SMTP password is never echoed back.** The Filament field renders empty; a save
  writes the password only when the field is non-empty (`dehydrateStateUsing` / a mutator).
- **`MailSettings` and `Analytics` are Filament Pages, not Resources** (`.claude/rules/filament.md`).
- **`RecordPageView` early-returns before any DB touch** for: `config()` flag off, non-GET,
  `$request->is('admin*')`, asset paths (`build/*`, `*.css`, `*.js`, `*.map`, `favicon*`), and known
  bot UAs. Only after all those does it insert one row. It is `append`ed to the global stack in
  `bootstrap/app.php`, alongside `SecurityHeaders`.
- **The tool download route is NOT cached; the `/tools` index and detail ARE** (`cache.public`).
  `ToolObserver` → `InvalidatePublicPageCache::forAllThemes('tools')`.
- **`RepoStats` degrades to stored columns.** `Cache::remember(key, now()->addHour(), …)`; on an HTTP
  failure, return the `gh_*` column values and set nothing. The tool page never 5xxs on a GitHub
  outage. `GITHUB_TOKEN` is read from env only if set.
- **`geolocation=(self)` is added to `Permissions-Policy` on public routes only.** `/admin` keeps
  `geolocation=()`. Scope on `! $request->is('admin*')`.
- **No `mac` field anywhere in `whoami`.** It is impossible; document the absence (blueprint §1
  Non-Goals). GPS is client-only, after the browser prompt, and "permission denied" is printed
  explicitly — never a silent omission.
- **No third-party JS.** The Analytics dashboard and every public page load only `'self'` scripts.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/filament.md`, `.claude/rules/security.md`,
`.claude/rules/database.md`, `.claude/rules/theming.md`.

---

## Tasks

Listed in `tasks.json` order. Work top to bottom.

### `E5-T1` — Admin-configurable SMTP with an encrypted store and a send-test action

**Depends on:** `E4-T9` · **Priority:** p1

`Setting` model: an `encrypted` cast for the `mail.password` key's value (or a dedicated
`EncryptedSetting` accessor keyed on the setting name). `App\Support\Mail\ConfiguresMailFromSettings`:
read the seven mail settings; when present, `config(['mail.mailers.smtp.host' => …, …, 'mail.from.address' => …, 'mail.from.name' => …])`.
Call it from `AppServiceProvider::boot()`. Filament `MailSettings` Page (custom Page, form + a
"Send test email" **header action** that dispatches `App\Mail\TestMail` to the from-address and
flashes success/failure). `TestMail` mailable.

**Files**
- `app/Support/Mail/ConfiguresMailFromSettings.php` — new
- `app/Filament/Pages/MailSettings.php` — new
- `app/Mail/TestMail.php` — new
- `app/Providers/AppServiceProvider.php` — edit: call `ConfiguresMailFromSettings` in `boot()`; register the `Setting` cast
- `tests/Feature/Phase2/MailSettingsTest.php` — new

**Acceptance**

1. **WHEN** mail settings are saved through the Filament "Mail settings" Page **THE SYSTEM SHALL** persist host, port, username, encryption, from-address and from-name, and store the password with an `encrypted` cast.
2. **WHEN** the framework boots with mail settings present **THE SYSTEM SHALL** make `config('mail.mailers.smtp.host')` and the from-address reflect the saved values, falling back to `.env` when no settings exist.
3. **WHEN** the "Send test email" header action is triggered **THE SYSTEM SHALL** dispatch a `TestMail` to the configured from-address and flash a success or failure message, never echoing the stored password.
4. **WHEN** `tests/Feature/Phase2/MailSettingsTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/MailSettingsTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: admin-configurable SMTP (encrypted) + send-test action"
git tag p2-step-35-admin-smtp
```

### `E5-T2` — Install `geoip2/geoip2` and add a null-safe `GeoLocator` service

**Depends on:** `E4-T9` · **Priority:** p1

`composer require geoip2/geoip2:^3.4 --no-interaction` (resolves to v3.4.0, §11). `config/geoip.php`
with `'database_path' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb'))`.
`App\Support\Geo\GeoLocator`: lazily open a `GeoIp2\Database\Reader` on the configured path; each of
`country`/`region`/`city`/`isp` returns `?string` — `null` when the file is missing/unreadable or the
lookup throws (`AddressNotFoundException`). Add `storage/app/geoip/` to `.gitignore` (next to
`/storage/app/private-media`). Unit-test all null paths with no `.mmdb` present.

**Files**
- `composer.json` — edit: `composer require` adds the package
- `config/geoip.php` — new
- `app/Support/Geo/GeoLocator.php` — new
- `.gitignore` — edit: `storage/app/geoip/`
- `tests/Unit/Phase2/GeoLocatorTest.php` — new

**Acceptance**

1. **WHEN** `composer require geoip2/geoip2:^3.4` runs **THE SYSTEM SHALL** exit 0 and `config/geoip.php` **SHALL** define a `database_path` defaulting to `storage/app/geoip/GeoLite2-City.mmdb` and overridable by `GEOIP_DATABASE_PATH`.
2. **WHEN** `GeoLocator` is asked for country, region, city or ISP and the `.mmdb` file is absent **THE SYSTEM SHALL** return null for each and never throw.
3. **WHEN** `.gitignore` is inspected **THE SYSTEM SHALL** exclude `storage/app/geoip/`.
4. **WHEN** `tests/Unit/Phase2/GeoLocatorTest.php` runs with no `.mmdb` present **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
composer require geoip2/geoip2:^3.4 --no-interaction
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
git check-ignore -q storage/app/geoip/GeoLite2-City.mmdb; test $? -eq 0
./vendor/bin/pest tests/Unit/Phase2/GeoLocatorTest.php
```

> `git check-ignore -q <path>` exits **0** when the path is ignored (the pass case), **1** when not,
> **128** on usage error. The `; test $? -eq 0` asserts the specific pass code, so a usage error
> fails the gate (blueprint §9 rule 16).

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: geoip2/geoip2 + null-safe GeoLocator; ignore storage/app/geoip"
git tag p2-step-36-geolocator
```

### `E5-T3` — Add the Livewire terminal command widget to public pages

**Depends on:** `E4-T9` · **Priority:** p2

`App\Livewire\Terminal`: a small command loop — `help` (list commands), `dir`/`ls` (list public
pages; include `/life` only when the active theme shows it), `cd <page>` (`return redirect(...)` to a
resolved public route), `whoami` (fetch `/whoami` / call `WhoamiController` logic — wired fully in
E5-T4; here `whoami` may return a stub), `clear`, `matrix` (set the `miautrix_theme` cookie to
`matrix`, reusing `ThemeController` logic, then redirect). Output in a terminal-style popup, not
persisted. Mount `<livewire:terminal>` in `layouts/app.blade.php` **only** on public pages, never in
the Filament panel.

**Files**
- `app/Livewire/Terminal.php` — new
- `resources/views/livewire/terminal.blade.php` — new
- `resources/views/layouts/app.blade.php` — edit: mount on public pages only
- `tests/Feature/Phase2/TerminalWidgetTest.php` — new

**Acceptance**

1. **WHEN** the `help` command is entered **THE SYSTEM SHALL** list `help`, `dir`/`ls`, `cd`, `whoami`, `clear` and `matrix`.
2. **WHEN** `cd projects` is entered **THE SYSTEM SHALL** issue a browser redirect to `/projects`.
3. **WHEN** `matrix` is entered **THE SYSTEM SHALL** set the `miautrix_theme` cookie to `matrix`.
4. **WHEN** an admin panel page is rendered **THE SYSTEM SHALL** NOT mount the terminal widget.
5. **WHEN** `tests/Feature/Phase2/TerminalWidgetTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/TerminalWidgetTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: livewire terminal command widget on public pages"
git tag p2-step-37-terminal-widget
```

### `E5-T4` — Add the `whoami` payload endpoint and open geolocation on public routes

**Depends on:** `E5-T2`, `E5-T3` · **Priority:** p2

`WhoamiController`: return the payload from blueprint §5 — `ip` (`$request->ip()`), `isp`/`city`/
`region`/`country` from `GeoLocator` (null without the `.mmdb`), `ua`, and `timezone`/`screen`/`gps`
as `null` (the client fills those). **No `mac` key.** Route `/whoami` (`whoami.show`), outside
`cache.public`. In `SecurityHeaders`, when `! $request->is('admin*')`, send
`Permissions-Policy` with `geolocation=(self)` instead of `geolocation=()`; `/admin` unchanged. The
terminal widget's `whoami` command now renders this payload, printing `location: unavailable` when
geo is null and `gps: permission denied` when the client reports a denial.

**Files**
- `app/Http/Controllers/Public/WhoamiController.php` — new
- `app/Http/Middleware/SecurityHeaders.php` — edit: `geolocation=(self)` on public routes only
- `routes/web.php` — edit: `/whoami`
- `tests/Feature/Phase2/WhoamiTest.php` — new

**Acceptance**

1. **WHEN** `GET /whoami` is requested **THE SYSTEM SHALL** return a payload containing an `ip` key from the request IP and `isp`/`city`/`region`/`country` keys that are null when the GeoLite2 `.mmdb` is absent.
2. **WHEN** `GET /whoami` responds **THE SYSTEM SHALL** NOT include any `mac` key.
3. **WHEN** a public (non-admin) response is inspected **THE SYSTEM SHALL** send `Permissions-Policy` allowing `geolocation=(self)`; **WHEN** an `/admin` response is inspected it **SHALL** still send `geolocation=()`.
4. **WHEN** `tests/Feature/Phase2/WhoamiTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/WhoamiTest.php
./vendor/bin/pest tests/Feature/Security/HeadersTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: whoami payload + geolocation=(self) on public routes"
git tag p2-step-38-whoami
```

### `E5-T5` — Add the `tools` and `tool_downloads` schema with their models

**Depends on:** `E5-T2` · **Priority:** p1

Migrations for `tools` (per the data-model table, minus the `gh_*` columns — those come in E5-T7) and
`tool_downloads` (`tool_id` FK `cascadeOnDelete`, the nullable geo/referrer/UA columns, `created_at`
only). `Tool` model: `$fillable`, `slug`/`published`/`sort_order` + `SoftDeletes`, a `tool_file`
single-file media collection on `private-media`, a `publishedScope`. `ToolDownload` model
(`$timestamps = false`, `created_at` cast). Factories.

**Files**
- `app/Models/Tool.php` — new
- `app/Models/ToolDownload.php` — new
- `database/factories/ToolFactory.php` — new
- `database/factories/ToolDownloadFactory.php` — new
- `tests/Feature/Phase2/ToolSchemaTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `tools` (title, unique `slug`, `summary`, `description`, `version`, `published`, `repo_url`, `sort_order`, soft deletes) and `tool_downloads` (tool_id FK `cascadeOnDelete`, nullable `ip`/`country`/`region`/`city`/`isp`/`referrer`/`user_agent`, `created_at` only).
2. **WHEN** a `Tool` is force-deleted **THE SYSTEM SHALL** cascade-delete its `tool_downloads`; **WHEN** it is soft-deleted **THE SYSTEM SHALL** keep them.
3. **WHEN** a file is attached to a `Tool` **THE SYSTEM SHALL** store it in the single-file `tool_file` media collection on the `private-media` disk.
4. **WHEN** `tests/Feature/Phase2/ToolSchemaTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/ToolSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: tools + tool_downloads schema and models"
git tag p2-step-39-tools-schema
```

### `E5-T6` — Add the public Tools pages, logging download route and Filament resource

**Depends on:** `E5-T5` · **Priority:** p1

`ToolController@index` (`Tool::published()->orderBy('sort_order')`) and `@show` (404 unless
published). `ToolDownloadController`: 404 unless published; insert one `ToolDownload` row (`ip` from
`$request->ip()`, geo via `GeoLocator` — null without the `.mmdb`, `referrer`/`user_agent` from
headers); stream the `tool_file` media with `Content-Disposition: attachment`. Routes: `/tools`,
`/tools/{slug}` in `cache.public`; `/tools/{slug}/download` (`tools.download`) **outside** it.
`ToolResource` (`--generate` then edit) + a `ToolDownloadsRelationManager` or a stats widget (counts,
country breakdown, referrers). `ToolObserver` → `InvalidatePublicPageCache::forAllThemes('tools')`;
register it.

**Files**
- `app/Http/Controllers/Public/ToolController.php` — new
- `app/Http/Controllers/Public/ToolDownloadController.php` — new
- `routes/web.php` — edit: `/tools`, `/tools/{slug}` (cached), `/tools/{slug}/download` (uncached)
- `app/Filament/Resources/Tools/ToolResource.php` — new (generated then edited; + RelationManager, ToolObserver, ToolPolicy in the same task)
- `tests/Feature/Phase2/ToolsPublicAdminTest.php` — new

**Acceptance**

1. **WHEN** `/tools` is requested **THE SYSTEM SHALL** list only `published` tools; **WHEN** `/tools/{slug}` targets an unpublished tool **THE SYSTEM SHALL** return 404.
2. **WHEN** `GET /tools/{slug}/download` is requested for a published tool **THE SYSTEM SHALL** stream the `tool_file` and insert exactly one `tool_downloads` row carrying the request `ip` (geo fields null without the `.mmdb`).
3. **WHEN** a `Tool` is saved **THE SYSTEM SHALL** forget the `/tools` index cache entry for every seeded theme key, and the download route **SHALL** never be cached.
4. **WHEN** the super_admin opens the ToolResource **THE SYSTEM SHALL** show a CRUD table and a downloads breakdown (relation manager or stats widget).
5. **WHEN** `tests/Feature/Phase2/ToolsPublicAdminTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ToolsPublicAdminTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: /tools pages, logging download route, ToolResource"
git tag p2-step-40-tools-public-admin
```

### `E5-T7` — Add cached GitHub repo stats with a stored-column fallback

**Depends on:** `E5-T6` · **Priority:** p2

Migration adding nullable `gh_stars`, `gh_forks`, `gh_language`, `gh_license`, `gh_pushed_at`,
`gh_fetched_at` to `tools`. `App\Support\Github\RepoStats::for(Tool $tool)`: when `repo_url` is a
`github.com` URL and `gh_fetched_at` is null or older than 1 hour, `Http::withToken(env('GITHUB_TOKEN'))?`
`->get('https://api.github.com/repos/{owner}/{repo}')`, `Cache::remember` the parsed result for 1
hour, and persist `gh_*` to the tool row. On any non-2xx or exception, return the existing `gh_*`
column values and touch nothing. `tools/show.blade.php` renders the stats with a "last updated
{gh_fetched_at}" line. `Http::fake()` in the test.

**Files**
- `app/Support/Github/RepoStats.php` — new
- `app/Models/Tool.php` — edit: `gh_*` casts + a `repoStats()` accessor calling `RepoStats`
- `resources/views/public/tools/show.blade.php` — edit: render stats + staleness note
- `tests/Feature/Phase2/RepoStatsTest.php` — new
- *(the migration — generated)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** add nullable `gh_stars`, `gh_forks`, `gh_language`, `gh_license`, `gh_pushed_at` and `gh_fetched_at` columns to `tools`.
2. **WHEN** a tool with a github.com `repo_url` is viewed and its stats are stale (older than one hour or never fetched) **THE SYSTEM SHALL** fetch repo stats over HTTP, cache them for one hour, and persist them to the `gh_*` columns.
3. **WHEN** the same tool is viewed again within the hour **THE SYSTEM SHALL** NOT make a second HTTP request.
4. **WHEN** the GitHub request fails (for example returns 503) **THE SYSTEM SHALL** render the tool page using the last stored `gh_*` values and a staleness note, never a 5xx.
5. **WHEN** `tests/Feature/Phase2/RepoStatsTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/RepoStatsTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: cached GitHub repo stats with stored-column fallback"
git tag p2-step-41-github-repo-stats
```

### `E5-T8` — Add the `page_views` schema and the flag-gated `RecordPageView` middleware

**Depends on:** `E5-T2` · **Priority:** p1

Migration for `page_views` (per the data-model table). `App\Http\Middleware\RecordPageView`:
early-return unless `config('site.analytics.record_page_views')` is true; then early-return for
non-GET, `$request->is('admin*')`, asset paths, and known bot UAs; otherwise insert one row —
`path` (`$request->path()`), `referrer_host` (host of `Referer` or null), `country` (`GeoLocator`,
null without the `.mmdb`), `device` (coarse from UA), `channel` (from path: `life*` → `life`,
`blog*`/`/` → `professional`, else `other`). Append it to the global stack in `bootstrap/app.php`
next to `SecurityHeaders`. `PageView` model + factory.

**Files**
- `app/Http/Middleware/RecordPageView.php` — new
- `bootstrap/app.php` — edit: `->append(RecordPageView::class)`
- `app/Models/PageView.php` — new
- `database/factories/PageViewFactory.php` — new
- `tests/Feature/Phase2/RecordPageViewTest.php` — new

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `page_views` with `path`, nullable `referrer_host`, nullable `country`, nullable `device`, `channel` and `created_at` only.
2. **WHEN** `config('site.analytics.record_page_views')` is true and `GET /` is requested **THE SYSTEM SHALL** insert exactly one `page_views` row.
3. **WHEN** the flag is true and an `/admin/...` path, a non-GET request, an asset path, or a known bot user agent is seen **THE SYSTEM SHALL** insert zero rows and return before any database write.
4. **WHEN** the flag is false **THE SYSTEM SHALL** insert zero rows for any request.
5. **WHEN** `tests/Feature/Phase2/RecordPageViewTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/RecordPageViewTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: page_views schema + flag-gated RecordPageView middleware"
git tag p2-step-42-page-views
```

### `E5-T9` — Add the Filament Analytics dashboard and turn the page-view flag on

**Depends on:** `E5-T8`, `E5-T7`, `E2-T3` (the dashboard aggregates `share_clicks`) · **Priority:** p1

Filament `Analytics` Page (custom Page) with a range selector. `AnalyticsOverviewWidget`: total
views, downloads, share clicks over the range. `AnalyticsTopPathsWidget`: top paths, referrer hosts,
country breakdown, professional-vs-life split. All queries against `page_views` / `tool_downloads` /
`share_clicks` — no third-party JS, no external chart CDN (render with server-side aggregates + a
`'self'` chart script if any). Then set `config/site.php` so `site.analytics.record_page_views`
defaults to `true`. Seed factory rows in the test and assert the totals.

**Files**
- `app/Filament/Pages/Analytics.php` — new
- `app/Filament/Widgets/AnalyticsOverviewWidget.php` — new
- `app/Filament/Widgets/AnalyticsTopPathsWidget.php` — new
- `config/site.php` — edit: `site.analytics.record_page_views` default → `true`
- `tests/Feature/Phase2/AnalyticsDashboardTest.php` — new

**Acceptance**

1. **WHEN** the super_admin opens the Analytics Page with seeded `page_views`, `tool_downloads` and `share_clicks` rows **THE SYSTEM SHALL** render totals for views, downloads and share clicks that equal the seeded counts over the selected range.
2. **WHEN** the Analytics widgets render **THE SYSTEM SHALL** show top paths, referrer hosts, a country breakdown and a professional-vs-life split, all from first-party tables.
3. **WHEN** any Analytics or public page loads **THE SYSTEM SHALL** include no `<script>` whose src is a non-`'self'` origin.
4. **WHEN** `config('site.analytics.record_page_views')` reads its default **THE SYSTEM SHALL** return true.
5. **WHEN** `tests/Feature/Phase2/AnalyticsDashboardTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/AnalyticsDashboardTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: self-hosted Analytics dashboard; page-view flag on"
git tag p2-step-43-analytics-dashboard-on
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** the owner saves SMTP settings and triggers "Send test email" **THE SYSTEM SHALL** apply the settings to `config('mail.*')` at boot and dispatch a `TestMail`, with the stored password never rendered.
2. **WHEN** a visitor downloads a published tool **THE SYSTEM SHALL** stream the file, write exactly one `tool_downloads` row (geo null without the `.mmdb`), and the Analytics total **SHALL** increase by one; an unpublished tool **SHALL** 404.
3. **WHEN** `GET /` is requested with the analytics flag on **THE SYSTEM SHALL** write exactly one `page_views` row and `GET /admin/...` **SHALL** write none; `whoami` **SHALL** return an `ip` and a null `isp` with no `.mmdb` and no `mac` key.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
./vendor/bin/pest tests/Feature/Security/HeadersTest.php
```

## Pitfalls

- **A geo test that needs the `.mmdb`.** Every geo assertion is written against `null`. The file is
  never in CI or a fresh clone.
- **Echoing the stored SMTP password back into the form.** The field renders empty; a save only
  writes a non-empty value.
- **`RecordPageView` doing work before the early-returns.** Flag → method → path/UA checks must all
  run before any `PageView::create`.
- **A third-party chart CDN in the Analytics page.** `'self'` only — no external `<script>`.
- **Caching the tool download route.** It logs and streams — never `cache.public`.
- **A `RepoStats` call that 5xxs the tool page on a GitHub outage.** Catch and fall back to the
  `gh_*` columns.
- **Mounting `<livewire:terminal>` in the Filament panel.** Public pages only.
- **A `mac` field or a silent GPS omission in `whoami`.** No `mac`, ever; GPS denial is printed.

## Before moving on

- [ ] Every task is `done` in `tasks.json` — none `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited; none skipped for a missing file.
- [ ] Every task has its `p2-step-*` checkpoint tag (`git tag -l 'p2-step-3[5-9]-*' 'p2-step-4[0-3]-*'` → 9).
- [ ] Gate passes clean from the project root with the bundle present, and with **no** `.mmdb` file present.
- [ ] `geoip2/geoip2` is in `composer.json` with a commit message stating why (§11); `storage/app/geoip/` is git-ignored.
- [ ] Every "Produced" contract exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged (the `SITE_ANALYTICS_RECORD_PAGE_VIEWS`, `GEOIP_DATABASE_PATH`, `GITHUB_TOKEN` keys were added in Epic 01).
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote; the sponsor exercised "Send test email" once on staging.
