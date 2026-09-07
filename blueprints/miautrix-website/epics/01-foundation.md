# Epic 01: Foundation, CI & Deploy

> After this epic, a scaffolded Laravel app with the full tooling in place has reached the real
> production domain over HTTPS, protected by GitHub branch rules and a green CI pipeline — before a
> single feature exists.

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

Laravel 13 · PHP 8.4+ · Livewire 4 · Tailwind CSS v4 (`@tailwindcss/vite`) · Alpine.js · Vite ·
PostgreSQL 18 · Redis 8 · Pest 5 · self-hosted on a Debian 13 LXC behind Nginx and Cloudflare.
Package manager: Composer (PHP) + npm (JS). No runtime version file beyond `composer.json`'s
`require.php` — PHP 8.4 is enforced there and by Pest's own requirement (`^8.4`), which is stricter
than Laravel's `^8.3`.

| Task | Command |
|---|---|
| Local services up/down | `docker compose up -d --wait` / `docker compose down` |
| Install | `composer install` · `npm ci` |
| Dev server | `php artisan serve` |
| Build | `npm run build` |
| Format check | `./vendor/bin/pint --test` |
| Static analysis | `./vendor/bin/phpstan analyse` |
| Tests | `./vendor/bin/pest` |
| Deploy | `bash infra/deploy.sh` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`
passes before any task here is marked done.

If any task below verifies against a real service, start it first with `docker compose up -d --wait`.
`docker-compose.yml` and every other verify-critical config (`phpstan.neon`, `pint.json`,
`phpunit.xml`, `.github/workflows/ci.yml`, `.env.example`, `.gitignore`) shipped in this bundle's
`workspace/` and is already at the project root before task one — you do not write these files, and
you never substitute a fake for a service the acceptance criteria name.

## Directory subtree

Only the parts this epic touches:

```
composer.json  composer.lock  package.json  package-lock.json    # NEW — authored by the scaffold, edited by named commands
vite.config.js                                                   # NEW
resources/
  css/app.css                                                    # NEW — token file, only a minimal @theme stub here (full in epic 04)
config/
  required_env.php                                               # NEW — boot validator, only APP_KEY required at this step
scripts/
  github-bootstrap.sh              # NEW
  github-assert-protection.sh      # NEW
  ci-assert-run.sh                 # NEW
  assert-lxc.sh                    # NEW
infra/
  provision.sh                     # NEW
  deploy.sh                        # NEW — minimal stub, extended in epic 04's last task
routes/web.php                     # exists (scaffold default), untouched here beyond what the scaffold writes
.github/workflows/ci.yml           # exists — shipped in workspace/, this epic is where it's first exercised
```

Everything outside this subtree is out of scope. If a task seems to require editing a file not
listed here, stop and report — it means the epic boundary is wrong.

## Data model touched here

None. Schema work starts in `02-schema-auth`.

## Contracts

**Consumed** — already exists, do not rebuild:

| From | Interface | Guarantee |
|---|---|---|
| bundle `workspace/` | `docker-compose.yml`, `.github/workflows/ci.yml`, `phpstan.neon`, `pint.json`, `phpunit.xml`, `.env.example`, `.gitignore` | Real files, already at the project root, matching the values this epic's tasks assert against |

**Produced** — later epics depend on exactly these:

| Export | Signature | Used by |
|---|---|---|
| `https://$APP_DOMAIN/` | responds `200` over HTTPS | every later epic's deploy-time verification |
| `main` branch, protected, `ci` required | GitHub branch protection ruleset | every later epic's PR merges |
| `config/required_env.php` | boot validator, degrades by step (only `APP_KEY` required after this epic) | `02-schema-auth` adds `DB_*`/`REDIS_*` as required |

## Conventions that bite in this area

- **No `gh` CLI on the build machine.** `E1-T2` uses the GitHub REST API directly via `curl` with
  `$GITHUB_TOKEN` — do not attempt to install or shell out to `gh`.
- **Never write a version number here from memory.** Every pin (PHP 8.4, Laravel 13.30, etc.) is
  already fixed in `blueprint.md` §11 — install exactly those constraints, do not "helpfully" update
  to a newer minor mid-build.
- **Migrations never run in this epic** beyond the scaffold's own defaults — there is no schema yet.
- `infra/deploy.sh` here is a **minimal stub** (rsync + composer install + serve). It gains
  `migrate --force`, cache warming and the queue restart only once those things exist to warm/restart
  (epic `04`, task `E4-T7`) — do not front-load them here; the manifest ruling in `blueprint.md` §2/§10
  applies the same logic to every artifact: a step only gains a capability once its dependency exists.

Full project rules: `CLAUDE.md`. Area rules: `.claude/rules/{database,filament,frontend,security}.md`.
Both sit in the project root — the builder copied them there from the bundle's `workspace/` before
task one.

---

## Tasks

Listed in the same order as `tasks.json`. Work top to bottom.

### `E1-T1` — Scaffold Laravel and the toolchain

**Depends on:** nothing · **Priority:** p0

Run `composer create-project laravel/laravel . --no-interaction --prefer-dist` (this **is** the
manifest author — `composer.json`/`package.json` come from the scaffold, per `blueprint.md` §2's
manifest ruling; never hand-write them). Add Livewire, Pest, Pint, Larastan, and the Tailwind v4 +
Alpine wiring as named `composer require`/`npm install` commands **after** the scaffold line, per
`blueprint.md` §10's Bootstrap block. Write `resources/css/app.css` with `@import "tailwindcss";`, a
minimal `@theme` block (full token set lands in epic 04's `E4-T1` — this step only needs the build to
succeed, not the final palette), and the three `@source` directives so the class scanner skips
`blueprints/`. Write `config/required_env.php` validating only `APP_KEY` at this point.

**Files**
- `composer.json`, `composer.lock` — new (scaffold-authored, edited)
- `package.json`, `package-lock.json` — new (scaffold-authored, edited)
- `resources/css/app.css` — new
- `vite.config.js` — new
- `config/required_env.php` — new

**Acceptance**

1. **WHEN** `composer install --no-interaction` runs on a clean checkout **THE SYSTEM SHALL** exit 0
   and populate `vendor/` with `laravel/framework`, `livewire/livewire` and `pestphp/pest` at the
   versions pinned in `blueprint.md` §11.
2. **WHEN** `./vendor/bin/pint --test` runs on the freshly scaffolded tree **THE SYSTEM SHALL** exit 0.
3. **WHEN** `./vendor/bin/phpstan analyse` runs **THE SYSTEM SHALL** exit 0 at level 5 over `app`,
   `config`, `database`, `routes`.
4. **WHEN** `./vendor/bin/pest` runs **THE SYSTEM SHALL** report the scaffold's own default test
   passing, 0 failed, 0 skipped.
5. **WHEN** `npm ci && npm run build` runs **THE SYSTEM SHALL** exit 0 and write a `manifest.json`
   under `public/build/`.
6. **WHEN** `php artisan serve` starts and a request hits `/` **THE SYSTEM SHALL** respond `200`.

**Verify**

```bash
composer install --no-interaction
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pest
npm ci && npm run build
test -f public/build/manifest.json
php artisan serve --port=8123 & SERVE_PID=$!; sleep 1; test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8123/)" = 200; kill "$SERVE_PID"
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T1: scaffold laravel + livewire + pest + tailwind v4"
git tag step-01-scaffold
```

### `E1-T2` — Git remote and branch protection on GitHub

**Depends on:** `E1-T1` · **Priority:** p0

The project already has a local repo (one commit, `.gitattributes` only) with no remote. `gh` is
**not installed** on this build machine — use `curl` against the GitHub REST API with
`$GITHUB_TOKEN` (a fine-grained PAT, `Administration:write` + `Contents:write`, this repo only).
`scripts/github-bootstrap.sh` creates the repo if it doesn't exist, adds `origin`, pushes `main`,
creates `develop`. `scripts/github-assert-protection.sh` sets and then reads back a branch-protection
rule on `main` requiring the `ci` status check. Both scripts must be safe to re-run.

**Files**
- `scripts/github-bootstrap.sh` — new
- `scripts/github-assert-protection.sh` — new

**Acceptance**

1. **WHEN** `bash scripts/github-bootstrap.sh` runs against a repository with no remote **THE
   SYSTEM SHALL** add `origin`, push `main`, and create `develop`, idempotently.
2. **WHEN** `bash scripts/github-assert-protection.sh` runs **THE SYSTEM SHALL** confirm `main`
   requires the `ci` status check before merge.
3. **WHEN** `GITHUB_TOKEN` is unset **THE SYSTEM SHALL** exit 1 with a named error before making any
   API call.
4. **WHEN** `git ls-remote origin main` runs **THE SYSTEM SHALL** resolve a commit hash.

**Verify**

```bash
env -u GITHUB_TOKEN bash scripts/github-bootstrap.sh; test $? -eq 1
bash scripts/github-bootstrap.sh
git ls-remote origin main
bash scripts/github-assert-protection.sh
bash scripts/github-bootstrap.sh
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T2: github remote + branch protection"
git tag step-02-github
```

### `E1-T3` — GitHub Actions CI verified end to end

**Depends on:** `E1-T2` · **Priority:** p0

`.github/workflows/ci.yml` already ships in `workspace/`. This task's job is to prove it actually runs
against the live remote just created: push a throwaway branch, poll the Actions API until the run
concludes, assert success, then clean up the branch both locally and on the remote.
`scripts/ci-assert-run.sh` does the polling.

**Files**
- `scripts/ci-assert-run.sh` — new

**Acceptance**

1. **WHEN** a commit is pushed to a `task/**` branch **THE SYSTEM SHALL** trigger the `ci` workflow.
2. **WHEN** the workflow runs **THE SYSTEM SHALL** report every job (Pint, PHPStan, Pest, asset
   build) passing.
3. **WHEN** a PR targeting `main` is opened before `ci` finishes **THE SYSTEM SHALL** block merge.

**Verify**

```bash
git checkout -b task/00-ci-smoke && git commit --allow-empty -m "chore: trigger ci" && git push origin task/00-ci-smoke
bash scripts/ci-assert-run.sh task/00-ci-smoke
git checkout main && git branch -D task/00-ci-smoke && git push origin --delete task/00-ci-smoke
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T3: ci verified end to end"
git tag step-03-ci
```

### `E1-T4` — Provision the Debian 13 LXC

**Depends on:** `E1-T1` · **Priority:** p0

`infra/provision.sh` runs over SSH against the already-created Proxmox LXC (2 vCPU / 4GB RAM / 30GB
disk, per the risk register): installs Nginx, PHP-FPM 8.4+, PostgreSQL 18, Redis 8, `supervisor`;
configures UFW for 22 (restricted), 80, 443 only; binds PostgreSQL and Redis to `127.0.0.1`; tunes
PHP-FPM `pm.max_children` and PostgreSQL `shared_buffers` for the 4GB envelope. Must be idempotent —
a second run changes nothing. `scripts/assert-lxc.sh` does the remote read-back.

**Files**
- `infra/provision.sh` — new
- `scripts/assert-lxc.sh` — new

**Acceptance**

1. **WHEN** `infra/provision.sh` runs against a fresh Debian 13 LXC **THE SYSTEM SHALL** leave
   Nginx, PHP-FPM, PostgreSQL, Redis and supervisor all `active (running)`.
2. **WHEN** a TCP probe from outside the LXC targets port 5432 or 6379 **THE SYSTEM SHALL** fail to
   connect.
3. **WHEN** `ufw status` is read on the LXC **THE SYSTEM SHALL** list exactly 22 (restricted), 80,
   443 as allowed.
4. **WHEN** `infra/provision.sh` runs a second time on the same LXC **THE SYSTEM SHALL** exit 0 and
   change nothing.

**Verify**

```bash
bash infra/provision.sh
bash scripts/assert-lxc.sh
ssh -p "$DEPLOY_PORT" "$DEPLOY_USER@$DEPLOY_HOST" "sudo ufw status | grep -c ALLOW"
timeout 3 bash -c "echo > /dev/tcp/$DEPLOY_HOST/5432"; test $? -ne 0
bash infra/provision.sh
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T4: provision debian 13 lxc"
git tag step-04-lxc
```

### `E1-T5` — Deploy a hello-world release over HTTPS

**Depends on:** `E1-T4` · **Priority:** p0

`infra/deploy.sh` — a **minimal** version: rsync the current tree to a timestamped release directory
under `$DEPLOY_PATH/releases/`, `composer install --no-dev`, symlink `current`, reload PHP-FPM and
Nginx. Cloudflare DNS is already proxied at the LXC's public IP (confirmed ready by the user). No new
route — step `E1-T1`'s scaffold welcome page at `/` is the "hello-world".

**Files**
- `infra/deploy.sh` — new (minimal; extended in `E4-T7`)

**Acceptance**

1. **WHEN** `infra/deploy.sh` runs against the provisioned LXC **THE SYSTEM SHALL** place a release
   under `$DEPLOY_PATH/releases/`, symlink `current`, and reload Nginx and PHP-FPM.
2. **WHEN** `curl https://$APP_DOMAIN/` is requested from the build machine **THE SYSTEM SHALL**
   respond `200` with a valid certificate chain.
3. **WHEN** the same request is repeated after a second deploy **THE SYSTEM SHALL** still respond
   `200` with zero downtime observed.

**Verify**

```bash
bash infra/deploy.sh
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
curl -sSv "https://$APP_DOMAIN/" 2>&1 | grep -q "SSL certificate verify ok"
```

**Checkpoint**

```bash
git add -A && git commit -m "E1-T5: hello-world deployed over https"
git tag step-05-deploy
```

---

## Epic acceptance

The epic is done when every task is `done` **and**:

1. **WHEN** `git tag -l 'step-0[1-5]-*' | wc -l` runs **THE SYSTEM SHALL** report `5`.
2. **WHEN** the full local gate is run from a clean checkout **THE SYSTEM SHALL** pass, and a request
   to the production domain **THE SYSTEM SHALL** respond `200` over valid HTTPS.

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
```

## Pitfalls

- **Do not front-load schema, auth, or admin work into this epic** because "it would be convenient
  while the LXC is fresh." This epic proves the pipe, not the product — the next epic starts the
  data layer.
- **Do not put `migrate --force` or a queue restart into `infra/deploy.sh` here.** Nothing needs
  migrating yet and there is no queue worker yet; both are added in `E4-T7` once they exist.
- **`GITHUB_TOKEN` must never be echoed or logged** by either GitHub script — a leaked fine-grained
  PAT with `Administration:write` on this repo is a real compromise, not a formality.

## Before moving on

- [ ] Every task in this epic is `done` in `tasks.json` — no task left `in_progress`.
- [ ] Every `verify` command of every task in this epic passed, not just the first one.
- [ ] No `verify` command was edited, and none was skipped because a file it names did not exist.
- [ ] **Every task in this epic has its `checkpoint` tag in version control** — `step-01-scaffold`
      through `step-05-deploy`. `git tag -l 'step-0[1-5]-*'` lists all 5.
- [ ] Gate command passes clean, run from the project root.
- [ ] `https://$APP_DOMAIN/` returns `200` over valid HTTPS.
- [ ] No file outside the subtree was modified.
- [ ] `.env.example` was not touched here beyond what the scaffold and Bootstrap already wrote — this
      epic adds no new application-level variable (the GitHub/deploy variables were already present
      in the shipped `.env.example`, per `blueprint.md` §10).
- [ ] One commit per task, each prefixed with its task id, each followed by its checkpoint tag.
