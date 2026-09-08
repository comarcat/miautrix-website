---
name: deploy
description: Release miautrix-website to the production Debian 13 LXC. Use when asked to deploy, ship, push a release, promote to production, roll back a release, or when a build step's Verify calls infra/deploy.sh. Covers the zero-downtime release sequence, the cache and migration order, the queue restart, and the rollback path.
---

# Deploy miautrix-website

## When to use

- "deploy", "ship it", "release to production", "promote", "roll back"
- Blueprint §9 steps 5 and 24, and any hotfix that must reach `https://$APP_DOMAIN`.

## Before you start

- `infra/provision.sh` has already run once on the target LXC (Nginx, PHP 8.4-FPM, Node 24,
  Composer, the `deploy` user, firewall, the `$DEPLOY_PATH` release structure). It does not
  install PostgreSQL — the database is a separate, already-running server; only DB_HOST in
  `.env` points at it.
- `.env` on the build machine defines `LXC_HOST`, `LXC_SSH_KEY`, `DEPLOY_PATH`, `DEPLOY_USER`,
  `DEPLOY_PORT`, `GITHUB_REPO_URL`, and `APP_DOMAIN` (the production hostname). Load it:
  `set -a; . ./.env; set +a`.
- The gate must be green locally first:
  `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`.
- Deploy from `main` only. `main` is protected: it is reached by a reviewed PR, never a direct push.

## Steps

1. Confirm the working tree is committed and you are on `main` at the commit CI marked green.
2. Run `bash infra/deploy.sh`. It performs, in this exact order:
   1. Runs the local gate above — refuses to proceed if any command fails.
   2. Confirms the current branch is `main` and the tree is clean; captures the commit SHA.
   3. SSHes into the LXC and `git clone`s that branch/commit straight from GitHub into a new
      timestamped directory under `$DEPLOY_PATH/releases/` — nothing is rsynced from the build
      machine except the SSH commands themselves.
   4. Symlinks the shared `storage/` and `.env` into the release, then
      `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build` **on the
      server**.
   5. `php artisan migrate --force` — **expand-only**. A destructive change ships in a later
      release, never in the same one as the code that stops using the column.
   6. `php artisan config:cache route:cache view:cache event:cache`.
   7. Atomically repoints `$DEPLOY_PATH/current` at the new release.
   8. `php artisan queue:restart`, reloads PHP-FPM and Nginx.
   9. Prunes to the last five releases.
3. Verifies from the build machine, not from the server — see below.

## Verify

```bash
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/up")" = 200
curl -sS "https://$APP_DOMAIN/up" | jq -e '.database == "ok" and .migrations == "current"'
curl -sSI "https://$APP_DOMAIN/" | grep -qi '^strict-transport-security:'
```

## Rollback

```bash
ssh -i "$LXC_SSH_KEY" -p "$DEPLOY_PORT" "$DEPLOY_USER@$LXC_HOST" "$DEPLOY_PATH/bin/rollback.sh"
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
```

`rollback.sh` repoints `current` at the previous release and restarts the queue. It does **not**
reverse a migration — that is why migrations are expand-only.

## Do not

- Never put `php artisan migrate:fresh` or `db:wipe` in a deploy path. It drops the only copy of the
  content, which is the irreplaceable part of this project.
- Never deploy without a supervised queue worker running: jobs enqueue silently and never run.
- Never deploy a release whose CI run is not green — branch protection exists for exactly this.
- Never edit files inside a release directory on the server. Fix, commit, deploy.
