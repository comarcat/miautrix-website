---
name: deploy
description: Release miautrix-website to the production Debian 13 LXC. Use when asked to deploy, ship, push a release, promote to production, roll back a release, or when a build step's Verify calls infra/deploy.sh. Covers the zero-downtime release sequence, the cache and migration order, the queue restart, and the rollback path.
---

# Deploy miautrix-website

## When to use

- "deploy", "ship it", "release to production", "promote", "roll back"
- Blueprint §9 steps 5 and 24, and any hotfix that must reach `https://$APP_DOMAIN`.

## Before you start

- `.env` on the build machine defines `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_PATH`
  and `APP_DOMAIN`. Load it: `set -a; . ./.env; set +a`.
- The gate must be green locally first:
  `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`.
- Deploy from `main` only. `main` is protected: it is reached by a reviewed PR, never a direct push.

## Steps

1. Confirm the working tree is committed and you are on `main` at the commit CI marked green.
2. Run `bash infra/deploy.sh`. It performs, in this exact order:
   1. `rsync` the release into a new timestamped directory under `$DEPLOY_PATH/releases/`,
      excluding `.env`, `storage/`, `node_modules/` and `blueprints/`.
   2. Symlink the shared `.env` and `storage/` into the release.
   3. `composer install --no-dev --optimize-autoloader` inside the release.
   4. `php artisan migrate --force` — **expand-only**. A destructive change ships in a later
      release, never in the same one as the code that stops using the column.
   5. `php artisan config:cache route:cache view:cache event:cache`.
   6. Atomically repoint `$DEPLOY_PATH/current` at the new release.
   7. `php artisan queue:restart` and reload PHP-FPM and Nginx.
   8. Prune to the last five releases.
3. Verify from the build machine, not from the server.

## Verify

```bash
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/health")" = 200
curl -sS "https://$APP_DOMAIN/health" | jq -e '.database == "ok" and .redis == "ok" and .migrations == "current"'
curl -sSI "https://$APP_DOMAIN/" | grep -qi '^strict-transport-security:'
```

## Rollback

```bash
ssh -p "$DEPLOY_PORT" "$DEPLOY_USER@$DEPLOY_HOST" "$DEPLOY_PATH/bin/rollback.sh"
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
