# Epic 02: Reach & Sharing

> After this epic, blog posts carry six working share links backed by a first-party click-logging
> redirect, social preview cards render a real absolute-URL image that crawlers can fetch, and
> `/connect` shows social profiles in admin-managed groups with a trailing "Other" section.
> Backlog items 1, 6 and 9.

| | |
|---|---|
| **Epic id** | `02-reach-sharing` |
| **Tasks** | `E2-T1` … `E2-T8` |
| **Depends on** | `01-staging-foundation` |
| **Unlocks** | `03-theming-navigation` |
| **Parallel with** | nothing (built after Epic 01, before Epic 03) |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` · Livewire 4 · Filament 5 · Blade + Tailwind v4 + Alpine · Vite · PostgreSQL
(tests: network `miautrix_test`, or docker `postgres:18.6` fallback) · Pest · Pint · Larastan. **No
Redis.** `spatie/laravel-medialibrary` for media. Package manager: Composer + npm. Versions live in
the lockfiles. Phase 2 adds **no** package in this epic.

| Task | Command |
|---|---|
| Format / analyse | `./vendor/bin/pint --test` · `./vendor/bin/phpstan analyse` |
| Build assets | `npm run build` (before `pest`) |
| Test (one file) | `./vendor/bin/pest tests/Feature/Phase2/<Name>Test.php` |
| Migrate | `php artisan migrate` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task is marked done.

**Release sub-flow (every task):** PR → CI `ci` green → merge → `bash infra/deploy-staging.sh` →
**sponsor review & approval on staging (`review/<id>.md` = `APROBADO`)** → `bash infra/deploy.sh` →
live verify. The sponsor gate is human; never a task's "Done when". Epic 02's tasks may be reviewed
in two batches (items 1+6, then item 9).

## Directory subtree

```
database/migrations/                         # share_clicks; social_profile_groups; social_profiles.group_id; guarded og_image_id
app/Models/
  ShareClick.php SocialProfileGroup.php      # NEW E2-T1, E2-T6
  SocialProfile.php                          # EDIT E2-T6 — group() belongsTo
app/Support/Seo/OgImage.php                  # EDIT E2-T4 — absolute url()
app/Http/Controllers/Public/
  ShareRedirectController.php                # NEW E2-T3
  MediaController.php                        # EDIT E2-T5 — serve OG media to guests for published entities
  ConnectController.php                      # EDIT E2-T8 — one section per group
app/Filament/Resources/
  SocialProfileGroups/**                     # NEW E2-T7
  SocialProfiles/Schemas/SocialProfileForm.php  # EDIT E2-T7 — group_id Select + inline create
app/Observers/
  SocialProfileGroupObserver.php SocialProfileObserver.php   # NEW E2-T8
app/Policies/SocialProfileGroupPolicy.php    # NEW E2-T7
resources/views/components/share-links.blade.php   # NEW E2-T2
resources/views/public/
  blog/show.blade.php                        # EDIT E2-T3 — mount <x-share-links> at /s/... URLs
  connect.blade.php                          # EDIT E2-T8 — grouped sections
routes/web.php                               # EDIT E2-T3 — GET /s/{network}/{type}/{id}
database/factories/ShareClickFactory.php SocialProfileGroupFactory.php   # NEW
tests/Feature/Phase2/                        # ShareClickSchema, ShareLinksComponent, ShareRedirect,
                                             # OgImageAbsolute, OgImageCrawlerAccess, SocialGroupSchema,
                                             # SocialGroupResource, ConnectGrouped
```

Everything outside this subtree is out of scope — stop and report if a task seems to need more.

## Data model touched here

| Entity | Fields this epic adds or reads | Notes |
|---|---|---|
| `share_clicks` (new) | `network`, `type`, `subject_id`, nullable `referrer`, nullable `ip`, `created_at` (no `updated_at`) | append-only log; no FK to the subject — survives its deletion. Index `(type, subject_id)`, `(network)`, `(created_at)`. |
| `social_profile_groups` (new) | `name`, `heading`, nullable `intro_text`, `sort_order`, timestamps | ordered `/connect` sections. Index `(sort_order)`. |
| `social_profiles` | add nullable `group_id` FK → `social_profile_groups`, `nullOnDelete`; add `group()` belongsTo | ungrouped rows render under "Other". Index `(group_id)`. `show_in_footer` behaviour unchanged. |
| `articles`, `projects` | add nullable `og_image_id` FK → `media`, `nullOnDelete` **only if absent** | Phase 1 gave every publishable entity these SEO fields; E2-T4 verifies first and adds only what is missing. |

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `App\Support\Seo\OgImage::resolve(?int $mediaId): string` | returns a `media.show` route URL or the `og-default.png` asset URL |
| Phase 1 | `App\Http\Controllers\Public\MediaController@show` | streams a `private-media` file; 404s unless the owning entity is published |
| Phase 1 | `App\Http\Controllers\Public\ConnectController` + `resources/views/public/connect.blade.php` | renders one flat list of `SocialProfile` rows |
| Phase 1 | `App\Actions\Cache\InvalidatePublicPageCache` (`forHome()`, `forSocialProfiles()`, …) | Observer → forget the relevant `public-page:{theme}:{path}` keys |
| Epic 01 | `config('site.*')` | the three OFF flags and `canonical_host` |

**Produced** — later epics depend on these:

| Export | Signature | Used by |
|---|---|---|
| `share_clicks` table + `App\Models\ShareClick` | append-only rows: `network`, `type`, `subject_id`, `referrer`, `ip`, `created_at` | E5-T9 (analytics dashboard — share clicks by network) |
| `App\Support\Seo\OgImage::resolve()` | now always returns an **absolute `https://` URL** | E4-T8 (Life posts reuse the blog partials, incl. `<x-meta>`) |
| `social_profile_groups` + `SocialProfile::group()` | grouped `/connect` render | none downstream (leaf feature) |

## Conventions that bite in this area

- **Append-only logging tables have no `updated_at`, no soft delete, no FK to the subject.**
  `.claude/rules/database.md` still requires an explicit `onDelete` on any FK you *do* declare
  (`social_profiles.group_id` → `nullOnDelete`).
- **Share links are plain `href`s.** No SDK `<script>`, no tracking pixel. The optional Web Share API
  button is nonce'd and `hidden` unless `navigator.share` exists — never the only path.
- **Cache correctness is Observer → `InvalidatePublicPageCache`.** A controller or resource never
  forgets a key directly. Add a `forConnect()` / extend `forSocialProfiles()` rather than inlining
  `Cache::forget`.
- **`OgImage::resolve()` must return an absolute URL** — use `url()` / `URL::to()`, never `asset()`
  (which can be path-relative) and never a `//host` scheme-relative string. Crawlers and
  `og:image` validators reject non-absolute values.
- **`media.show` already 404s for unpublished entities.** E2-T5 only *broadens* it to serve a
  published entity's OG media to an unauthenticated request — it never loosens the unpublished gate.
- **Filament resources: generate then edit**, `Schemas/ Tables/ Pages/` split, Policy for authz
  (`.claude/rules/filament.md`).

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/database.md`, `.claude/rules/filament.md`,
`.claude/rules/theming.md`, `.claude/rules/security.md`.

---

## Tasks

Listed in `tasks.json` order. Work top to bottom.

### `E2-T1` — Add the `share_clicks` schema, `ShareClick` model and factory

**Depends on:** `E1-T8` · **Priority:** p1

Create the migration `php artisan make:migration` emits for `share_clicks` (`network` string(20),
`type` string(20), `subject_id` bigint, nullable `referrer` string(255), nullable `ip` string(45),
`created_at` only — set `$table->timestamp('created_at')` explicitly, no `timestamps()`). Model
`App\Models\ShareClick` with `$fillable` explicit, `public $timestamps = false`, a `created_at` cast.
`ShareClickFactory`.

**Files**
- `app/Models/ShareClick.php` — new
- `database/factories/ShareClickFactory.php` — new
- `tests/Feature/Phase2/ShareClickSchemaTest.php` — new
- *(the migration file — referred to by how it is generated, never a hand-named path)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create a `share_clicks` table with `network`, `type`, `subject_id`, nullable `referrer`, nullable `ip` and `created_at`, and no `updated_at` column.
2. **WHEN** a `ShareClick` row is inserted through the model **THE SYSTEM SHALL** persist without touching an `updated_at` column.
3. **WHEN** `tests/Feature/Phase2/ShareClickSchemaTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/ShareClickSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: share_clicks append-only schema + model"
git tag p2-step-09-share-clicks-schema
```

### `E2-T2` — Add the `<x-share-links>` Blade component for six networks

**Depends on:** `E2-T1` · **Priority:** p1

Build `resources/views/components/share-links.blade.php` taking `:url`, `:title`, `:summary`. Emit
six anchors — Facebook (`sharer.php`), X (`x.com/intent/tweet` or `twitter.com/intent/tweet`),
LinkedIn (`linkedin.com/sharing/share-offsite`), WhatsApp (`wa.me` / `api.whatsapp.com/send`),
Reddit (`reddit.com/submit`), email (`mailto:`) — each with `urlencode`d parameters and an accessible
name ("Share on LinkedIn"). Optional progressive enhancement: a nonce'd inline `<script>` that, when
`navigator.share` exists, reveals a native-share button. No external SDK.

**Files**
- `resources/views/components/share-links.blade.php` — new
- `tests/Feature/Phase2/ShareLinksComponentTest.php` — new

**Acceptance**

1. **WHEN** `<x-share-links :url :title :summary />` is rendered **THE SYSTEM SHALL** emit exactly six share links, one each for Facebook, X, LinkedIn, WhatsApp, Reddit and email, with the url/title/summary parameters URL-encoded.
2. **WHEN** the component renders **THE SYSTEM SHALL** emit no external SDK script tag and any progressive-enhancement Web Share script SHALL carry the per-request CSP nonce.
3. **WHEN** `tests/Feature/Phase2/ShareLinksComponentTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ShareLinksComponentTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: x-share-links component, six networks, no SDK"
git tag p2-step-10-share-links-component
```

### `E2-T3` — Add the `GET /s/{network}/{type}/{id}` logging redirect route

**Depends on:** `E2-T2` · **Priority:** p1

`ShareRedirectController`: validate `network` against the six-value set and `type` against
`article` (loose — `subject_id` is not an FK); look up the subject to build the canonical share URL
and the real target URL; insert one `ShareClick` row (`referrer` from the `Referer` header truncated,
`ip` from `$request->ip()`); return `redirect()->away($networkShareUrl, 302)`. Register the route
outside `cache.public`. Wire `<x-share-links>` into `resources/views/public/blog/show.blade.php`
pointing at `/s/...` URLs, not directly at the networks.

**Files**
- `app/Http/Controllers/Public/ShareRedirectController.php` — new
- `routes/web.php` — edit: add `/s/{network}/{type}/{id}` → `share.redirect`
- `resources/views/public/blog/show.blade.php` — edit: mount `<x-share-links>` at `/s/...` URLs
- `tests/Feature/Phase2/ShareRedirectTest.php` — new

**Acceptance**

1. **WHEN** `GET /s/x/article/1` is requested for an existing article **THE SYSTEM SHALL** insert exactly one `share_clicks` row with `network='x'`, `type='article'`, `subject_id=1` and return a 302 whose `Location` host is `x.com` (or `twitter.com`).
2. **WHEN** `GET /s/{network}/{type}/{id}` is requested with an unknown `network` **THE SYSTEM SHALL** return 404 and write no `share_clicks` row.
3. **WHEN** a blog post detail page is rendered **THE SYSTEM SHALL** include the `<x-share-links>` component pointing at `/s/...` redirect URLs.
4. **WHEN** `tests/Feature/Phase2/ShareRedirectTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/ShareRedirectTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: /s/{network}/{type}/{id} logging share redirect"
git tag p2-step-11-share-redirect-route
```

### `E2-T4` — Make `OgImage::resolve` return an absolute URL and guarantee `og_image_id`

**Depends on:** `E1-T8` · **Priority:** p0

Change `OgImage::resolve()` to build an absolute `https://` URL (`URL::to(route('media.show', …, absolute: true))`
or `route(..., absolute: true)`), including for the `og-default.png` fallback (`url('images/og-default.png')`).
Add a guarded migration: for each of `articles` and `projects`, add a nullable `og_image_id` FK →
`media` `nullOnDelete` **only if the column does not already exist** (`Schema::hasColumn`). Confirm
the blog and project detail views already pass `:image="OgImage::resolve($x->og_image_id)"`.

**Files**
- `app/Support/Seo/OgImage.php` — edit: absolute URL
- `tests/Feature/Phase2/OgImageAbsoluteTest.php` — new
- *(the guarded migration — generated, not hand-named)*

**Acceptance**

1. **WHEN** `OgImage::resolve()` is called with a media id or with null **THE SYSTEM SHALL** return an absolute `https://` URL, never a scheme-relative or path-only string.
2. **WHEN** a blog post and a project are rendered **THE SYSTEM SHALL** each emit a `<meta property="og:image">` whose content is an absolute `https://` URL to an existing image.
3. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** ensure both `articles` and `projects` have a nullable `og_image_id` foreign key to `media` (added only if absent).
4. **WHEN** `tests/Feature/Phase2/OgImageAbsoluteTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/OgImageAbsoluteTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] fix: OgImage::resolve returns an absolute https URL"
git tag p2-step-12-ogimage-absolute
```

### `E2-T5` — Serve OG images to unauthenticated crawlers for published entities

**Depends on:** `E2-T4` · **Priority:** p0

In `MediaController@show`, allow an unauthenticated request when the target media is the OG image
(`og_image_id`) of a **published** `Article` or `Project`, in addition to the existing
"owning entity published" path. Keep the unpublished path a hard 404. Add the Cloudflare guidance to
`infra/provision-staging.md`: OG image URLs (`/media/{id}/{file}` for `og_image_id` media) must be
excluded from bot-fight-mode / "Under Attack" challenges — give the exact WAF/bot exception rule.

**Files**
- `app/Http/Controllers/Public/MediaController.php` — edit: guest access for published-entity OG media
- `infra/provision-staging.md` — edit: Cloudflare bot-fight-mode exception for OG image URLs
- `tests/Feature/Phase2/OgImageCrawlerAccessTest.php` — new

**Acceptance**

1. **WHEN** an unauthenticated `GET /media/{media}/{filename}` request targets media referenced as the OG image of a published article or project **THE SYSTEM SHALL** return 200 with an image content type.
2. **WHEN** the same request targets media of an unpublished entity **THE SYSTEM SHALL** return 404.
3. **WHEN** `infra/provision-staging.md` is read **THE SYSTEM SHALL** document the Cloudflare configuration that exempts OG image URLs from bot-fight-mode.
4. **WHEN** `tests/Feature/Phase2/OgImageCrawlerAccessTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
grep -qi 'bot' infra/provision-staging.md
./vendor/bin/pest tests/Feature/Phase2/OgImageCrawlerAccessTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[security-auditor] feat: serve OG media to crawlers for published entities"
git tag p2-step-13-ogimage-crawler-access
```

### `E2-T6` — Add the `social_profile_groups` schema and `social_profiles.group_id` FK

**Depends on:** `E1-T8` · **Priority:** p1

Migration for `social_profile_groups` (`name`, `heading`, nullable `intro_text` text, `sort_order`
integer default 0, `timestamps()`), and a second change adding nullable `group_id` on
`social_profiles` as an FK → `social_profile_groups` with `->nullOnDelete()`. `SocialProfileGroup`
model (`$fillable`, `socialProfiles()` hasMany). Add `group()` belongsTo to `SocialProfile`.
`SocialProfileGroupFactory`.

**Files**
- `app/Models/SocialProfileGroup.php` — new
- `app/Models/SocialProfile.php` — edit: `group()` belongsTo, `group_id` in `$fillable`
- `database/factories/SocialProfileGroupFactory.php` — new
- `tests/Feature/Phase2/SocialGroupSchemaTest.php` — new
- *(the two migration files — generated)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `social_profile_groups` with `name`, `heading`, nullable `intro_text`, `sort_order` and timestamps, and add a nullable `group_id` foreign key on `social_profiles` with `nullOnDelete`.
2. **WHEN** a `SocialProfileGroup` is deleted **THE SYSTEM SHALL** set `group_id` to null on its member `social_profiles`, never delete them.
3. **WHEN** `tests/Feature/Phase2/SocialGroupSchemaTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/SocialGroupSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: social_profile_groups schema + group_id FK"
git tag p2-step-14-social-groups-schema
```

### `E2-T7` — Add the Filament `SocialProfileGroupResource` and a grouped Select

**Depends on:** `E2-T6` · **Priority:** p1

`php artisan make:filament-resource SocialProfileGroup --generate`, then edit: a sortable table
(reorderable by `sort_order`), form fields for `name`/`heading`/`intro_text`. `SocialProfileGroupPolicy`
(super_admin only), registered in `AuthServiceProvider`. In
`app/Filament/Resources/SocialProfiles/Schemas/SocialProfileForm.php`, add a `group_id` `Select`
with `->relationship('group', 'name')` and `->createOptionForm([...])` for inline group creation.

**Files**
- `app/Filament/Resources/SocialProfileGroups/SocialProfileGroupResource.php` — new (generated then edited)
- `app/Filament/Resources/SocialProfileGroups/Schemas/SocialProfileGroupForm.php` — new
- `app/Filament/Resources/SocialProfiles/Schemas/SocialProfileForm.php` — edit: `group_id` Select + inline create
- `app/Policies/SocialProfileGroupPolicy.php` — new
- `tests/Feature/Phase2/SocialGroupResourceTest.php` — new

**Acceptance**

1. **WHEN** the authenticated super_admin opens the SocialProfileGroups index **THE SYSTEM SHALL** return 200 and allow reordering rows by `sort_order`.
2. **WHEN** editing a SocialProfile in Filament **THE SYSTEM SHALL** offer a `group_id` Select that can create a new group inline.
3. **WHEN** `tests/Feature/Phase2/SocialGroupResourceTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/SocialGroupResourceTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: SocialProfileGroupResource + grouped Select w/ inline create"
git tag p2-step-15-social-groups-admin
```

### `E2-T8` — Render `/connect` as one section per group and bust its cache on change

**Depends on:** `E2-T7` · **Priority:** p1

`ConnectController@index`: load `SocialProfileGroup::orderBy('sort_order')->with('socialProfiles')`
plus `SocialProfile::whereNull('group_id')->get()`. `connect.blade.php`: one `<section>` per group
with its `heading` (`<h2>`) and `intro_text` (`<p>`), grouped profiles nested; a trailing "Other"
section for the ungrouped set (omit it when empty). Footer (`<x-footer-social-profiles>`) unchanged —
still `show_in_footer` only. Add `SocialProfileGroupObserver` and `SocialProfileObserver`, both
calling a new `InvalidatePublicPageCache::forConnect()` (forget `public-page:{host}:{theme}:connect`
for every seeded theme/host); register both in `AppServiceProvider`.

**Files**
- `app/Http/Controllers/Public/ConnectController.php` — edit: grouped load
- `resources/views/public/connect.blade.php` — edit: grouped sections + "Other"
- `app/Observers/SocialProfileGroupObserver.php` — new
- `app/Observers/SocialProfileObserver.php` — new
- `tests/Feature/Phase2/ConnectGroupedTest.php` — new

**Acceptance**

1. **WHEN** `/connect` is requested with two groups and profiles across them **THE SYSTEM SHALL** render one section per group in `sort_order`, each showing the group's heading and intro paragraph, with grouped profiles nested correctly.
2. **WHEN** a profile has no `group_id` **THE SYSTEM SHALL** render it in a trailing "Other" section.
3. **WHEN** a `SocialProfileGroup` or `SocialProfile` is saved **THE SYSTEM SHALL** forget the `/connect` page cache entry for every seeded theme key.
4. **WHEN** the footer is rendered **THE SYSTEM SHALL** still show only `show_in_footer` profiles, unchanged by grouping.
5. **WHEN** `tests/Feature/Phase2/ConnectGroupedTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ConnectGroupedTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: grouped /connect sections + cache-busting observers"
git tag p2-step-16-connect-grouped-render
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a blog post is shared via one of its six links **THE SYSTEM SHALL** record exactly one `share_clicks` row and 302 to the correct network, and the professional blog's own rendering **SHALL** be otherwise unchanged.
2. **WHEN** a published blog post and a published project are fetched by an unauthenticated client **THE SYSTEM SHALL** each expose an `<meta property="og:image">` that is an absolute `https://` URL to an image that returns 200 to that same client.
3. **WHEN** `/connect` is rendered with grouped and ungrouped profiles **THE SYSTEM SHALL** show ordered group sections plus a trailing "Other", and any group/profile change **SHALL** bust the `/connect` cache.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
./vendor/bin/pest tests/Feature/Public/ProjectsContactTest.php   # Phase-1 contact-form behaviour unbroken
```

## Pitfalls

- **A relative or scheme-relative `og:image`.** Validators and crawlers reject it — the whole point
  of item 6. Always `route(..., absolute: true)` / `url()`.
- **Loosening the unpublished-media 404.** E2-T5 broadens guest access for *published*-entity OG
  media only. An unpublished entity's media stays 404 for everyone.
- **A tracking script in `<x-share-links>`.** Plain `href`s only; the `/s/...` redirect is the
  first-party logging path.
- **Busting the cache from the controller.** Observer → `InvalidatePublicPageCache::forConnect()`,
  never `Cache::forget` in `ConnectController`.
- **A logging table with `timestamps()`.** `share_clicks` has `created_at` only — set it explicitly,
  `public $timestamps = false` on the model.

## Before moving on

- [ ] Every task is `done` in `tasks.json` — none `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited; none skipped for a missing file.
- [ ] Every task has its `p2-step-*` checkpoint tag (`git tag -l 'p2-step-09-*' … 'p2-step-16-*'` → 8).
- [ ] Gate passes clean from the project root, with the `blueprints/phase-2/` bundle present.
- [ ] Every "Produced" contract exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged (this epic adds no variable).
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote.
