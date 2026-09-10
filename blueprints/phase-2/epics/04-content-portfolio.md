# Epic 04: Content & Portfolio Credibility

> After this epic, any project or article downloads as a real PDF, projects can carry supplementary
> files and optional delivery metrics (with an "On time · On budget" badge), and a second
> "Life / Gaming" blog exists at `/life` — visible only under a `shows_life_blog` theme, with lazy
> click-to-play YouTube embeds under a `/life`-scoped CSP. `/feed.xml` stays professional-only.
> Backlog items 5, 7, 8, 11, 11.1.

| | |
|---|---|
| **Epic id** | `04-content-portfolio` |
| **Tasks** | `E4-T1` … `E4-T9` |
| **Depends on** | `03-theming-navigation` |
| **Unlocks** | `05-platform-analytics` |
| **Parallel with** | nothing |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` · Livewire 4 · Filament 5 · Blade + Tailwind v4 + Alpine · Vite · PostgreSQL ·
`spatie/laravel-medialibrary` · Pest · Pint · Larastan. **No Redis.** Package manager: Composer + npm.
**This epic installs one Composer package: `barryvdh/laravel-dompdf` v3.1.2** (pin/provenance in
blueprint §11), via `composer require barryvdh/laravel-dompdf:^3.1` in `E4-T1`. Everything else is at
its lockfile version.

| Task | Command |
|---|---|
| Install the PDF package | `composer require barryvdh/laravel-dompdf:^3.1 --no-interaction` (E4-T1 only) |
| Format / analyse | `./vendor/bin/pint --test` · `./vendor/bin/phpstan analyse` |
| Build assets | `npm run build` (before `pest`) |
| Test (one file) | `./vendor/bin/pest tests/{Feature,Unit}/Phase2/<Name>Test.php` |
| Migrate | `php artisan migrate` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task is marked done.

**Release sub-flow (every task):** PR → CI `ci` green → merge → `bash infra/deploy-staging.sh` →
**sponsor review & approval on staging** → `bash infra/deploy.sh` → live verify. The sponsor gate is
human. Before promoting the PDF work, the sponsor renders a representative project and article PDF on
staging (Risk #3). Batches: PDF (T1–T2), project files + metrics (T3–T6), Life blog + YouTube
(T7–T9).

## Directory subtree

```
composer.json                              # EDIT E4-T1 — composer require adds barryvdh/laravel-dompdf
config/dompdf.php                           # NEW E4-T1 — published
config/site.php                             # EDIT E4-T9 — site.csp.youtube_on_life default -> true
database/migrations/                        # projects delivery-metrics columns; articles.channel
app/Models/
  Project.php                               # EDIT E4-T3 (project_files media collection), E4-T5 (metric accessors)
  Article.php                               # EDIT E4-T7 — channel column + scopes
app/Http/Controllers/Public/
  ProjectPdfController.php ArticlePdfController.php   # NEW E4-T2
  ProjectFileDownloadController.php         # NEW E4-T4
  LifeController.php                        # NEW E4-T8
app/Http/Middleware/SecurityHeaders.php    # EDIT E4-T9 — /life-scoped YouTube frame-src/img-src
app/Actions/Cache/InvalidatePublicPageCache.php   # EDIT E4-T8 — forLife()
app/Filament/Resources/
  Projects/Schemas/ProjectForm.php          # EDIT E4-T3 (Files upload), E4-T6 ("Delivery metrics" section)
  Articles/Schemas/ArticleForm.php          # EDIT E4-T7 — channel Select
  Articles/Tables/ArticlesTable.php         # EDIT E4-T7 — channel filter
resources/views/
  pdf/project.blade.php pdf/article.blade.php        # NEW E4-T1 — inline CSS only
  components/youtube-embed.blade.php        # NEW E4-T9
  components/project-card.blade.php         # EDIT E4-T6 — "On time · On budget" badge
  public/projects/show.blade.php            # EDIT E4-T2/T4/T6 — Download PDF button, Files list, stat strip
  public/life/index.blade.php              # NEW E4-T8 (+ show.blade.php, reusing blog partials)
routes/web.php                              # EDIT — /projects|blog/{slug}/pdf; /projects/{project}/files/{media}; /life, /life/{slug}
tests/{Feature,Unit}/Phase2/                # PdfViewsRender, PdfDownload, ProjectFilesUpload,
                                            # ProjectFilesPublic, ProjectMetricsAccessor(unit),
                                            # DeliveryMetricsUi, ArticleChannel, LifeBlogVisibility,
                                            # YoutubeCspScope
```

Everything outside this subtree is out of scope.

## Data model touched here

| Entity | Fields this epic adds or reads | Notes |
|---|---|---|
| `projects` | add nullable `budget_planned`/`budget_actual` (decimal 12,2), `planned_start`/`planned_end`/`actual_start`/`actual_end` (date), `team_size` (int), `role`/`outcome` (string) | all optional; no new index (not filtered publicly). Model accessors `schedulePerformancePct`, `budgetPerformancePct`, `isOnTime`, `isOnBudget` — all null-safe. |
| `projects` | `project_files` **media collection** (spatie) — no column | matches the gallery pattern; served via a controller that re-checks `published` |
| `articles` | add `channel` string(20) not null default `professional`, indexed | scopes `professional()` / `life()` / `channel(string)`. `/feed.xml` query gains `->professional()`. |

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `App\Models\Article::scopePublished()` | gates on `published_at` (non-null, past) |
| Phase 1 | `App\Http\Controllers\Public\BlogController@feed` + `resources/views/feed.xml.blade.php` | RSS 2.0 of published articles at `/feed.xml` |
| Phase 1 | `App\Http\Controllers\Public\DocumentDownloadController` | streams a published document, mirrors the published-state gate this epic's file/PDF controllers copy |
| Phase 1 | `MediaUploadField` / `App\Rules\AllowedMediaMime` | hardened upload factory — sniffed MIME, generated filename, `private-media` disk, **no SVG** |
| Phase 1 | `App\Http\Middleware\SecurityHeaders` (`PUBLIC_CSP` template, `$request->is('admin*')`) | per-route CSP with `Vite::useCspNonce()` |
| Epic 03 | `App\Support\Theming\ThemeResolver::active(Request): Theme`; `themes.shows_life_blog` | the active theme, and whether it shows the Life blog |
| Epic 03 | `<x-nav-menu>` | the menubar the "Life" entry is added to |
| Epic 03 | `InvalidatePublicPageCache::forAllThemes()` | host+theme-aware forget |
| Epic 01 | `config('site.csp.youtube_on_life')` | flag, default `false` until E4-T9 |

**Produced** — later epics depend on these:

| Export | Signature | Used by |
|---|---|---|
| `articles.channel` + `Article::professional()` / `life()` | channel-scoped queries; `/feed.xml` professional-only | E5-T8/T9 (analytics infers `channel` from path; professional-vs-life split) |
| `/life`, `/life/{slug}` routes, theme-gated | 200 under a `shows_life_blog` theme, 404 otherwise | E5-T3 terminal `dir` lists them only when visible; E5-T9 split |
| `<x-youtube-embed id>` + `[youtube:ID]` shortcode | click-to-play facade, no iframe in initial HTML | none downstream (leaf) |
| `config('site.csp.youtube_on_life')` = `true` (default, E4-T9) | `/life`-scoped YouTube CSP live | — |

## Conventions that bite in this area

- **PDF routes are NOT cached.** Register them outside the `cache.public` group (streamed binary) —
  same reasoning as the Phase-1 media/document download routes.
- **Print Blade views carry inline CSS only** — no `@vite`, no `<link>`, no external font. dompdf
  cannot fetch assets; a `<link>` silently drops.
- **Never invent a migration filename.** `php artisan make:migration` names it; refer to "the
  migration that command emitted".
- **Reuse the hardened upload pipeline.** `project_files` and `tool_file` (Epic 05) go through
  `MediaUploadField` / `SpatieMediaLibraryFileUpload` with the existing allowlist plus PDF/ZIP —
  **SVG is still never accepted** (`.claude/rules/security.md`).
- **The Life blog is theme-gated in the controller AND the nav** — both call `ThemeResolver`. The
  page cache key already carries the theme (Epic 03), so a `/life` entry cached under Matrix is never
  served to a Console visitor; still add an explicit `abort(404)` — do not rely on cache alone.
- **`/feed.xml` stays professional-only.** Add `->professional()` to the feed query; a `channel='life'`
  article must never appear there (frozen contract, blueprint §5).
- **The `/life`-scoped CSP addition changes only `/life*` responses.** `/blog/*` and `/` must send a
  `Content-Security-Policy` byte-identical to today. Scope on `config('site.csp.youtube_on_life') && $request->is('life*')`.
- **Model metric accessors are null-safe** — return `null` when an input is missing, never divide by
  zero or throw. Percentages are computed from planned/actual, never stored.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/database.md`, `.claude/rules/filament.md`,
`.claude/rules/security.md`, `.claude/rules/theming.md`.

---

## Tasks

Listed in `tasks.json` order. Work top to bottom.

### `E4-T1` — Install `barryvdh/laravel-dompdf` and add the two print Blade views

**Depends on:** `E3-T9` · **Priority:** p1

`composer require barryvdh/laravel-dompdf:^3.1 --no-interaction` (resolves to v3.1.2, §11), then
`php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"` to write `config/dompdf.php`.
Build `resources/views/pdf/project.blade.php` and `resources/views/pdf/article.blade.php` — a clean
print layout with a single inline `<style>` block, no `@vite`, no external resource. Commit the reason
for the new dependency in the message (`.claude/rules` — no new dep without a stated reason).

**Files**
- `composer.json` — edit: `composer require` adds the package
- `config/dompdf.php` — new (published)
- `resources/views/pdf/project.blade.php` — new
- `resources/views/pdf/article.blade.php` — new
- `tests/Feature/Phase2/PdfViewsRenderTest.php` — new

**Acceptance**

1. **WHEN** `composer require barryvdh/laravel-dompdf:^3.1` runs **THE SYSTEM SHALL** exit 0 and `config/dompdf.php` **SHALL** be published.
2. **WHEN** `resources/views/pdf/project.blade.php` and `resources/views/pdf/article.blade.php` are rendered **THE SYSTEM SHALL** produce HTML with only inline `<style>` CSS and no external `<link>` or `<script>` element.
3. **WHEN** `tests/Feature/Phase2/PdfViewsRenderTest.php` runs **THE SYSTEM SHALL** report 2 passing tests, 0 skipped.

**Verify**

```bash
composer require barryvdh/laravel-dompdf:^3.1 --no-interaction
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/PdfViewsRenderTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: add barryvdh/laravel-dompdf + print Blade views (inline CSS)"
git tag p2-step-26-dompdf-install-views
```

### `E4-T2` — Add the project and blog PDF download routes and buttons

**Depends on:** `E4-T1` · **Priority:** p1

`ProjectPdfController` / `ArticlePdfController`: `firstWhere('slug', …)` scoped to published (else
`abort(404)`), render the print view to a streamed PDF (`Pdf::loadView('pdf.project', […])->download("…")`).
Register `/projects/{slug}/pdf` (`projects.pdf`) and `/blog/{slug}/pdf` (`blog.pdf`) **outside**
`cache.public`. Add a "Download PDF" button to `resources/views/public/projects/show.blade.php` and
the blog show view.

**Files**
- `app/Http/Controllers/Public/ProjectPdfController.php` — new
- `app/Http/Controllers/Public/ArticlePdfController.php` — new
- `routes/web.php` — edit: two PDF routes, outside `cache.public`
- `resources/views/public/projects/show.blade.php` — edit: "Download PDF" button
- `resources/views/public/blog/show.blade.php` — edit: "Download PDF" button
- `tests/Feature/Phase2/PdfDownloadTest.php` — new

**Acceptance**

1. **WHEN** `GET /projects/{slug}/pdf` is requested for a published project **THE SYSTEM SHALL** return 200 with `Content-Type: application/pdf` and a non-empty body.
2. **WHEN** `GET /blog/{slug}/pdf` is requested for a published article **THE SYSTEM SHALL** return 200 with `Content-Type: application/pdf` and a non-empty body.
3. **WHEN** either PDF route is requested for an unpublished entity **THE SYSTEM SHALL** return 404.
4. **WHEN** the route table is inspected **THE SYSTEM SHALL** show neither PDF route inside the `cache.public` middleware group.
5. **WHEN** `tests/Feature/Phase2/PdfDownloadTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/PdfDownloadTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: project & blog PDF download routes + buttons"
git tag p2-step-27-pdf-routes
```

### `E4-T3` — Add a `project_files` media collection and its Filament upload field

**Depends on:** `E3-T9` · **Priority:** p1

Register a `project_files` media collection on `Project` (`registerMediaCollections()`), on the
`private-media` disk, accepting the existing allowlist plus `application/pdf` and
`application/zip` — **never** `image/svg+xml`. In `ProjectForm`, add a repeatable
`SpatieMediaLibraryFileUpload` (or the repo's `MediaUploadField`) bound to `project_files`, with an
optional per-file label.

**Files**
- `app/Models/Project.php` — edit: `project_files` collection registration
- `app/Filament/Resources/Projects/Schemas/ProjectForm.php` — edit: Files upload field
- `tests/Feature/Phase2/ProjectFilesUploadTest.php` — new

**Acceptance**

1. **WHEN** a super_admin uploads a PDF or ZIP to a project's Files field in Filament **THE SYSTEM SHALL** store it in the `project_files` media collection on the `private-media` disk with a generated filename.
2. **WHEN** an SVG is uploaded to that field **THE SYSTEM SHALL** reject it with a validation error and store nothing.
3. **WHEN** `tests/Feature/Phase2/ProjectFilesUploadTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/ProjectFilesUploadTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: project_files media collection + hardened upload field"
git tag p2-step-28-project-files-collection
```

### `E4-T4` — Render project files publicly with a published-state download controller

**Depends on:** `E4-T3` · **Priority:** p1

`ProjectFileDownloadController` mirroring `DocumentDownloadController`: resolve the `Project`, 404
unless `published`, resolve the `Media` from its `project_files` collection, stream it with
`Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`. Route
`/projects/{project}/files/{media}` (`projects.file`), outside `cache.public`. On the public project
detail page, render a "Files" list when the collection is non-empty.

**Files**
- `app/Http/Controllers/Public/ProjectFileDownloadController.php` — new
- `routes/web.php` — edit: `/projects/{project}/files/{media}`
- `resources/views/public/projects/show.blade.php` — edit: "Files" list
- `tests/Feature/Phase2/ProjectFilesPublicTest.php` — new

**Acceptance**

1. **WHEN** a published project has a file in `project_files` **THE SYSTEM SHALL** render a "Files" list on its public detail page with a working download link.
2. **WHEN** `GET /projects/{project}/files/{media}` targets a file of an unpublished project **THE SYSTEM SHALL** return 404.
3. **WHEN** the download link is followed for a published project **THE SYSTEM SHALL** stream the stored file with a `Content-Disposition: attachment` header.
4. **WHEN** `tests/Feature/Phase2/ProjectFilesPublicTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ProjectFilesPublicTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: public project files list + published-gated download"
git tag p2-step-29-project-files-public
```

### `E4-T5` — Add nullable delivery-metrics columns and null-safe `Project` accessors

**Depends on:** `E3-T9` · **Priority:** p1

Migration adding the nine nullable columns to `projects` (see data-model table). Model accessors:
- `schedulePerformancePct` — `null` unless all four schedule dates are set; else planned duration ÷
  actual duration × 100.
- `budgetPerformancePct` — `null` unless both budgets set; else `budget_planned ÷ budget_actual × 100`.
- `isOnTime` — `null` unless `planned_end` and `actual_end` set; else `actual_end <= planned_end`.
- `isOnBudget` — `null` unless both budgets set; else `budget_actual <= budget_planned`.

Unit-test each with full, partial and empty inputs.

**Files**
- `app/Models/Project.php` — edit: four null-safe accessors + casts for the new columns
- `tests/Unit/Phase2/ProjectMetricsAccessorTest.php` — new
- *(the migration — generated)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** add nullable `budget_planned`, `budget_actual`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `team_size`, `role` and `outcome` columns to `projects`.
2. **WHEN** `schedulePerformancePct`, `budgetPerformancePct`, `isOnTime` or `isOnBudget` is read on a project missing the inputs it needs **THE SYSTEM SHALL** return null, never throw.
3. **WHEN** all inputs are present **THE SYSTEM SHALL** compute the percentages from planned vs actual values, not from a stored column.
4. **WHEN** `tests/Unit/Phase2/ProjectMetricsAccessorTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Unit/Phase2/ProjectMetricsAccessorTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: project delivery-metrics columns + null-safe accessors"
git tag p2-step-30-delivery-metrics-schema
```

### `E4-T6` — Add the delivery-metrics admin section, public stat strip and card badge

**Depends on:** `E4-T5` · **Priority:** p1

`ProjectForm`: a collapsible "Delivery metrics" `Section` with all nine fields, every one optional.
Public project detail: render a stat strip (planned vs actual budget, schedule performance %, team
size, role, outcome) **only** when at least one metric is present. `project-card.blade.php`: an
"On time · On budget" badge **only** when `isOnTime && isOnBudget` are both true.

**Files**
- `app/Filament/Resources/Projects/Schemas/ProjectForm.php` — edit: "Delivery metrics" section
- `resources/views/public/projects/show.blade.php` — edit: conditional stat strip
- `resources/views/components/project-card.blade.php` — edit: conditional badge
- `tests/Feature/Phase2/DeliveryMetricsUiTest.php` — new

**Acceptance**

1. **WHEN** a project has one or more delivery metrics set **THE SYSTEM SHALL** render a stat strip on its public detail page; **WHEN** it has none **THE SYSTEM SHALL** render neither the strip nor a placeholder.
2. **WHEN** a project's `isOnTime` and `isOnBudget` are both true **THE SYSTEM SHALL** render an "On time · On budget" badge on its card; otherwise no badge.
3. **WHEN** the stat strip renders a percentage **THE SYSTEM SHALL** show the value computed from planned/actual, matching the model accessor.
4. **WHEN** `tests/Feature/Phase2/DeliveryMetricsUiTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/DeliveryMetricsUiTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: delivery-metrics admin section, stat strip, on-time/on-budget badge"
git tag p2-step-31-delivery-metrics-ui
```

### `E4-T7` — Add the article `channel` column, scopes, admin filter and professional-only feed

**Depends on:** `E3-T9` · **Priority:** p0

Migration adding `channel` string(20) not null default `professional` + an index. `Article`:
`scopeChannel($q, string $c)`, `scopeProfessional`, `scopeLife`, `channel` in `$fillable`. Update the
`/feed.xml` query (`BlogController@feed`) to add `->professional()`. `ArticleForm`: a `channel`
`Select` (`professional` / `life`). `ArticlesTable`: a `channel` `SelectFilter`. `ArticleObserver`,
`ArticlePolicy`, cache invalidation are reused unchanged for professional articles.

**Files**
- `app/Models/Article.php` — edit: `channel` scopes + `$fillable`
- `app/Filament/Resources/Articles/Schemas/ArticleForm.php` — edit: `channel` Select
- `app/Filament/Resources/Articles/Tables/ArticlesTable.php` — edit: `channel` filter
- `tests/Feature/Phase2/ArticleChannelTest.php` — new
- *(the migration — generated; `BlogController@feed` gets the `->professional()` scope — a one-line edit noted here, counted within the model/feed change)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** add a non-null `channel` column to `articles` defaulting to `professional`, with an index.
2. **WHEN** `Article::professional()` or `Article::life()` is queried **THE SYSTEM SHALL** return only rows whose `channel` matches.
3. **WHEN** `/feed.xml` is requested **THE SYSTEM SHALL** contain no article whose `channel` is `life`, and `/blog` **SHALL** be unaffected by the new column.
4. **WHEN** the ArticleResource table is used **THE SYSTEM SHALL** offer a `channel` Select on the form and a `channel` filter on the table.
5. **WHEN** `tests/Feature/Phase2/ArticleChannelTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/ArticleChannelTest.php
./vendor/bin/pest tests/Feature/Public/BlogFeedTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: article channel column, scopes, admin filter, professional-only feed"
git tag p2-step-32-article-channel
```

### `E4-T8` — Add the theme-gated `/life` blog routes, nav entry and cache invalidation

**Depends on:** `E4-T7`, `E2-T4` (so Life pages inherit the absolute-URL `OgImage::resolve()`) · **Priority:** p0

`LifeController@index` / `@show`: resolve the active theme via `ThemeResolver`; `abort(404)` when
`shows_life_blog` is false. `index()` lists `Article::life()->published()`; `show()` `firstWhere('slug')`
scoped to `life()->published()`, else 404. Routes `/life` (`life.index`) and `/life/{slug}`
(`life.show`) inside `cache.public` (key already host+theme-scoped). `life/index.blade.php` reuses the
professional blog partials. `<x-nav-menu>`: render the "Writing → Life" entry only when the active
theme's `shows_life_blog` is true. `InvalidatePublicPageCache::forLife(Article $a)` — forget `life`
and `life/{slug}` for every seeded theme/host; call it from `ArticleObserver` when the saved
article's `channel` is `life`. Extend `tests/Feature/A11yTest.php` to sweep `/life` under a permitted
theme.

**Files** (primary; the `Do` text above also mandates one-line edits to `resources/views/components/nav-menu.blade.php` (Life entry, `shows_life_blog`-gated), `app/Observers/ArticleObserver.php` (call `forLife()` for `channel='life'`), and `tests/Feature/A11yTest.php` (sweep `/life`) — all in files an earlier step already created)
- `app/Http/Controllers/Public/LifeController.php` — new
- `routes/web.php` — edit: `/life`, `/life/{slug}` in `cache.public`
- `resources/views/public/life/index.blade.php` — new (+ `show.blade.php`)
- `app/Actions/Cache/InvalidatePublicPageCache.php` — edit: `forLife()`
- `tests/Feature/Phase2/LifeBlogVisibilityTest.php` — new

**Acceptance**

1. **WHEN** `/life` is requested under a theme whose `shows_life_blog` is true **THE SYSTEM SHALL** return 200 and list only published `channel='life'` articles.
2. **WHEN** `/life` or `/life/{slug}` is requested under a theme whose `shows_life_blog` is false **THE SYSTEM SHALL** return 404.
3. **WHEN** the nav is rendered **THE SYSTEM SHALL** show a "Life" entry only under a `shows_life_blog` theme and hide it otherwise.
4. **WHEN** a `channel='life'` article is published or unpublished **THE SYSTEM SHALL** forget the `/life` and `/life/{slug}` cache entries for every seeded theme key.
5. **WHEN** `tests/Feature/Phase2/LifeBlogVisibilityTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/LifeBlogVisibilityTest.php
./vendor/bin/pest tests/Feature/A11yTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: theme-gated /life blog, nav entry, cache invalidation"
git tag p2-step-33-life-blog-routes
```

### `E4-T9` — Add the lazy YouTube facade with a `/life`-scoped CSP and turn the flag on

**Depends on:** `E4-T8` · **Priority:** p1

`resources/views/components/youtube-embed.blade.php`: a thumbnail (`https://i.ytimg.com/vi/{id}/hqdefault.jpg`)
+ a real `<button>` "Play video"; a nonce'd Alpine handler swaps in
`<iframe src="https://www.youtube-nocookie.com/embed/{id}?autoplay=1" title="…">` on click. **No
iframe in the initial HTML.** Add a `[youtube:ID]` shortcode expander for Life-article body rendering
(match how the repo renders RichEditor content). In `SecurityHeaders`, when
`config('site.csp.youtube_on_life')` is true **and** `$request->is('life*')`, append
`frame-src https://www.youtube-nocookie.com` and add `https://i.ytimg.com` to `img-src` in the public
CSP string — every other route's CSP byte-identical. Then set `config/site.php` so
`site.csp.youtube_on_life` defaults to `true`.

**Files**
- `resources/views/components/youtube-embed.blade.php` — new
- `app/Http/Middleware/SecurityHeaders.php` — edit: `/life`-scoped YouTube sources
- `config/site.php` — edit: `site.csp.youtube_on_life` default → `true`
- `tests/Feature/Phase2/YoutubeCspScopeTest.php` — new

**Acceptance**

1. **WHEN** a Life post containing a `[youtube:ID]` shortcode is rendered **THE SYSTEM SHALL** emit a click-to-play facade (thumbnail plus a real play `<button>`) and no `<iframe>` in the initial HTML.
2. **WHEN** `config('site.csp.youtube_on_life')` is true and the request path matches `life*` **THE SYSTEM SHALL** add `frame-src https://www.youtube-nocookie.com` and `https://i.ytimg.com` to `img-src` in the public CSP.
3. **WHEN** the request path is `/blog/*` or `/` **THE SYSTEM SHALL** send a `Content-Security-Policy` byte-identical to its pre-Phase-2 value with no YouTube sources.
4. **WHEN** `config('site.csp.youtube_on_life')` reads its default **THE SYSTEM SHALL** return true.
5. **WHEN** `tests/Feature/Phase2/YoutubeCspScopeTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/YoutubeCspScopeTest.php
./vendor/bin/pest tests/Feature/Security/HeadersTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[security-auditor] feat: lazy YouTube facade + /life-scoped CSP; flag on"
git tag p2-step-34-youtube-embed-and-flag
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a published project and a published article are requested at `/…/pdf` **THE SYSTEM SHALL** each return a non-empty `application/pdf`, and an unpublished one **SHALL** 404.
2. **WHEN** a project has delivery metrics **THE SYSTEM SHALL** show a stat strip and (when on time and on budget) a card badge; **WHEN** it has none, neither renders.
3. **WHEN** a `channel='life'` article is published **THE SYSTEM SHALL** appear on `/life` under Matrix, 404 under Console, be absent from `/blog` and `/feed.xml`, and its embed **SHALL** render a facade with no iframe while `/life/*` responses carry the YouTube `frame-src` and `/blog/*` do not.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
./vendor/bin/pest tests/Feature/Public/BlogFeedTest.php tests/Feature/Security/HeadersTest.php tests/Feature/A11yTest.php
```

## Pitfalls

- **An `@vite` or `<link>` in a print view.** dompdf drops it silently and the PDF renders unstyled.
  Inline `<style>` only.
- **A PDF route inside `cache.public`.** It streams a binary — caching it is a bug.
- **Relying on the theme cache key alone to hide `/life`.** Add an explicit `abort(404)` in
  `LifeController` and hide the nav entry — defence in depth.
- **A `channel='life'` article leaking into `/feed.xml`.** Frozen contract. `->professional()` on the
  feed query, with a test that asserts it.
- **A YouTube source on `/blog/*` or `/`.** The CSP addition is scoped to
  `config(...) && $request->is('life*')`. `YoutubeCspScopeTest` asserts `/` and `/blog/*` are
  byte-identical to today.
- **An iframe in the facade's initial HTML.** It must appear only after the click.

## Before moving on

- [ ] Every task is `done` in `tasks.json` — none `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited; none skipped for a missing file.
- [ ] Every task has its `p2-step-*` checkpoint tag (`git tag -l 'p2-step-2[6-9]-*' 'p2-step-3[0-4]-*'` → 9).
- [ ] Gate passes clean from the project root with the bundle present.
- [ ] `barryvdh/laravel-dompdf` is in `composer.json` with a commit message stating why (§11).
- [ ] Every "Produced" contract exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged (the `SITE_CSP_YOUTUBE_ON_LIFE` key was added in Epic 01; only its default flips here).
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote; the sponsor rendered a real project and article PDF on staging.
