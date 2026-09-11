# Epic 06: Visitor Testimonials

> After this epic, a visitor can submit an endorsement on `/endorsements` (professional area only,
> never under `/life`); it lands as `pending` and is invisible until the owner approves it in
> Filament, after which it renders with the signer's name, org, role and a linked contact, and the
> page cache is busted. Backlog item 15.

| | |
|---|---|
| **Epic id** | `06-testimonials` |
| **Tasks** | `E6-T1` … `E6-T5` |
| **Depends on** | `05-platform-analytics` |
| **Unlocks** | nothing — this is the last epic |
| **Parallel with** | nothing |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 · PHP `^8.4` · Livewire 4 · Filament 5 · Blade + Tailwind v4 + Alpine · Vite · PostgreSQL ·
Pest · Pint · Larastan. **No Redis.** Package manager: Composer + npm. Versions in the lockfiles.
Phase 2 adds **no** package in this epic.

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
live verify. The sponsor gate is human. Epic 06 is one review batch.

## Directory subtree

```
database/migrations/                            # testimonials
app/Models/Testimonial.php                      # NEW E6-T1
app/Policies/TestimonialPolicy.php              # NEW E6-T1 (edited E6-T4 for approve/reject + deny-create)
database/factories/TestimonialFactory.php       # NEW E6-T1
app/Livewire/TestimonialForm.php                # NEW E6-T2
app/Mail/TestimonialSubmitted.php              # NEW E6-T2 (optional admin notify)
app/Http/Controllers/Public/EndorsementController.php   # NEW E6-T3
app/Filament/Resources/Testimonials/
  TestimonialResource.php                       # NEW E6-T4 (generated then edited)
  Tables/TestimonialsTable.php                  # NEW E6-T4 — status filter + Approve/Reject row actions
app/Observers/TestimonialObserver.php           # NEW E6-T5
app/Providers/AppServiceProvider.php            # EDIT E6-T5 — register TestimonialObserver
app/Actions/Cache/InvalidatePublicPageCache.php # EDIT E6-T5 — forEndorsements()
resources/views/
  livewire/testimonial-form.blade.php           # NEW E6-T2
  public/endorsements.blade.php                 # NEW E6-T3
routes/web.php                                  # EDIT E6-T3 — /endorsements (cache.public), professional area only
tests/Feature/Phase2/                           # TestimonialSchema, TestimonialForm, EndorsementsPage,
                                                # TestimonialResource, TestimonialCacheBust
```

Everything outside this subtree is out of scope.

## Data model touched here

| Entity | Fields | Notes |
|---|---|---|
| `testimonials` (new) | `name`, nullable `organization`/`role`, `contact_type` (`email`/`linkedin`/`url` — enum-in-code), `contact_value`, `body` (text), `status` (`pending`/`approved`/`rejected`, default `pending`), nullable `approved_at`, nullable `company_id` FK → `companies` `nullOnDelete`, timestamps | index `(status)`, `(company_id)`, `(created_at)`. `status` and `contact_type` are string columns validated in code (matches the repo's enum-in-code pattern). The public form never sets `status`. |

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| Phase 1 | `App\Livewire\ContactForm` | honeypot field + `RateLimiter::for` pattern (5/hour/IP) — copied, not imported |
| Phase 1 | `App\Models\Company` | the optional `company_id` link target |
| Phase 1 | `App\Actions\Cache\InvalidatePublicPageCache` | Observer → forget the relevant keys (now host+theme-aware, Epic 03) |
| Phase 1 | `App\Providers\AuthServiceProvider` | Policy registration |
| Epic 03 | `InvalidatePublicPageCache::forAllThemes()` | host+theme-aware forget for `TestimonialObserver` |
| Epic 05 | `App\Support\Mail\ConfiguresMailFromSettings` | the admin SMTP override — `TestimonialSubmitted` mail uses whatever it configured |

**Produced** — nothing downstream (this is the last epic). The public contract added:

| Export | Signature | Notes |
|---|---|---|
| `GET /endorsements` (`endorsements.index`) | server Blade, approved testimonials + `<livewire:testimonial-form>` | professional area only; **no `/life/endorsements`** |
| `<livewire:testimonial-form>` | creates a `pending` `Testimonial`; honeypot + 5/hour/IP | never rendered under `/life` |

## Conventions that bite in this area

- **The public form never sets `status`.** It is `pending` on insert, always. Approval is a Filament
  action only.
- **`/endorsements` is professional-area only.** No route under `/life`, and the form/section must be
  absent from any Life-scoped view. `EndorsementsPageTest` asserts `/life/endorsements` does not
  resolve.
- **Reuse `ContactForm`'s abuse pattern, don't import it.** Copy the honeypot field name and the
  `RateLimiter::for` shape (`.claude/rules/security.md` — the contact form's rate limit and honeypot
  are a frozen Phase-1 interface; the testimonial form mirrors, does not modify, them).
- **Cache correctness is Observer → `InvalidatePublicPageCache::forEndorsements()`** — never
  `Cache::forget` in the resource or the Approve action.
- **`contact_value` validation is per `contact_type`** — `email` rule for `email`, `url` rule for
  `linkedin`/`url`. Server-side, in the Livewire component.
- **Filament: generate then edit**, `Tables/` split, Policy for authz. The Approve/Reject row actions
  live in the table; the Policy grants a bespoke `approve` ability and denies `create` from the
  panel.
- **Approved testimonials render the linked contact** — `mailto:` for `email`, the URL for
  `linkedin`/`url`, each with an accessible name.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/database.md`, `.claude/rules/filament.md`,
`.claude/rules/security.md`.

---

## Tasks

Listed in `tasks.json` order. Work top to bottom.

### `E6-T1` — Add the `testimonials` schema, model, policy and factory

**Depends on:** `E5-T9` · **Priority:** p1

Migration per the data-model table. `App\Models\Testimonial`: `$fillable` explicit,
`approved_at` datetime cast, `status`/`contact_type` left as strings (enum-in-code — validate on
write), `company()` belongsTo, an `approved` scope. `TestimonialPolicy` (super_admin for
view/update/delete; `create` handled in E6-T4). `TestimonialFactory` with `pending`/`approved`/
`rejected` states.

**Files**
- `app/Models/Testimonial.php` — new
- `app/Policies/TestimonialPolicy.php` — new
- `database/factories/TestimonialFactory.php` — new
- `tests/Feature/Phase2/TestimonialSchemaTest.php` — new
- *(the migration — generated)*

**Acceptance**

1. **WHEN** `php artisan migrate` runs **THE SYSTEM SHALL** create `testimonials` with `name`, nullable `organization`/`role`, `contact_type`, `contact_value`, `body`, `status` defaulting to `pending`, nullable `approved_at`, and a nullable `company_id` foreign key to `companies` with `nullOnDelete`.
2. **WHEN** a `Testimonial` is inserted without a status **THE SYSTEM SHALL** store `pending`.
3. **WHEN** a referenced `Company` is deleted **THE SYSTEM SHALL** set `company_id` to null and keep the testimonial.
4. **WHEN** `tests/Feature/Phase2/TestimonialSchemaTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan migrate
./vendor/bin/pest tests/Feature/Phase2/TestimonialSchemaTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[db-architect] feat: testimonials schema, model, policy, factory"
git tag p2-step-44-testimonials-schema
```

### `E6-T2` — Add the public testimonial submission Livewire form

**Depends on:** `E6-T1` · **Priority:** p1

`App\Livewire\TestimonialForm`: fields `name`, `organization`, `role`, `contact_type`,
`contact_value`, `body`, plus a honeypot (reuse `ContactForm`'s hidden-field name and CSS-hidden
technique). Server-side validation, `contact_value` rule chosen by `contact_type`. A
`RateLimiter::for`-style 5/hour/IP guard (mirror `ContactForm`). On a valid submit: create one
`Testimonial` with `status='pending'`, optionally dispatch `App\Mail\TestimonialSubmitted` to the
admin, show a "thanks — pending review" message. A filled honeypot → silently discard (no row).

**Files**
- `app/Livewire/TestimonialForm.php` — new
- `resources/views/livewire/testimonial-form.blade.php` — new
- `app/Mail/TestimonialSubmitted.php` — new (optional admin notify)
- `tests/Feature/Phase2/TestimonialFormTest.php` — new

**Acceptance**

1. **WHEN** a valid endorsement is submitted **THE SYSTEM SHALL** create exactly one `testimonials` row with `status='pending'` and show a "thanks, pending review" message.
2. **WHEN** the honeypot field is filled **THE SYSTEM SHALL** discard the submission with no row written.
3. **WHEN** a sixth submission from the same IP within an hour is made **THE SYSTEM SHALL** reject it with a rate-limit message.
4. **WHEN** `contact_type` is `email` **THE SYSTEM SHALL** validate `contact_value` as an email, and as a URL for `linkedin`/`url`.
5. **WHEN** `tests/Feature/Phase2/TestimonialFormTest.php` runs **THE SYSTEM SHALL** report 5 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/TestimonialFormTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: public testimonial submission form (honeypot + rate limit)"
git tag p2-step-45-testimonial-form
```

### `E6-T3` — Add the `/endorsements` page showing approved testimonials only

**Depends on:** `E6-T2` · **Priority:** p1

`EndorsementController@index`: `Testimonial::where('status', 'approved')->latest('approved_at')->get()`
+ mount `<livewire:testimonial-form>`. `endorsements.blade.php`: one card per approved testimonial —
name, organization, role, and the linked contact (`mailto:` / URL) with an accessible name; the
company link when `company_id` is set. A privacy note stating approval is manual and the retention.
Route `/endorsements` (`endorsements.index`) inside `cache.public`, in the professional area — **no**
`/life/endorsements` route, and the component/section must not appear in any Life view.

**Files**
- `app/Http/Controllers/Public/EndorsementController.php` — new
- `routes/web.php` — edit: `/endorsements` in `cache.public`, professional area only
- `resources/views/public/endorsements.blade.php` — new
- `tests/Feature/Phase2/EndorsementsPageTest.php` — new

**Acceptance**

1. **WHEN** `/endorsements` is requested **THE SYSTEM SHALL** render only `approved` testimonials, each with name, organization, role and a linked contact, plus the submission form.
2. **WHEN** a testimonial is `pending` or `rejected` **THE SYSTEM SHALL** never render it publicly.
3. **WHEN** the route table is inspected **THE SYSTEM SHALL** expose `/endorsements` in the professional area only, with no `/life/endorsements` route.
4. **WHEN** `tests/Feature/Phase2/EndorsementsPageTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest tests/Feature/Phase2/EndorsementsPageTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: /endorsements page, approved-only, professional area"
git tag p2-step-46-endorsements-page
```

### `E6-T4` — Add the Filament `TestimonialResource` with Approve and Reject actions

**Depends on:** `E6-T3` · **Priority:** p1

`php artisan make:filament-resource Testimonial --generate`, then edit: the table filtered by
`status` (a `SelectFilter`), with **Approve** and **Reject** row actions. Approve → `status='approved'`,
`approved_at=now()`. Reject → `status='rejected'`, `approved_at` untouched. `TestimonialPolicy`:
deny `create` from the panel (testimonials come only from the public form), grant a bespoke `approve`
ability to `super_admin`.

**Files**
- `app/Filament/Resources/Testimonials/TestimonialResource.php` — new (generated then edited)
- `app/Filament/Resources/Testimonials/Tables/TestimonialsTable.php` — new — status filter + Approve/Reject actions
- `app/Policies/TestimonialPolicy.php` — edit: deny panel `create`, add `approve`
- `tests/Feature/Phase2/TestimonialResourceTest.php` — new

**Acceptance**

1. **WHEN** the super_admin uses the Approve row action **THE SYSTEM SHALL** set `status='approved'` and `approved_at=now()`.
2. **WHEN** the Reject row action is used **THE SYSTEM SHALL** set `status='rejected'` and leave `approved_at` null.
3. **WHEN** the resource table is used **THE SYSTEM SHALL** filter rows by `status`.
4. **WHEN** the super_admin attempts to create a testimonial from the panel **THE SYSTEM SHALL** deny it (testimonials are created only by the public form).
5. **WHEN** `tests/Feature/Phase2/TestimonialResourceTest.php` runs **THE SYSTEM SHALL** report 4 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/TestimonialResourceTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[uxui-dev] feat: TestimonialResource with Approve/Reject actions + status filter"
git tag p2-step-47-testimonial-admin
```

### `E6-T5` — Bust the endorsements cache when a testimonial is approved

**Depends on:** `E6-T4` · **Priority:** p1

`InvalidatePublicPageCache::forEndorsements()` — forget `public-page:{host}:{theme}:endorsements` for
every known host and seeded theme. `TestimonialObserver` calls it on `saved` / `deleted`; register it
in `AppServiceProvider`. Extend `tests/Feature/A11yTest.php`'s route list to include `/endorsements`.

**Files**
- `app/Observers/TestimonialObserver.php` — new
- `app/Providers/AppServiceProvider.php` — edit: register `TestimonialObserver`
- `app/Actions/Cache/InvalidatePublicPageCache.php` — edit: `forEndorsements()`
- `tests/Feature/Phase2/TestimonialCacheBustTest.php` — new

**Acceptance**

1. **WHEN** a `Testimonial` is saved (including an Approve action) **THE SYSTEM SHALL** forget the `/endorsements` page cache entry for every seeded theme key.
2. **WHEN** a `pending` testimonial is approved **THE SYSTEM SHALL** cause it to appear on the next `/endorsements` request without a manual cache clear.
3. **WHEN** a `rejected` testimonial is saved **THE SYSTEM SHALL** still never render it on `/endorsements`.
4. **WHEN** `tests/Feature/Phase2/TestimonialCacheBustTest.php` runs **THE SYSTEM SHALL** report 3 passing tests, 0 skipped.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest tests/Feature/Phase2/TestimonialCacheBustTest.php
./vendor/bin/pest tests/Feature/A11yTest.php
```

**Checkpoint**

```bash
git add -A && git commit -m "[backend-dev] feat: TestimonialObserver busts the /endorsements cache"
git tag p2-step-48-testimonial-cache-bust
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** a visitor submits a valid endorsement **THE SYSTEM SHALL** store one `pending` row, show "pending review", and that row **SHALL** be invisible on `/endorsements`.
2. **WHEN** the owner approves it in Filament **THE SYSTEM SHALL** set `status='approved'` + `approved_at`, bust the `/endorsements` cache, and the endorsement **SHALL** render on the next request with its linked contact; a rejected one **SHALL** never render.
3. **WHEN** the route table and every Life view are inspected **THE SYSTEM SHALL** show `/endorsements` in the professional area only, with no `/life/endorsements` route and no testimonial form under `/life`.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
./vendor/bin/pest tests/Feature/A11yTest.php
git tag -l 'p2-step-*' | wc -l   # expect: 48 — every Phase-2 step tagged
```

## Pitfalls

- **The public form setting `status`.** It is always `pending` on insert. Approval is admin-only.
- **A testimonial form or section under `/life`.** Professional area only — assert `/life/endorsements`
  does not resolve.
- **Approving without busting the cache.** Observer → `forEndorsements()`; the Approve action must
  save the model so the observer fires.
- **Importing `ContactForm` instead of mirroring it.** The contact form's rate limit and honeypot are
  a frozen Phase-1 interface — copy the pattern, do not touch `ContactForm`.
- **`contact_value` validated with one rule for all types.** `email` vs `url` per `contact_type`.

## Before moving on

- [ ] Every task is `done` in `tasks.json` — none `in_progress`.
- [ ] Every `verify` command of every task passed, not just the first.
- [ ] No `verify` command was edited; none skipped for a missing file.
- [ ] Every task has its `p2-step-*` checkpoint tag; `git tag -l 'p2-step-*' | wc -l` reports **48**.
- [ ] Gate passes clean from the project root with the bundle present.
- [ ] Every "Produced" contract exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` unchanged (this epic adds no variable).
- [ ] One commit per task, each prefixed with its role/type, each followed by its checkpoint tag.
- [ ] Each merged task has a `review/<id>.md` = `APROBADO` dated before its production promote.
- [ ] Blueprint §20.1's manual gates are all checked (sponsor approvals, host-parity, flag toggles, `.mmdb` present on staging/prod and un-tracked, rollback drill).
