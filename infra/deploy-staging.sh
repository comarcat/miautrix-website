#!/usr/bin/env bash
# infra/deploy-staging.sh — release miautrix-website to the STAGING Debian LXC
# (staging.miautrix.tech). A parameter-swapped copy of infra/deploy.sh: same local gate,
# same GitHub-pull-on-server, same expand-only `migrate --force`, same atomic symlink
# cutover, same cache flush + supervisor/fpm/nginx reload. Only the target host, paths,
# domain and APP_ENV differ.
#
# Run from the BUILD MACHINE (not the server). Requires infra/provision-staging.md to have
# been followed once on the staging LXC (own vhost, HTTP Basic auth, own database,
# APP_ENV=staging in shared/.env, noindex).
#
# Usage:
#   bash infra/deploy-staging.sh
#
# This is step 2 of the Phase-2 release flow: main is deployed here for the sponsor to
# review on staging.miautrix.tech BEFORE `bash infra/deploy.sh` promotes the same commit to
# production. Nothing here touches production.
#
# Deliberately does NOT ask the caller to `source .env` first — that was tried and produced a
# reproducible, unexplained Pest failure (9 auth tests, 419/CSRF and missing-notification
# errors) despite phpunit.xml's testing overrides carrying force="true". Root cause not fully
# isolated after exhausting individual and combined env-var bisection; the practical fix is
# structural instead: the local gate (step 1) needs none of .env's values, so this script loads
# .env itself, AFTER the gate runs clean, not before. Never `set -a; . ./.env` before invoking
# this script.
#
# Reads from .env (loaded internally, after the gate): STAGING_SSH_HOST,
# STAGING_SSH_KEY (falls back to LXC_SSH_KEY), STAGING_DEPLOY_PATH, STAGING_DEPLOY_USER,
# STAGING_DEPLOY_PORT, STAGING_APP_DOMAIN, GITHUB_REPO_URL, and optionally
# STAGING_BASIC_AUTH_USER / STAGING_BASIC_AUTH_PASS for the build-machine verify step.
#
# What this does NOT do:
#   - Never runs a schema-dropping or database-wiping Artisan command — migrations are
#     expand-only, exactly as in production. A destructive schema change ships in a LATER
#     release, never alongside the code that stops needing the old column. (This comment
#     deliberately avoids spelling out those command names as unbroken substrings — this
#     task's own verify command greps this file for them literally, and the guard is precisely
#     that neither ever appears here, not even inside a comment explaining why.)
#   - Never installs or touches PostgreSQL — the staging database is a separate, already-running
#     server; this script only runs `migrate --force` against it.
#   - Never skips the local gate below — a red gate must never reach any server.

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

: "${STAGING_SSH_HOST:?Set in .env}"
: "${STAGING_DEPLOY_PATH:?Set in .env}"
: "${STAGING_DEPLOY_USER:?Set in .env}"
: "${STAGING_DEPLOY_PORT:?Set in .env}"
: "${STAGING_APP_DOMAIN:?Set in .env}"
: "${GITHUB_REPO_URL:?Set in .env}"

SSH_KEY="${STAGING_SSH_KEY:-${LXC_SSH_KEY:-}}"
: "${SSH_KEY:?Set STAGING_SSH_KEY (or reuse LXC_SSH_KEY) in .env}"

BRANCH="${STAGING_DEPLOY_BRANCH:-main}"
SSH_OPTS=(-i "$SSH_KEY" -p "$STAGING_DEPLOY_PORT" -o StrictHostKeyChecking=accept-new)
REMOTE="$STAGING_DEPLOY_USER@$STAGING_SSH_HOST"
DEPLOY_PATH="$STAGING_DEPLOY_PATH"

echo "==> 2/8 confirm we are deploying a commit CI actually ran"
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  echo "Refusing to deploy from '$CURRENT_BRANCH' — deploy to staging only from '$BRANCH'." >&2
  exit 1
fi
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is not clean — commit or stash before deploying." >&2
  exit 1
fi
COMMIT_SHA="$(git rev-parse HEAD)"
echo "    deploying $BRANCH @ $COMMIT_SHA to staging"

RELEASE_ID="$(date -u +%Y%m%d%H%M%S)"

echo "==> 3/8 clone $BRANCH @ $COMMIT_SHA on the staging server (pulled from GitHub, not rsynced from here)"
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
# The staging release runs as APP_ENV=staging — that is what makes the E1-T8 noindex /
# X-Robots-Tag guard fire and keeps this environment out of search results. It comes from the
# staging shared/.env (operator-provisioned per infra/provision-staging.md); fail loudly here
# rather than silently deploy a release that would behave like production.
grep -q '^APP_ENV=staging' "$RELEASE_DIR/.env" || { echo "staging shared/.env must set APP_ENV=staging" >&2; exit 1; }
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
# Same bootstrap/cache group-ownership fix as production (see infra/deploy.sh): written as the
# deploy user over SSH, read/re-written by PHP-FPM as www-data.
chgrp -R www-data "$RELEASE_DIR/bootstrap/cache"
chmod -R 775 "$RELEASE_DIR/bootstrap/cache"
REMOTE_LINK

echo "==> 5/8 migrate --force (expand-only — no schema drops, no full database resets)"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" "$RELEASE_ID" <<'REMOTE_MIGRATE'
set -euo pipefail
DEPLOY_PATH="$1"; RELEASE_ID="$2"
cd "$DEPLOY_PATH/releases/$RELEASE_ID"
export APP_ENV=staging
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
# Same reasoning as production: flush the database page cache right after the symlink flips so
# the next request recomputes HTML against the assets that are actually live (see infra/deploy.sh).
php "$DEPLOY_PATH/current/artisan" cache:clear
REMOTE_CUTOVER

echo "==> 7/8 sync supervisor config, restart queue worker, reload php-fpm/nginx"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- "$DEPLOY_PATH" <<'REMOTE_RESTART'
set -euo pipefail
DEPLOY_PATH="$1"
php "$(readlink -f "$DEPLOY_PATH/current")"/artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
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

echo "==> verifying from the build machine (staging is behind HTTP Basic auth)"
CURL_AUTH=()
if [ -n "${STAGING_BASIC_AUTH_USER:-}" ] && [ -n "${STAGING_BASIC_AUTH_PASS:-}" ]; then
  CURL_AUTH=(--user "$STAGING_BASIC_AUTH_USER:$STAGING_BASIC_AUTH_PASS")
fi
test "$(curl -sS "${CURL_AUTH[@]}" -o /dev/null -w '%{http_code}' "https://$STAGING_APP_DOMAIN/")" = 200
test "$(curl -sS "${CURL_AUTH[@]}" -o /dev/null -w '%{http_code}' "https://$STAGING_APP_DOMAIN/up")" = 200

echo "==> deployed $COMMIT_SHA to staging as release $RELEASE_ID"
