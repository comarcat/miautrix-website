# Epic 04: Public Site, Themes & Blog

> After this epic, a visitor can browse the entire public portfolio and blog, switch between the
> Technical and Matrix themes with no flash, and every page is built from the one shared component
> library — no page reaches into raw hex values or a theme-specific component tree.

| | |
|---|---|
| **Epic id** | `04-public-themes` |
| **Tasks** | `E4-T1` … `E4-T6` |
| **Depends on** | `03-admin-filament` |
| **Unlocks** | `05-seo-hardening` |
| **Parallel with** | nothing — SEO/a11y in epic 05 sweeps every route this epic creates |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Tailwind CSS `^4.3.3` (`@theme` tokens) · Alpine.js `^3.17` · Livewire `^4.4` (contact form) ·
self-hosted IBM Plex Sans + JetBrains Mono woff2 · Pest `^5.1`.

| Task | Command |
|---|---|
| Build assets | `npm run build` |
| Test (one file) | `./vendor/bin/pest {path}` |
| Dev server | `php artisan serve` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task here is marked done.

## Directory subtree

```
resources/
  css/app.css                          # @theme tokens both themes — E4-T1, E4-T2
  views/
    layouts/app.blade.php               # base layout — E4-T1
    components/
      meta.blade.php                     # E4-T1
      theme-switcher.blade.php            # E4-T2
      button.blade.php card.blade.php badge.blade.php timeline.blade.php
      table.blade.php tabs.blade.php modal.blade.php alert.blade.php toast.blade.php
      file-upload.blade.php image-gallery.blade.php breadcrumb.blade.php   # E4-T3, 12 total
    public/
      home.blade.php about.blade.php experience.blade.php skills.blade.php   # E4-T4
      projects/index.blade.php projects/show.blade.php
      software/index.blade.php software/show.blade.php
      certifications.blade.php resume.blade.php contact.blade.php            # E4-T5
      blog/index.blade.php blog/show.blade.php                               # E4-T6
    feed.xml.blade.php                    # E4-T6
app/
  Http/Middleware/ResolveTheme.php      # NEW E4-T2
  View/Components/Modal.php             # NEW E4-T3 (+ others where logic is needed)
  Http/Controllers/Public/*.php         # NEW E4-T4, E4-T5, E4-T6
  Livewire/ContactForm.php              # NEW E4-T5
public/fonts/*.woff2                    # NEW E4-T1
```

Everything outside this subtree is out of scope.

## Data model touched here

NOT APPLICABLE — no schema changes. This epic only reads entities created in epics 02-03.

## Contracts

**Consumed:**

| From | Interface | Guarantee |
|---|---|---|
| `02-schema-auth` | Every Eloquent model, `published`/`featured` scopes | Public queries only ever read `published = true` |
| `03-admin-filament` | `MediaController`, `Article` model | Images and blog content are servable and queryable |

**Produced:**

| Export | Signature | Used by |
|---|---|---|
| Every public route in blueprint §6 | server-rendered Blade, 200/404 per the route table | `05-seo-hardening`'s SEO/a11y/Lighthouse sweep |
| The 12-component shared library | `resources/views/components/*.blade.php`, token-only styling | Every public page in this epic, and any future page |
| `/feed.xml` | hand-rolled RSS 2.0, `application/rss+xml` | External RSS readers, `05-seo-hardening`'s SEO checks |

## Conventions that bite in this area

- **One component tree, never two.** Both themes render the same Blade files; only CSS custom
  property values differ via `[data-theme]`.
- **Matrix accent color (`#00FF41`) never appears as full-paragraph body text** — body copy always
  uses `--color-foreground`.
- **The theme cookie must be read server-side before the first byte renders** — no client-side theme
  flash is acceptable.
- **`resources/js/app.js` module resolution is separate from PHP's PSR-4** — see blueprint §19.6's
  resolution convention matrix if anything here seems to not resolve the way a PHP import would.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/theming.md`.

---

## Tasks

### `E4-T1` — Design tokens both themes, Tailwind v4 @theme, self-hosted fonts, base layout, meta component

**Depends on:** nothing in this epic (epic-level: `E3-T6`) · **Priority:** p0

Write the full `@theme` token block for Theme A (light/dark via `prefers-color-scheme`) with a
`[data-theme="matrix"]` override for Theme B, per blueprint §7's exact hex values. Self-host IBM Plex
Sans and JetBrains Mono woff2 files. Build the base layout and `<x-meta>` component.

**Files**
- `resources/css/app.css` — edit: full `@theme` token block
- `resources/views/layouts/app.blade.php` — new
- `resources/views/components/meta.blade.php` — new
- `public/fonts/ibm-plex-sans-400.woff2` — new (plus the other 8 weight files across both families)
- `tests/Feature/Layout/MetaComponentTest.php` — new

**Acceptance**

1. **WHEN** `npm run build` runs **THE SYSTEM SHALL** exit 0 and the compiled CSS **SHALL** contain the `@theme` tokens.
2. **WHEN** the base layout renders **THE SYSTEM SHALL** include a font preload for the 400 and 600 weight IBM Plex Sans files.
3. **WHEN** a page uses `<x-meta title="..." description="...">` **THE SYSTEM SHALL** render a matching `<title>`, `meta[name=description]`, and OG tags.
4. **WHEN** `tests/Feature/Layout/MetaComponentTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
npm run build
test -f public/build/manifest.json
grep -q "IBM Plex Sans" resources/css/app.css
./vendor/bin/pest tests/Feature/Layout/MetaComponentTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T1: design tokens, self-hosted fonts, base layout, meta component"
git tag step-19-design-tokens
```

### `E4-T2` — Theme B (Matrix) + cookie-persisted SSR-correct theme switcher

**Depends on:** `E4-T1` · **Priority:** p0

Complete the Matrix token block. Add `ResolveTheme` middleware reading the `miautrix_theme` cookie
and a View Composer exposing `$theme`. Build the theme switcher: sets the cookie, reloads, zero
flash, visually communicates the system-preference override.

**Files**
- `app/Http/Middleware/ResolveTheme.php` — new
- `resources/views/components/theme-switcher.blade.php` — new
- `resources/css/app.css` — edit: Matrix token block
- `tests/Feature/Theme/ThemeSwitchTest.php` — new

**Acceptance**

1. **WHEN** the `miautrix_theme` cookie is absent **THE SYSTEM SHALL** render Theme A and respect `prefers-color-scheme` client-side.
2. **WHEN** the cookie is `matrix` **THE SYSTEM SHALL** render `data-theme="matrix"` on the very first response, before any client JS runs.
3. **WHEN** the switcher is used to select Matrix **THE SYSTEM SHALL** set the cookie and the next page load **SHALL** be Matrix-themed with no flash.
4. **WHEN** the switcher renders in Matrix mode **THE SYSTEM SHALL** display an "overrides system theme" label.
5. **WHEN** `tests/Feature/Theme/ThemeSwitchTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Theme/ThemeSwitchTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T2: matrix theme and ssr-correct cookie-persisted switcher"
git tag step-20-matrix-theme-switcher
```

### `E4-T3` — Shared component library rendered correctly in both themes

**Depends on:** `E4-T2` · **Priority:** p0

Build the 12 shared Blade components. Every component uses only `--color-*` custom properties — no
component-local hex values.

**Files**
- `resources/views/components/button.blade.php` — new
- `resources/views/components/card.blade.php` — new
- `resources/views/components/modal.blade.php` — new
- `app/View/Components/Modal.php` — new
- `tests/Feature/Components/ComponentLibraryTest.php` — new

**Acceptance**

1. **WHEN** each of the 12 components is rendered with `data-theme="technical"` **THE SYSTEM SHALL** produce output containing no raw hex color in its style attribute.
2. **WHEN** each is rendered with `data-theme="matrix"` **THE SYSTEM SHALL** produce equivalent structural HTML, differing only in resolved token values.
3. **WHEN** the `Modal` component opens **THE SYSTEM SHALL** trap focus within it and return focus to the trigger on close.
4. **WHEN** `tests/Feature/Components/ComponentLibraryTest.php` runs **THE SYSTEM SHALL** report 12 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Components/ComponentLibraryTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T3: shared component library, both themes"
git tag step-21-component-library
```

### `E4-T4` — Public pages: Home, About, Experience, Skills

**Depends on:** `E4-T3` · **Priority:** p0

Build the four core public pages and their thin controllers. Each queries only `published = true`
rows; empty sections render a designed empty state, never a blank page.

**Files**
- `resources/views/public/home.blade.php` — new
- `resources/views/public/about.blade.php` — new
- `resources/views/public/experience.blade.php` — new
- `resources/views/public/skills.blade.php` — new
- `tests/Feature/Public/CorePagesTest.php` — new

**Acceptance**

1. **WHEN** `/` is requested **THE SYSTEM SHALL** return 200 and render featured projects and the latest 3 published articles.
2. **WHEN** `/about` is requested **THE SYSTEM SHALL** return 200 and render the profile bio and published experience/education summaries.
3. **WHEN** `/experience` is requested **THE SYSTEM SHALL** return 200 and render only published experiences ordered by `started_at` desc.
4. **WHEN** `/skills` is requested **THE SYSTEM SHALL** return 200 and group skills by skill category.
5. **WHEN** `tests/Feature/Public/CorePagesTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Public/CorePagesTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T4: public pages — home, about, experience, skills"
git tag step-22-public-core-pages
```

### `E4-T5` — Public pages: Projects, Software, Certifications, Resume downloads, Contact

**Depends on:** `E4-T4` · **Priority:** p0

Build the projects/software/certifications/resume/contact pages. Contact form is a Livewire
component with a honeypot and a 5/hour/IP rate limit.

**Files**
- `resources/views/public/projects/index.blade.php` — new
- `resources/views/public/projects/show.blade.php` — new
- `app/Livewire/ContactForm.php` — new
- `app/Http/Controllers/Public/ProjectController.php` — new
- `tests/Feature/Public/ProjectsContactTest.php` — new

**Acceptance**

1. **WHEN** `/projects` is requested **THE SYSTEM SHALL** return 200, paginated, filterable by category via query param.
2. **WHEN** `/projects/{slug}` is requested for a published project **THE SYSTEM SHALL** return 200 with its technologies, media, and documents.
3. **WHEN** `/projects/{slug}` is requested for an unpublished project **THE SYSTEM SHALL** return 404.
4. **WHEN** the contact form's honeypot field is filled **THE SYSTEM SHALL** silently discard the submission with no mail sent.
5. **WHEN** the contact form receives a 6th submission from the same IP within an hour **THE SYSTEM SHALL** reject with a rate-limit message.
6. **WHEN** `tests/Feature/Public/ProjectsContactTest.php` runs **THE SYSTEM SHALL** report 6 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Public/ProjectsContactTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T5: public projects, software, certifications, resume, contact"
git tag step-23-public-projects-contact
```

### `E4-T6` — Public blog: index + detail + /feed.xml hand-rolled RSS

**Depends on:** `E4-T5` · **Priority:** p0

Build the blog index and detail pages. Hand-roll `feed.xml.blade.php` returning
`application/rss+xml` RSS 2.0 XML — no new package.

**Files**
- `resources/views/public/blog/index.blade.php` — new
- `resources/views/public/blog/show.blade.php` — new
- `resources/views/feed.xml.blade.php` — new
- `routes/web.php` — edit: `/blog`, `/blog/{slug}`, `/feed.xml`
- `tests/Feature/Public/BlogFeedTest.php` — new

**Acceptance**

1. **WHEN** `/blog` is requested **THE SYSTEM SHALL** return 200, paginated, showing only published articles.
2. **WHEN** `/blog/{slug}` is requested for a published article **THE SYSTEM SHALL** return 200 with the rendered RichEditor body.
3. **WHEN** `/blog/{slug}` is requested for an unpublished article **THE SYSTEM SHALL** return 404.
4. **WHEN** `/feed.xml` is requested **THE SYSTEM SHALL** return `Content-Type: application/rss+xml` and valid RSS 2.0 XML with one item per published article.
5. **WHEN** `tests/Feature/Public/BlogFeedTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pest tests/Feature/Public/BlogFeedTest.php
php artisan tinker --execute="exit(simplexml_load_string(view('feed', ['articles' => App\Models\Article::published()->get()])->render()) === false ? 1 : 0);"
```

**Checkpoint**

```bash
git add -A && git commit -m "E4-T6: public blog index, detail, hand-rolled rss feed"
git tag step-24-public-blog-feed
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a visitor browses every route in blueprint §6 in both themes **THE SYSTEM SHALL** render with no raw hex color, no flash on theme switch, and no horizontal scroll at 375/768/1024/1440px.
2. **WHEN** the seeded database's Articles are published **THE SYSTEM SHALL** appear on `/blog` and in `/feed.xml` with no code change.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
```

## Pitfalls

- **A second component tree for Matrix.** There is one `resources/views/components/` directory,
  period.
- **Full-paragraph Matrix body text in `#00FF41`.** Body copy is always `--color-foreground`.
- **A theme flash on first paint.** The cookie must be read server-side before the layout renders;
  a client-only theme toggle is a defect here, not a nice-to-have.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-19-design-tokens`
      through `step-24-public-blog-feed`.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged unless this epic added a variable.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
