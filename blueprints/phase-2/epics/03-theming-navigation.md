# Epic 03: Theming & Navigation

> After this epic, special-event themes are admin-created data rows with JSON tokens injected at
> render (behind a now-ON `site.themes.dynamic` flag), the page-cache key carries a host segment,
> `www.` and apex resolve to the same theme and one canonical `<link>`, and a keyboard-navigable
> desktop menubar replaces the flat nav — all with the `technical` and `matrix` themes rendering
> byte-identically to before. Backlog items 2, 10, 12.

| | |
|---|---|
| **Epic id** | `03-theming-navigation` |
| **Tasks** | `E3-T1` … `E3-T9` |
| **Depends on** | `02-reach-sharing` |
| **Unlocks** | `04-content-portfolio` (Life blog needs `themes.shows_life_blog`; YouTube CSP needs the nav) |
| **Parallel with** | nothing |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` · Livewire 4 · Filament 5 · Blade + Tailwind v4 (`@theme` tokens) + Alpine ·
Vite · PostgreSQL · Pest · Pint · Larastan. **No Redis** — `database` cache/queue/session. Versions
in the lockfiles. Phase 2 adds **no** package in this epic.

| Task | Command |
|---|---|
| Format / analyse | `./vendor/bin/pint --test` · `./vendor/bin/phpstan analyse` |
| Build assets | `npm run build` (before `pest`) |
| Test (one file) | `./vendor/bin/pest tests/{Feature,Unit}/Phase2/<Name>Test.php` |
| Migrate / seed | `php artisan migrate` · `php artisan db:seed` |
| Host-parity | `bash infra/host-parity-check.sh` (blueprint §9.1) |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task is marked done.

**Release sub-flow (every task):** PR → CI `ci` green → merge → `bash infra/deploy-staging.sh` →
**sponsor review & approval on staging** → `bash infra/deploy.sh` → live verify. After every staging
deploy in this epic, also run `bash infra/host-parity-check.sh` — the www/apex 301 rule is applied by
the owner only once it reports 0 diffs for 48h (blueprint §9.1). Epic 03's tasks are reviewed as one
batch given the routing-migration interdependence (Risk #1).

## Directory subtree

```
database/migrations/                    # themes table
app/Models/Theme.php                    # NEW E3-T1 (class App\Models\Theme)
database/seeders/DatabaseSeeder.php     # EDIT E3-T1 — seed technical/matrix theme rows
database/factories/ThemeFactory.php     # NEW E3-T1
app/Support/Theming/ThemeResolver.php   # NEW E3-T2
app/Http/Middleware/ResolveTheme.php    # EDIT E3-T3 — delegate to ThemeResolver behind the flag
app/Http/Controllers/Public/ThemeController.php   # EDIT E3-T4 — validate against enabled+active themes
app/Actions/Cache/
  CachePublicPage.php                   # EDIT E3-T6 — host segment in keyFor()
  InvalidatePublicPageCache.php         # EDIT E3-T5, E3-T6 — forAllThemes(); host-aware forget
app/Filament/Resources/Themes/**        # NEW E3-T5 — {ThemeResource, Schemas/ThemeForm, Pages/*}
app/Observers/ThemeObserver.php         # NEW E3-T5
app/Policies/ThemePolicy.php            # NEW E3-T5
config/site.php                         # EDIT E3-T9 — site.themes.dynamic default -> true
config/session.php                      # EDIT E3-T7 — domain = "." + registrable domain of canonical_host
resources/views/layouts/app.blade.php   # EDIT E3-T4 (nonce'd token <style>), E3-T7 (<link canonical>), E3-T8 (<x-nav-menu>)
resources/views/components/
  theme-switcher.blade.php              # EDIT E3-T4 — list only currently-active enabled themes
  nav-menu.blade.php                    # NEW E3-T8
resources/css/app.css                   # EDIT E3-T8 — --menu-* tokens in BOTH theme blocks
infra/provision-staging.md              # EDIT E3-T7 — Cloudflare 301 Redirect Rule text
tests/{Feature,Unit}/Phase2/            # ThemeSchema, ThemeResolver(unit), ResolveThemeDelegation,
                                        # ThemeTokenInjection, ThemeResource, CacheKeyHostSegment,
                                        # CanonicalHost, NavMenuA11y, ThemesDynamicOn
```

Everything outside this subtree is out of scope.

## Data model touched here

| Entity | Fields this epic adds or reads | Notes |
|---|---|---|
| `themes` (new) | `key` (unique), `name`, `tokens` (json, default `{}`), `is_default` (bool), `enabled` (bool), nullable `active_from`/`active_until` (date), `shows_life_blog` (bool, default false), `sort_order`, timestamps | seeded rows: `technical` (`is_default=true`, `shows_life_blog=false`, `tokens={}`), `matrix` (`shows_life_blog=true`, `tokens={}`). Index `(key)` unique, `(enabled)`, `(active_from)`, `(active_until)`. `is_default` uniqueness enforced in the model `saving` hook. |

`articles`/`projects`/etc. are only read (`ThemeResolver` reads `themes`; nothing else changes shape).

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `App\Http\Middleware\ResolveTheme` (`COOKIE_NAME = 'miautrix_theme'`) | shares `$theme` (`'matrix'` only when the cookie equals `matrix`, else `'technical'`) with every web view |
| Phase 1 | `App\Actions\Cache\CachePublicPage::keyFor(Request): string` | `public-page:{theme}:{path}` (+ `?query`); a HIT short-circuits before the controller |
| Phase 1 | `App\Actions\Cache\InvalidatePublicPageCache` | `__invoke($routePath)` forgets `technical` + `matrix` entries; `forProject/forArticle/forHome/forSocialProfiles/forSkills` |
| Phase 1 | `App\Http\Controllers\Public\ThemeController@update` | `Rule::in(['technical','matrix'])`, sets the cookie, redirects back |
| Phase 1 | `resources/css/app.css` | `:root` + `@media (prefers-color-scheme: dark)` (Console) and `[data-theme="matrix"]` blocks |
| Epic 01 | `config('site.themes.dynamic')`, `config('site.canonical_host')` | flag default `false`; host default `miautrix.tech` |
| Epic 01 | `infra/host-parity-check.sh` | 0 diffs = www/apex parity |

**Produced** — later epics depend on these:

| Export | Signature | Used by |
|---|---|---|
| `App\Models\Theme` + `themes.shows_life_blog` | boolean per theme row | E4-T8 (`LifeController` + `<x-nav-menu>` gate `/life` on the active theme's `shows_life_blog`) |
| `App\Support\Theming\ThemeResolver::active(Request): Theme` | returns the resolved `Theme`, honouring cookie + enabled + date window, else the default | E4-T8, E5-T3 (`matrix` terminal command), anywhere the active theme is needed |
| `CachePublicPage::keyFor()` | `public-page:{host}:{theme}:{path}` | E4-T8 (`/life` theme-scoped), E5-T6 (`/tools`), E6-T5 (`/endorsements`), blueprint §9.1 parity row 3 |
| `InvalidatePublicPageCache::forAllThemes()` | forget a path for every seeded theme **and** host | E3-T5, E4-T8, E5-T6, E6-T5 observers |
| `<x-nav-menu>` | menubar component with per-theme `--menu-*` tokens | E4-T8 ("Life" entry), every public layout |
| `config('site.themes.dynamic')` = `true` (default, from E3-T9) | dynamic theme resolution live | E4-T8 relies on `ThemeResolver` being the resolution path |

## Conventions that bite in this area

- **`technical`/`matrix` must render byte-identically.** They are seeded with `tokens={}` and keep
  rendering from `app.css` — the flag-ON path never injects a `<style>` block for them. The flag-OFF
  branch of `ResolveTheme` is the *exact* pre-Phase-2 literal path; `ResolveThemeDelegationTest`
  asserts it.
- **One component tree, never two** (`.claude/rules/theming.md`). The nav menu's per-theme look is
  entirely `--menu-*` custom properties in both `app.css` blocks — no `nav-menu-matrix.blade.php`.
- **`#00FF41` never colors body text.** `--menu-highlight`/`--terminal-*` follow the same rule —
  accents/surfaces only.
- **The theme cookie is read server-side before the first byte.** A client-only path that flashes is
  a defect. `ThemeResolver` runs in `ResolveTheme` middleware, before the view.
- **The page-cache key gains a *leading* host segment** — `public-page:{host}:{theme}:{path}`. Every
  existing `InvalidatePublicPageCache` call keeps working; the change is additive, never a reshape of
  the `{theme}:{path}` tail. (Decision log #8.)
- **Injected token `<style>` uses the same `Vite::cspNonce()`** `SecurityHeaders` already puts in
  `style-src` — no CSP change needed for it.
- **`ThemeController` validation** moves from `Rule::in([...])` to "exists in `themes` AND is
  currently active (enabled + within window)". A disabled or future theme is not a valid switch
  target.
- **`ThemeObserver` busts ALL public page cache on any theme save** — themes change rarely enough
  that a broad flush is acceptable (unlike the targeted per-entity busts elsewhere).

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/theming.md`, `.claude/rules/database.md`,
`.claude/rules/filament.md`, `.claude/rules/security.md`.

---

## Tasks

Listed in `tasks.json` order. Work top to bottom.

### `E3-T1` — Add the `themes` schema, `Theme` model and seeded `technical`/`matrix` rows

**Depends on:** `E2-T8` · **Priority:** p0

Migration for `themes` per the data-model table. `App\Models\Theme`: `$fillable` explicit,
`tokens` cast to `array`, `is_default`/`enabled`/`shows_life_blog` to `boolean`,
`active_from`/`active_until` to `date`; a `saving` hook that, when the row is `is_default=true`,
sets `is_default=false` on every other row (so exactly one remains). `ThemeFactory`. Put the
`technical`/`matrix` rows in a dedicated `Database\Seeders\ThemeSeeder` (`updateOrCreate` keyed on
`key`) and call it from `DatabaseSeeder::run()`. **`ThemeSeeder` must be independently runnable via
`db:seed --class=ThemeSeeder` and must never touch the admin user** — so the theme rows can be
seeded on any node without `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD` (which `DatabaseSeeder`'s
`seedAdminUser()` requires and throws without).

**Files**
- `app/Models/Theme.php` — new
- `database/seeders/ThemeSeeder.php` — new: the two theme rows, no admin-user code
- `database/seeders/DatabaseSeeder.php` — edit: call `ThemeSeeder`
- `database/factories/ThemeFactory.php` — new
- `tests/Feature/Phase2/ThemeSchemaTest.php` — new
- *(the migration — generated)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create a `themes` table with a unique `key`, `name`, json `tokens`, `is_default`, `enabled`, nullable `active_from`, nullable `active_until`, `shows_life_blog` defaulting false, and `sort_order`.
2. **WHEN** `php artisan db:seed --class=ThemeSeeder` runs **THE SYSTEM SHALL** create exactly one `themes` row with `is_default=true` (`technical`) and a `matrix` row with `shows_life_blog=true`, both `enabled` and both with `tokens` equal to `{}` — and **SHALL** require no `ADMIN_SEED_*` env vars.
3. **WHEN** a second `Theme` is saved with `is_default=true` **THE SYSTEM SHALL** demote the previously-default row so exactly one remains default.
4. **WHEN** `tests/Feature/Phase2/ThemeSchemaTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
php artisan db:seed --class=ThemeSeeder
./vendor/bin/pest tests/Feature/Phase2/ThemeSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: themes schema + seeded technical/matrix rows"
git tag p2-step-17-themes-schema
```

### `E3-T2` — Add the `ThemeResolver` service

**Depends on:** `E3-T1` · **Priority:** p0

`App\Support\Theming\ThemeResolver` with `active(Request $request): Theme`. Read the
`miautrix_theme` cookie; if it names a row that is `enabled` and whose `[active_from, active_until]`
window (nulls = unbounded) contains `today()`, return it; otherwise return the `is_default` row. No
cookie → default. Take `today()` from `now()` so tests can `Carbon::setTestNow()`. Pure service — no
view, no response. Unit tests for each branch.

**Files**
- `app/Support/Theming/ThemeResolver.php` — new
- `tests/Unit/Phase2/ThemeResolverTest.php` — new

**Acceptance**

1. **WHEN** `ThemeResolver` is given a cookie value naming an enabled theme whose active window contains today **THE SYSTEM SHALL** return that theme.
2. **WHEN** the named theme is disabled, outside its active window, or unknown **THE SYSTEM SHALL** return the `is_default` theme.
3. **WHEN** no cookie is present **THE SYSTEM SHALL** return the `is_default` theme.
4. **WHEN** `tests/Unit/Phase2/ThemeResolverTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Unit/Phase2/ThemeResolverTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: ThemeResolver service (window + enabled + fallback)"
git tag p2-step-18-theme-resolver
```

### `E3-T3` — Delegate `ResolveTheme` to `ThemeResolver` behind the dynamic-themes flag

**Depends on:** `E3-T2` · **Priority:** p0

In `ResolveTheme@handle`: when `config('site.themes.dynamic')` is **false**, keep the exact current
line (`$theme = $request->cookie(self::COOKIE_NAME) === 'matrix' ? 'matrix' : 'technical'`) — do not
touch it. When **true**, `$theme = app(ThemeResolver::class)->active($request)->key`. `View::share('theme', $theme)`
either way. `ResolveThemeDelegationTest` asserts the OFF path is byte-identical output and the ON path
resolves via the resolver (e.g. a future-dated event theme in the cookie still yields `technical`).

**Files**
- `app/Http/Middleware/ResolveTheme.php` — edit: flag-gated delegation
- `tests/Feature/Phase2/ResolveThemeDelegationTest.php` — new

**Acceptance**

1. **WHEN** `config('site.themes.dynamic')` is false **THE SYSTEM SHALL** resolve the theme by the pre-Phase-2 literal path (`matrix` only when the cookie equals `matrix`, otherwise `technical`), byte-identical to before.
2. **WHEN** `config('site.themes.dynamic')` is true **THE SYSTEM SHALL** resolve the theme via `ThemeResolver` and share the resolved key as `$theme`.
3. **WHEN** `tests/Feature/Phase2/ResolveThemeDelegationTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/ResolveThemeDelegationTest.php
./vendor/bin/pest tests/Feature/Theme/ThemeSwitchTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: ResolveTheme delegates to ThemeResolver behind the flag"
git tag p2-step-19-resolve-theme-delegation
```

### `E3-T4` — Inject a nonce'd token `<style>` and tighten `ThemeController` validation

**Depends on:** `E3-T3` · **Priority:** p0

In `layouts/app.blade.php`: when the dynamic flag is on and the active theme's `key` is neither
`technical` nor `matrix`, emit `<style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">:root{ … }</style>`
built from the theme's `tokens` array (only keys starting with `--`). For `technical`/`matrix`, emit
nothing. `ThemeController@update`: replace `Rule::in([...])` with a rule that the key `exists` in
`themes`, is `enabled`, and is within its active window. `theme-switcher.blade.php`: list only
currently-active enabled themes (query `Theme` when the flag is on; the two literals when off).

**Files**
- `resources/views/layouts/app.blade.php` — edit: nonce'd token `<style>` for non-seed themes
- `app/Http/Controllers/Public/ThemeController.php` — edit: validate against enabled+active themes
- `resources/views/components/theme-switcher.blade.php` — edit: list only active enabled themes
- `tests/Feature/Phase2/ThemeTokenInjectionTest.php` — new

**Acceptance**

1. **WHEN** the active theme is neither `technical` nor `matrix` and the dynamic flag is on **THE SYSTEM SHALL** emit one `<style>` block carrying the per-request CSP nonce and the theme's `tokens` as `:root` custom properties.
2. **WHEN** the active theme is `technical` or `matrix` **THE SYSTEM SHALL** emit no such `<style>` block and render from `app.css` unchanged.
3. **WHEN** `POST /theme` receives a theme key that is not an enabled, currently-active theme **THE SYSTEM SHALL** reject it with a validation error.
4. **WHEN** the switcher partial renders **THE SYSTEM SHALL** list only currently-active enabled themes.
5. **WHEN** `tests/Feature/Phase2/ThemeTokenInjectionTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ThemeTokenInjectionTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: nonce'd theme token style block + strict ThemeController validation"
git tag p2-step-20-theme-token-injection
```

### `E3-T5` — Add the Filament `ThemeResource` and a cache-busting `ThemeObserver`

**Depends on:** `E3-T4` · **Priority:** p1

`php artisan make:filament-resource Theme --generate`, then edit: a `KeyValue` field for `tokens`
(hint the documented `--*` keys), `DatePicker`s for `active_from`/`active_until`, `Toggle`s for
`enabled`/`is_default`/`shows_life_blog`, `TextInput` for `key`/`name`/`sort_order`. `ThemePolicy`
(super_admin). Add `InvalidatePublicPageCache::forAllThemes(?string $path = null)` — forget the given
path (or every statically-known public path) for every seeded theme **and** every known host.
`ThemeObserver` calls it on `saved`/`deleted`; register in `AppServiceProvider`.

**Files**
- `app/Filament/Resources/Themes/ThemeResource.php` — new (generated then edited)
- `app/Filament/Resources/Themes/Schemas/ThemeForm.php` — new
- `app/Observers/ThemeObserver.php` — new
- `app/Actions/Cache/InvalidatePublicPageCache.php` — edit: `forAllThemes()`
- `tests/Feature/Phase2/ThemeResourceTest.php` — new

**Acceptance**

1. **WHEN** the super_admin creates a theme in Filament with a KeyValue `tokens` map, date pickers and a `shows_life_blog` toggle **THE SYSTEM SHALL** persist all of them.
2. **WHEN** any `Theme` row is saved or deleted **THE SYSTEM SHALL** forget every public-page cache entry (all themes, all known public paths).
3. **WHEN** `tests/Feature/Phase2/ThemeResourceTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/ThemeResourceTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: ThemeResource + ThemeObserver full cache bust"
git tag p2-step-21-theme-admin
```

### `E3-T6` — Add a host segment to the public page-cache key

**Depends on:** `E3-T5` · **Priority:** p0

In `CachePublicPage::keyFor()`, prepend the request host:
`'public-page:' . $request->getHost() . ':' . $theme . ':' . $request->path() . ($query ? '?'.$query : '')`.
Update every `InvalidatePublicPageCache` method to loop known hosts (`config('site.canonical_host')`,
its `www.` variant, and — when set — the staging host) as well as the seeded themes when it forgets a
key. `CacheKeyHostSegmentTest` asserts the shape and that `www`/apex never collide.

**Files**
- `app/Actions/Cache/CachePublicPage.php` — edit: host segment in `keyFor()`
- `app/Actions/Cache/InvalidatePublicPageCache.php` — edit: host-aware forget in every method
- `tests/Feature/Phase2/CacheKeyHostSegmentTest.php` — new

**Acceptance**

1. **WHEN** `CachePublicPage::keyFor()` is called **THE SYSTEM SHALL** return `public-page:{host}:{theme}:{path}` where `{host}` is the request host, preserving the existing `{theme}:{path}` tail for every current route.
2. **WHEN** the same path is requested with `Host: www.miautrix.tech` and with `Host: miautrix.tech` **THE SYSTEM SHALL** compute two different cache keys, so neither host can serve the other's cached HTML.
3. **WHEN** `InvalidatePublicPageCache` forgets an entry **THE SYSTEM SHALL** forget it for every known host as well as every seeded theme.
4. **WHEN** `tests/Feature/Phase2/CacheKeyHostSegmentTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/CacheKeyHostSegmentTest.php
./vendor/bin/pest tests/Feature/Cache/PageCacheTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: host segment in the public page-cache key"
git tag p2-step-22-cache-key-host-segment
```

### `E3-T7` — Canonicalise the host: cookie domain, canonical link, Cloudflare rule text

**Depends on:** `E3-T6` · **Priority:** p0

`config/session.php`: derive `'domain'` from `config('site.canonical_host')` — `'.' . <registrable domain>`
(e.g. `.miautrix.tech`) so the session/theme cookie is shared across `www.` and apex. Set the theme
cookie explicitly with `cookie()->forever(...)->withDomain('.'.$registrable)` in `ThemeController`.
`layouts/app.blade.php`: emit `<link rel="canonical" href="{{ 'https://'.config('site.canonical_host').request()->getRequestUri() }}">`
so both hosts point at the canonical host. Add the exact Cloudflare Redirect Rule text (301 the
non-canonical host to `canonical_host`, preserving path + query) to `infra/provision-staging.md`.

**Files**
- `config/session.php` — edit: cookie `domain`
- `resources/views/layouts/app.blade.php` — edit: host-correct `<link rel="canonical">`
- `infra/provision-staging.md` — edit: Cloudflare 301 Redirect Rule text
- `tests/Feature/Phase2/CanonicalHostTest.php` — new

**Acceptance**

1. **WHEN** the app is requested with `Host: www.miautrix.tech` and with `Host: miautrix.tech` and the same `miautrix_theme` cookie **THE SYSTEM SHALL** resolve to the identical theme on both.
2. **WHEN** any public page renders on either host **THE SYSTEM SHALL** emit a `<link rel="canonical">` whose host equals `config('site.canonical_host')`.
3. **WHEN** the theme cookie is set **THE SYSTEM SHALL** scope it to a `.`-prefixed registrable domain of `config('site.canonical_host')` so it is shared across both hosts.
4. **WHEN** `infra/provision-staging.md` is read **THE SYSTEM SHALL** contain the exact Cloudflare 301 Redirect Rule text for the non-canonical host.
5. **WHEN** `tests/Feature/Phase2/CanonicalHostTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
grep -qi 'redirect rule' infra/provision-staging.md
./vendor/bin/pest tests/Feature/Phase2/CanonicalHostTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: shared cookie domain + canonical link + Cloudflare 301 rule text"
git tag p2-step-23-canonical-host
```

### `E3-T8` — Add the `<x-nav-menu>` desktop menubar with per-theme tokens

**Depends on:** `E3-T7` · **Priority:** p1

Build `resources/views/components/nav-menu.blade.php`: a menubar grouping every Phase-1 destination
(Work → Experience/Skills/Projects/Resume; Writing → Blog [Life added by E4-T8]; About; Connect;
Contact). `role="menubar"` / `menu` / `menuitem`; a small nonce'd Alpine component for open/close,
arrow-key movement, `Esc` to close + return focus, focus trap while open. Add `--menu-bg`,
`--menu-border`, `--menu-shadow`, `--menu-highlight` to **both** the `:root`/`@media` (Console) block
and the `[data-theme="matrix"]` block of `app.css` (blueprint §7 values), and list those keys in the
documented `themes.tokens` set (a comment block in `ThemeForm` or a shared constant). Replace the flat
nav in `layouts/app.blade.php` with `<x-nav-menu>`.

**Files**
- `resources/views/components/nav-menu.blade.php` — new
- `resources/views/layouts/app.blade.php` — edit: use `<x-nav-menu>`
- `resources/css/app.css` — edit: `--menu-*` tokens in both theme blocks
- `tests/Feature/Phase2/NavMenuA11yTest.php` — new

**Acceptance**

1. **WHEN** the layout renders **THE SYSTEM SHALL** use `<x-nav-menu>` carrying `role="menubar"`, `role="menu"` and `role="menuitem"` and expose every Phase-1 public destination (Work, Writing, About, Connect, Contact groups) as a reachable menu item.
2. **WHEN** a submenu is open and `Escape` is pressed **THE SYSTEM SHALL** close it and return focus to its trigger, with focus trapped inside the submenu while open.
3. **WHEN** the menu is styled **THE SYSTEM SHALL** use only `--menu-*` custom properties defined in both the `:root`/Console block and the `[data-theme="matrix"]` block of `app.css`, with those keys also listed in the documented `themes.tokens` set.
4. **WHEN** `tests/Feature/Phase2/NavMenuA11yTest.php` and the Phase-1 `tests/Feature/A11yTest.php` run **THE SYSTEM SHALL** each exit 0 with zero violations.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/NavMenuA11yTest.php
./vendor/bin/pest tests/Feature/A11yTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: x-nav-menu desktop menubar, per-theme tokens, ARIA"
git tag p2-step-24-nav-menu
```

### `E3-T9` — Turn the dynamic-themes flag on

**Depends on:** `E3-T8` · **Priority:** p0

Change `config/site.php` so `site.themes.dynamic` defaults to `true` (`env('SITE_THEMES_DYNAMIC', true)`).
The www/apex canonicalisation (E3-T7 / §9.1) is entirely a cookie-domain change, a host segment in
the cache key, and a Cloudflare 301 rule — no in-app host-alias shim is ever added, so nothing in-app
is removed here; this task is the flag flip alone. `ThemesDynamicOnTest` asserts a future-dated event
theme is not offered/resolved, an active-now one is, and the two seeded themes still resolve to their
pre-Phase-2 output.

**Files**
- `config/site.php` — edit: `site.themes.dynamic` default → `true`
- `tests/Feature/Phase2/ThemesDynamicOnTest.php` — new

**Acceptance**

1. **WHEN** `config('site.themes.dynamic')` reads its default **THE SYSTEM SHALL** return true, and the seeded `technical` and `matrix` themes **SHALL** still resolve to their pre-Phase-2 rendered output.
2. **WHEN** a theme has `active_from` in the future **THE SYSTEM SHALL** NOT offer it in the switcher and **SHALL** NOT resolve to it; a theme active now with `enabled=true` **SHALL** be offered and resolvable.
3. **WHEN** a theme row is saved through the admin **THE SYSTEM SHALL** bust every public page's cache entry for both theme variants.
4. **WHEN** `tests/Feature/Phase2/ThemesDynamicOnTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/ThemesDynamicOnTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: site.themes.dynamic on by default"
git tag p2-step-25-themes-dynamic-on
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a visitor who never opts into a new theme browses every Phase-1 route in both `technical` and `matrix` **THE SYSTEM SHALL** render byte-identically to before Phase 2 (no injected `<style>`, no flash, no changed markup beyond the new nav).
2. **WHEN** the same path is fetched on `www.miautrix.tech` and `miautrix.tech` with the same cookie **THE SYSTEM SHALL** resolve the same theme, emit a `<link rel="canonical">` on the canonical host, and never serve one host's cached HTML for the other.
3. **WHEN** an admin creates an event theme with an active window in the future **THE SYSTEM SHALL** not offer it until the window opens, then offer and resolve it; saving any theme busts every public cache entry.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
./vendor/bin/pest tests/Feature/A11yTest.php tests/Feature/Theme/ThemeSwitchTest.php tests/Feature/Cache/PageCacheTest.php
bash infra/host-parity-check.sh   # expect: 0 diffs — precondition for the Cloudflare 301
```

## Pitfalls

- **A changed render for `technical`/`matrix`.** The whole epic must be invisible to a default
  visitor. Seed them with `tokens={}`, keep the `app.css` blocks, keep the flag-OFF branch literal.
- **Reshaping the cache key tail.** Only *prepend* a host segment. `{theme}:{path}` stays exactly as
  it is or every existing invalidation call silently misses.
- **A client-only theme path.** `ThemeResolver` runs in middleware, before the view — no
  flash-then-correct.
- **Injecting a token `<style>` without the nonce.** It will be blocked by `style-src`. Use
  `Vite::cspNonce()`.
- **A second component tree for the Matrix menu.** One `nav-menu.blade.php`; `--menu-*` tokens carry
  the difference.
- **Applying the Cloudflare 301 before parity holds.** `infra/host-parity-check.sh` must report 0
  diffs for 48h first (blueprint §9.1 abort criteria).

## Before moving on

- [ ] Every task is `done` in `tasks.json` — none `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited; none skipped for a missing file.
- [ ] Every task has its `p2-step-*` checkpoint tag (`git tag -l 'p2-step-1[7-9]-*' 'p2-step-2[0-5]-*'` → 9).
- [ ] Gate passes clean from the project root with the bundle present.
- [ ] `bash infra/host-parity-check.sh` reports 0 diffs on staging.
- [ ] Every "Produced" contract exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged (the `SITE_THEMES_DYNAMIC` key was added in Epic 01; only its default flips here).
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote.
