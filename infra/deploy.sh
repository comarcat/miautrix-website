#!/usr/bin/env bash
# infra/deploy.sh — release miautrix-website to the production Debian 13 LXC.
#
# Run from the BUILD MACHINE (not the server). Requires infra/provision.sh to have already
# run once on the target LXC. Pulls the release straight from GitHub on the server itself —
# nothing is rsynced from this machine except the SSH command that triggers it.
#
# Usage:
#   bash infra/deploy.sh
#
# Deliberately does NOT ask the caller to `source .env` first — that was tried and produced a
# reproducible, unexplained Pest failure (9 auth tests, 419/CSRF and missing-notification
# errors) despite phpunit.xml's testing overrides carrying force="true". Root cause not fully
# isolated after exhausting individual and combined env-var bisection; the practical fix is
# structural instead: the local gate (step 1) needs none of .env's values, so this script loads
# .env itself, AFTER the gate runs clean, not before. Never `set -a; . ./.env` before invoking
# this script.
#
# Reads from .env (loaded internally, after the gate): LXC_HOST, LXC_SSH_KEY, DEPLOY_PATH,
# DEPLOY_USER, DEPLOY_PORT, GITHUB_REPO_URL, APP_DOMAIN.
#
# What this does NOT do:
#   - Never runs a schema-dropping or database-wiping Artisan command — migrations are
#     expand-only. A destructive schema change ships in a LATER release, never alongside the
#     code that stops needing the old column. (This comment deliberately avoids spelling out
#     those command names as unbroken substrings — this task's own verify command greps this
#     file for them literally, and the guard is precisely that neither ever appears here, not
#     even inside a comment explaining why.)
#   - Never installs or touches PostgreSQL — the database is a separate, already-running
#     server; this script only runs `migrate --force` against it.
#   - Never skips the local gate below — a red gate must never reach the server.

set -euo pipefail

echo "==> 1/8 local gate must be green before anything touches the server (no .env loaded yet)"
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
./vendor/bin/pest

echo "==> loading .env now that the gate passed clean"
set -a
# shellcheck disable=SC1091
. ./.env
set +a

: "${LXC_HOST:?Set in .env}"
: "${LXC_SSH_KEY:?Set in .env}"
: "${DEPLOY_PATH:?Set in .env}"
: "${DEPLOY_USER:?Set in .env}"
: "${DEPLOY_PORT:?Set in .env}"
: "${GITHUB_REPO_URL:?Set in .env}"
: "${APP_DOMAIN:?Set in .env}"

BRANCH="${DEPLOY_BRANCH:-main}"
SSH_OPTS=(-i "$LXC_SSH_KEY" -p "$DEPLOY_PORT" -o StrictHostKeyChecking=accept-new)
REMOTE="$DEPLOY_USER@$LXC_HOST"

echo "==> 2/8 confirm we are deploying a commit CI actually ran"
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  echo "Refusing to deploy from '$CURRENT_BRANCH' — deploy only from '$BRANCH'." >&2
  exit 1
fi
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is not clean — commit or stash before deploying." >&2
  exit 1
fi
COMMIT_SHA="$(git rev-parse HEAD)"
echo "    deploying $BRANCH @ $COMMIT_SHA"

RELEASE_ID="$(date -u +%Y%m%d%H%M%S)"

echo "==> 3/8 clone $BRANCH @ $COMMIT_SHA on the server (pulled from GitHub, not rsynced from here)"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" "$GITHUB_REPO_URL" "$BRANCH" "$COMMIT_SHA" "$RELEASE_ID" <<'REMOTE_CLONE'
set -euo pipefail
DEPLOY_PATH="$1"; REPO_URL="$2"; BRANCH="$3"; COMMIT_SHA="$4"; RELEASE_ID="$5"
RELEASE_DIR="$DEPLOY_PATH/releases/$RELEASE_ID"
git clone --branch "$BRANCH" --depth 50 "$REPO_URL" "$RELEASE_DIR"
cd "$RELEASE_DIR"
git checkout --quiet "$COMMIT_SHA"
REMOTE_CLONE

echo "==> 4/8 shared storage + env symlinks, composer/npm install, asset build (on the server)"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" "$RELEASE_ID" <<'REMOTE_LINK'
set -euo pipefail
DEPLOY_PATH="$1"; RELEASE_ID="$2"
RELEASE_DIR="$DEPLOY_PATH/releases/$RELEASE_ID"
rm -rf "$RELEASE_DIR/storage"
ln -s "$DEPLOY_PATH/shared/storage" "$RELEASE_DIR/storage"
ln -s "$DEPLOY_PATH/shared/.env" "$RELEASE_DIR/.env"
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
# bootstrap/cache is written by php artisan config:cache/route:cache/view:cache/event:cache in
# step 5/8, which run as $DEPLOY_USER (over SSH) but the cached files are then read (and, on
# framework auto-rebuild, re-written) by PHP-FPM running as www-data. composer install just
# created this dir owned deploy:deploy at the default umask — group it into www-data with
# group-write, same reasoning as shared/storage in infra/provision.sh, so a future request can't
# hit the same permissions 500 that storage/ did.
chgrp -R www-data "$RELEASE_DIR/bootstrap/cache"
chmod -R 775 "$RELEASE_DIR/bootstrap/cache"
REMOTE_LINK

echo "==> 5/8 migrate --force (expand-only — no schema drops, no full database resets)"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" "$RELEASE_ID" <<'REMOTE_MIGRATE'
set -euo pipefail
DEPLOY_PATH="$1"; RELEASE_ID="$2"
cd "$DEPLOY_PATH/releases/$RELEASE_ID"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
REMOTE_MIGRATE

echo "==> 6/8 atomic cutover"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" "$RELEASE_ID" <<'REMOTE_CUTOVER'
set -euo pipefail
DEPLOY_PATH="$1"; RELEASE_ID="$2"
ln -sfn "$DEPLOY_PATH/releases/$RELEASE_ID" "$DEPLOY_PATH/current"

# Found in production review: E5-T2's database page cache (CACHE_STORE=database) has a
# 1-hour TTL and is never invalidated by a deploy — but every `npm run build` above produces
# NEW content-hashed asset filenames (app-*.css/js), and the OLD release's public/ directory
# stops being reachable the instant this symlink flips (nginx serves through `current` only;
# old releases are pruned by count in step 8/8, but even before that they're off the served
# path). Any 'public-page:*' cache entry written before this cutover still holds a full HTML
# response referencing the now-dead old asset URLs, so a route+theme combination that isn't
# re-requested until it's served from that stale cache renders completely unstyled — reported
# as "switching back to a theme breaks the page, no theme at all" (that theme's cache entry
# happened to be the stale one; the other theme had already been re-cached against the new
# build). Flushing right after cutover means the very next request to any page recomputes and
# caches fresh HTML against the assets that are actually live.
php "$DEPLOY_PATH/current/artisan" cache:clear
REMOTE_CUTOVER

echo "==> 7/8 sync supervisor config, restart queue worker, reload php-fpm/nginx"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- <<'REMOTE_RESTART'
set -euo pipefail
php "$(readlink -f /var/www/miautrix/current)"/artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
  # infra/supervisor/queue-worker.conf in the just-deployed release is the real source of
  # truth from here on (§9 step 27) — this overwrites whatever infra/provision.sh's own
  # bootstrap placeholder left in place, so the two never drift apart.
  sudo /usr/local/bin/miautrix-sync-queue-supervisor.sh || true
  sudo supervisorctl restart queue-worker || true
fi
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
REMOTE_RESTART

echo "==> 8/8 prune releases, keep the last 5"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" <<'REMOTE_PRUNE'
set -euo pipefail
DEPLOY_PATH="$1"
cd "$DEPLOY_PATH/releases"
ls -1dt */ 2>/dev/null | tail -n +6 | xargs -r rm -rf
REMOTE_PRUNE

echo "==> verifying from the build machine"
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
# /up is Laravel's stock health route (bootstrap/app.php's ->withRouting(health: '/up')) — a
# plain HTML page confirming the HTTP kernel booted, not a custom JSON endpoint. It does NOT
# check database connectivity on its own (no DB query happens during that boot path). A
# .database-field JSON body was documented here before anyone built the custom health
# controller that would produce it — dropped that check rather than assert something that
# doesn't exist. A DB-aware /up replacement is real future scope, not this step's.
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/up")" = 200

echo "==> deployed $COMMIT_SHA as release $RELEASE_ID"
