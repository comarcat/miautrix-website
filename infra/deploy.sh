#!/usr/bin/env bash
# infra/deploy.sh — release miautrix-website to the production Debian 13 LXC.
#
# Run from the BUILD MACHINE (not the server). Requires infra/provision.sh to have already
# run once on the target LXC. Pulls the release straight from GitHub on the server itself —
# nothing is rsynced from this machine except the SSH command that triggers it.
#
# Usage:
#   set -a; . ./.env; set +a
#   bash infra/deploy.sh
#
# Reads from .env: LXC_HOST, LXC_SSH_KEY, DEPLOY_PATH, DEPLOY_USER, DEPLOY_PORT,
# GITHUB_REPO_URL, APP_DOMAIN.
#
# What this does NOT do:
#   - Never `php artisan migrate:fresh` or `db:wipe` — migrations are expand-only. A
#     destructive schema change ships in a LATER release, never alongside the code that
#     stops needing the old column.
#   - Never installs or touches PostgreSQL — the database is a separate, already-running
#     server; this script only runs `migrate --force` against it.
#   - Never skips the local gate below — a red gate must never reach the server.

set -euo pipefail

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

echo "==> 1/8 local gate must be green before anything touches the server"
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest
npm run build

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
REMOTE_LINK

echo "==> 5/8 migrate --force (expand-only — never :fresh, never db:wipe)"
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
REMOTE_CUTOVER

echo "==> 7/8 restart queue worker + reload php-fpm/nginx"
# shellcheck disable=SC2087
ssh "${SSH_OPTS[@]}" "$REMOTE" bash -s -- <<'REMOTE_RESTART'
set -euo pipefail
php "$(readlink -f /var/www/miautrix/current)"/artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
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
set -a; . ./.env; set +a
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/")" = 200
test "$(curl -sS -o /dev/null -w '%{http_code}' "https://$APP_DOMAIN/up")" = 200
curl -sS "https://$APP_DOMAIN/up" | jq -e '.database == "ok"'

echo "==> deployed $COMMIT_SHA as release $RELEASE_ID"
