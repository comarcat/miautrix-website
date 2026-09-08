# Epic 01: Foundation, CI & Deploy

> After this epic, a scaffolded Laravel app with the full tooling in place has reached the real
> production domain over HTTPS, protected by GitHub branch rules and a green CI pipeline — before a
> single content feature exists.

| | |
|---|---|
| **Epic id** | `01-foundation` |
| **Tasks** | `E1-T1` … `E1-T5` |
| **Depends on** | nothing — start here |
| **Unlocks** | `02-schema-auth` |
| **Parallel with** | nothing — every later epic needs a deployed app and a green CI gate |

You do not need any other file to complete this epic. Everything below is repeated here on purpose.

---

## Stack

Laravel 13 (`^13.30`) · PHP `^8.4` (hard floor set by Pest `^5.1`'s own `^8.4` requirement, not by
Laravel) · Livewire `^4.4` · Filament `^5.7` (installed in epic 03) · Tailwind CSS `^4.3.3` via
`@tailwindcss/vite` · Alpine.js `^3.17` · Vite `^8.2` · PostgreSQL 18 · Pest `^5.1` · **no Redis** —
Laravel's database driver handles queue, cache, and session. Self-hosted on a Debian 13 "trixie" LXC
behind Nginx and Cloudflare. Package manager: Composer (PHP) + npm (JS).

| Task | Command |
|---|---|
| Local services up/down | `docker compose up -d` / `docker compose down` |
| Install | `composer install` · `npm install` |
| Format | `./vendor/bin/pint --test` |
| Static analysis | `./vendor/bin/phpstan analyse` |
| Test (full) | `./vendor/bin/pest` |
| Build assets | `npm run build` |
| Dev server | `php artisan serve` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
passes before any task here is marked done.

The compose file (`docker-compose.yml`, at the project root) and its PostgreSQL 18 service are
already at the project root before task 1 starts — they ship in the bundle's `workspace/` and are
copied by Bootstrap. You do not create them; you only start them.

## Directory subtree

```
composer.json           # authored by `laravel new` (E1-T1), then edited by composer require lines
package.json             # authored by `laravel new --livewire`, then edited by npm install lines
vite.config.js            # Tailwind v4 plugin wired in E1-T1
resources/css/app.css      # minimal @import "tailwindcss" in E1-T1 — full @theme tokens land in epic 04
.github/workflows/ci.yml   # confirmed/edited in E1-T3 — real content already shipped in workspace/
infra/
  provision.sh              # NEW in E1-T4
routes/web.php              # hello-world route added in E1-T5
resources/views/public/hello.blade.php  # NEW in E1-T5
```

Everything outside this subtree is out of scope. If a task seems to require editing a file not
listed here, stop and report — it means the epic boundary is wrong.

## Data model touched here

NOT APPLICABLE — no schema work in this epic. Schema starts in `02-schema-auth`.

## Contracts

**Consumed** — nothing; this is the first epic.

**Produced** — later epics depend on exactly these:

| Export | Signature | Used by |
|---|---|---|
| A working Laravel app at the project root, `composer.json`/`package.json` authored | filesystem state | every later epic |
| `.github/workflows/ci.yml` running composer/npm audits + Pest against a Postgres 18 service | CI gate | every later epic's PR |
| The production domain, live over HTTPS on the Debian LXC | `https://$APP_DOMAIN/` returns 200 | epic 04's public pages, epic 05's Lighthouse gate |

## Conventions that bite in this area

- **No Redis.** Never add `predis/predis` or `laravel/horizon` — the queue/cache/session driver is
  `database` everywhere, confirmed by a grep guard in E1-T1's Verify.
- **The `gh` CLI is NOT installed** on the build machine — E1-T2 and E1-T3 use the GitHub REST API
  via `curl` with `$GITHUB_TOKEN`, never `gh`.
- **`laravel new` may refuse a non-empty target directory** (it has a `.git`) — if so, scaffold into
  a scratch subdirectory and move the tree up one level, preserving `.git`. State this explicitly.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/security.md` for anything touching auth
later. Both sit in the project root — the builder copied them there from the bundle's `workspace/`
before task one.

---

## Tasks

### `E1-T1` — Scaffold Laravel, Livewire, Pest, tooling, Tailwind v4

**Depends on:** nothing · **Priority:** p0

Run `laravel new miautrix-website --livewire --pest --database=pgsql --directory=.` from the project
root. This authors `composer.json` and `package.json` — do not hand-write either. Add
`composer require --dev laravel/pint:^1.30 larastan/larastan:^3.11 laravel/telescope:^5.23`, then
`composer require laravel/boost:^2.7 && php artisan boost:install`. Add
`npm install tailwindcss@^4.3.3 @tailwindcss/vite@^4.3.3 alpinejs@^3.17` and wire the Vite plugin.
`phpstan.neon` and `pint.json` are already at the project root from the workspace copy — do not
recreate them. Confirm no `predis/predis` or `laravel/horizon` ever enters `composer.json`.

**Files**
- `composer.json` — new (authored by `laravel new`, then edited by the `composer require` lines above)
- `package.json` — new (authored by `laravel new --livewire`, then edited by `npm install`)
- `vite.config.js` — edit: add the Tailwind v4 Vite plugin
- `resources/css/app.css` — new: minimal `@import "tailwindcss";`
- `.env.example` — confirm (already copied from `workspace/`; add any app-specific key this step introduces)

**Acceptance**

1. **WHEN** `./vendor/bin/pint --test` runs **THE SYSTEM SHALL** exit 0.
2. **WHEN** `./vendor/bin/phpstan analyse` runs **THE SYSTEM SHALL** exit 0 with zero errors at level 5.
3. **WHEN** `./vendor/bin/pest` runs **THE SYSTEM SHALL** exit 0 with 0 failed and 0 skipped.
4. **WHEN** `npm run build` runs **THE SYSTEM SHALL** exit 0 and emit `public/build/manifest.json`.
5. **WHEN** the built server receives a GET to `/` **THE SYSTEM SHALL** return HTTP 200.
6. **WHEN** `composer.json` is inspected **THE SYSTEM SHALL** contain no `predis/predis` and no `laravel/horizon` entry.

**Verify**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest
php artisan serve --port=8123 & SERVE_PID=$!; sleep 1; test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8123/)" = 200; kill "$SERVE_PID"
! grep -q '"predis/predis"' composer.json
! grep -q '"laravel/horizon"' composer.json
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T1: scaffold laravel + livewire + pest + tooling"
git tag step-01-scaffold
```

### `E1-T2` — Git remote, branches, branch protection via REST API

**Depends on:** `E1-T1` · **Priority:** p0

Add `origin` from `$GITHUB_REPO_URL`, push `main`, create and push `develop`. Use the GitHub REST
API via `curl` with `$GITHUB_TOKEN` to enable branch protection on `main` (PR required, `ci` status
check required, no force-push). Never use the `gh` CLI — it is not installed.

**Files**
- `.git/config` — edit (remote added)

**Acceptance**

1. **WHEN** `git remote -v` runs **THE SYSTEM SHALL** list `origin` pointing at the configured repository URL.
2. **WHEN** `main` is pushed **THE SYSTEM SHALL** be visible at `origin/main`.
3. **WHEN** `develop` is pushed **THE SYSTEM SHALL** be visible at `origin/develop`.
4. **WHEN** the branch protection API is queried for `main` **THE SYSTEM SHALL** report `required_pull_request_reviews` present and `required_status_checks.contexts` containing `ci`.
5. **WHEN** an unauthenticated force-push to `main` is attempted **THE SYSTEM SHALL** be rejected by GitHub.

**Verify**

```bash
git ls-remote origin main
curl -sS -H "Authorization: Bearer $GITHUB_TOKEN" -H "Accept: application/vnd.github+json" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/branches/main/protection" | jq -e --arg ctx "ci" '.required_pull_request_reviews != null and (.required_status_checks.contexts | index($ctx)) != null'
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T2: git remote, branches, branch protection" --allow-empty
git tag step-02-git-remote
```

### `E1-T3` — GitHub Actions CI with dependency audits

**Depends on:** `E1-T2` · **Priority:** p0

`.github/workflows/ci.yml` already ships in `workspace/` with the correct content — confirm it
(PHP 8.4 matrix, Postgres 18 service container only, `composer audit`, `pint --test`,
`phpstan analyse`, `migrate --force`, `pest --ci`, `npm audit --audit-level=high`, `npm run build`)
and edit only if a project-specific adjustment is needed.

**Files**
- `.github/workflows/ci.yml` — confirm/edit

**Acceptance**

1. **WHEN** `.github/workflows/ci.yml` is parsed as YAML **THE SYSTEM SHALL** be syntactically valid.
2. **WHEN** the workflow runs on a pushed branch **THE SYSTEM SHALL** execute `composer audit` and `npm audit --audit-level=high` as distinct steps.
3. **WHEN** the workflow runs **THE SYSTEM SHALL** use a PostgreSQL 18 service container and no Redis service.
4. **WHEN** a pushed branch's CI run completes **THE SYSTEM SHALL** report success (all steps exit 0).
5. **WHEN** `composer audit` finds a high-severity advisory **THE SYSTEM SHALL** fail the workflow (exit non-zero).

**Verify**

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml'))"
grep -q "composer audit" .github/workflows/ci.yml
grep -q "npm audit --audit-level=high" .github/workflows/ci.yml
! grep -qi "redis" .github/workflows/ci.yml
git push origin HEAD:ci-smoke-check && curl -sS -H "Authorization: Bearer $GITHUB_TOKEN" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/commits/$(git rev-parse HEAD)/check-runs" | jq -e --arg ctx "ci" --arg ok "success" '[.check_runs[] | select(.name==$ctx)][0].conclusion == $ok' && git push origin --delete ci-smoke-check
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T3: github actions ci with dependency audits"
git tag step-03-ci
```

### `E1-T4` — Provision Debian 13 LXC

**Depends on:** `E1-T3` · **Priority:** p0

Write `infra/provision.sh`, run once over SSH against `$LXC_HOST` with `$LXC_SSH_KEY`. Install and
enable Nginx, PHP-FPM 8.4+, Node 24 LTS, Composer, `supervisor`, UFW restricted to 80/443 and a
restricted SSH port; create the `deploy` user and release directory layout. **No PostgreSQL here** —
the database is a separate, already-running server the owner administers (confirmed during this
build: `DB_HOST` in `.env`); this LXC is web/app tier only. No Redis either.

**Files**
- `infra/provision.sh` — new

**Acceptance**

1. **WHEN** the remote host is queried for `systemctl is-active nginx` **THE SYSTEM SHALL** report `active`.
2. **WHEN** queried for `systemctl is-active php8.4-fpm` **THE SYSTEM SHALL** report `active`.
3. **WHEN** queried for `systemctl is-active supervisor` **THE SYSTEM SHALL** report `active`.
4. **WHEN** `ufw status` is queried **THE SYSTEM SHALL** show only 80, 443, and the configured SSH port as ALLOW.
5. **WHEN** the remote host is queried for an installed PostgreSQL server package **THE SYSTEM SHALL** confirm none is installed.

**Verify**

```bash
ssh -i "$LXC_SSH_KEY" "root@$LXC_HOST" 'systemctl is-active nginx && systemctl is-active php8.4-fpm && systemctl is-active supervisor'
test "$(ssh -i "$LXC_SSH_KEY" "root@$LXC_HOST" 'ufw status | grep -cE "ALLOW"')" -ge 3
ssh -i "$LXC_SSH_KEY" "root@$LXC_HOST" '! dpkg -l | grep -qi postgresql-'
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T4: provision debian 13 lxc"
git tag step-04-provision
```

### `E1-T5` — Deploy hello-world to production domain over HTTPS

**Depends on:** `E1-T4` · **Priority:** p0

Deploy the step-1 scaffold to the LXC: Nginx vhost, PHP-FPM pool, `resources/views/public/hello.blade.php`
at `/`. Point the production domain at the LXC via Cloudflare (proxied), attach TLS. This is the
step that proves the site reaches the internet before any feature is built (Risk #3).

**Files**
- `resources/views/public/hello.blade.php` — new
- `routes/web.php` — edit: hello-world route

**Acceptance**

1. **WHEN** a GET request reaches `https://$APP_DOMAIN/` **THE SYSTEM SHALL** return HTTP 200.
2. **WHEN** the TLS handshake is inspected **THE SYSTEM SHALL** present a valid certificate chain.
3. **WHEN** `http://$APP_DOMAIN/` is requested **THE SYSTEM SHALL** redirect (301/308) to `https://$APP_DOMAIN/`.
4. **WHEN** the LXC's Nginx access log is inspected after a request **THE SYSTEM SHALL** show it served by PHP-FPM, not a static file.

**Verify**

```bash
set -a; source .env; set +a                        # loads APP_DOMAIN
test "$(curl -sS -o /dev/null -w '%{http_code}' https://$APP_DOMAIN/)" = 200
test "$(curl -sS -o /dev/null -w '%{http_code}' http://$APP_DOMAIN/)" -ge 300 -a "$(curl -sS -o /dev/null -w '%{http_code}' http://$APP_DOMAIN/)" -lt 400
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T5: deploy hello-world to production domain over https"
git tag step-05-deploy-hello-world
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** `https://$APP_DOMAIN/` is requested **THE SYSTEM SHALL** return 200 with a valid TLS chain, before any epic-02 schema work exists.
2. **WHEN** the CI workflow runs on any pushed branch **THE SYSTEM SHALL** fail the whole run if `composer audit` or `npm audit --audit-level=high` reports a high-severity advisory.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest
set -a; source .env; set +a                        # loads APP_DOMAIN
test "$(curl -sS -o /dev/null -w '%{http_code}' https://$APP_DOMAIN/)" = 200
```

## Pitfalls

- **Reaching for `gh`.** It is not installed on the build machine. Everything GitHub-side is REST
  API calls with `curl` and `$GITHUB_TOKEN`.
- **Adding Redis "just for now."** Every later epic assumes the database driver. Adding
  `predis/predis` here breaks the grep guard in E1-T1's own Verify and contradicts §2/§11 of the blueprint.
- **Deploying last.** This epic's whole point is deploying at step 5, not step 27. Do not defer E1-T5.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-01-scaffold`
      through `step-05-deploy-hello-world`.
- [ ] Gate command passes clean, run from the project root.
- [ ] Every "Produced" contract above exists with the stated signature.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` updated if this epic added a variable.
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
