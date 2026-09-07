#!/usr/bin/env bash
# infra/provision.sh — one-time setup for a fresh Debian 13 ("trixie") LXC.
# Run ONCE, as root, on the LXC itself (not the build machine):
#   ssh root@<lxc-ip> 'bash -s' < infra/provision.sh
#
# What this does NOT do, on purpose:
#   - Does NOT install PostgreSQL. This project's database is a separate, already-running
#     server (see .env's DB_HOST) — the LXC is web/app tier only.
#   - Does NOT install Redis. Queue/cache/session all use Laravel's database driver.
#   - Does NOT clone the site or run migrations — that is infra/deploy.sh's job, run
#     afterwards from the build machine.
#
# Safe to re-run: every step below is idempotent (apt install is a no-op on an installed
# package, mkdir -p never fails on an existing dir, the nginx vhost is overwritten with the
# same content, systemctl enable/restart are both idempotent).

set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/miautrix}"
APP_DOMAIN="${APP_DOMAIN:?Set APP_DOMAIN before running (the production hostname, no scheme)}"
DEPLOY_PUBKEY="${DEPLOY_PUBKEY:-}"   # optional: an SSH public key to authorize for $DEPLOY_USER

echo "==> apt update/upgrade"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo "==> installing nginx, PHP 8.4-FPM + required extensions, Node 24 LTS, git, composer, ufw"
apt-get install -y \
  nginx \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache \
  git unzip curl ufw ca-certificates gnupg

# Composer (official installer, checksum-verified)
if ! command -v composer >/dev/null 2>&1; then
  echo "==> installing Composer"
  EXPECTED_SIG="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL -o /tmp/composer-setup.php https://getcomposer.org/installer
  ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  if [ "$EXPECTED_SIG" != "$ACTUAL_SIG" ]; then
    echo "Composer installer signature mismatch — aborting" >&2
    exit 1
  fi
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

# Node 24 LTS (NodeSource) — needed on the server only for `npm run build` during deploy;
# if you prefer to build assets on the build machine and rsync public/build/, this step
# (and the nodejs package) can be dropped.
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | sed 's/^v//;s/\..*//')" -lt 24 ]; then
  echo "==> installing Node.js 24 LTS"
  curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
  apt-get install -y nodejs
fi

echo "==> deploy user"
if ! id -u "$DEPLOY_USER" >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"

if [ -n "$DEPLOY_PUBKEY" ]; then
  install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
  AUTH_KEYS="/home/$DEPLOY_USER/.ssh/authorized_keys"
  touch "$AUTH_KEYS"
  grep -qxF "$DEPLOY_PUBKEY" "$AUTH_KEYS" || echo "$DEPLOY_PUBKEY" >> "$AUTH_KEYS"
  chmod 600 "$AUTH_KEYS"
  chown "$DEPLOY_USER:$DEPLOY_USER" "$AUTH_KEYS"
fi

echo "==> firewall (80, 443, 22 only)"
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> release directory structure"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" \
  "$DEPLOY_PATH" \
  "$DEPLOY_PATH/releases" \
  "$DEPLOY_PATH/shared" \
  "$DEPLOY_PATH/shared/storage" \
  "$DEPLOY_PATH/shared/storage/app" \
  "$DEPLOY_PATH/shared/storage/app/private-media" \
  "$DEPLOY_PATH/shared/storage/framework/cache" \
  "$DEPLOY_PATH/shared/storage/framework/sessions" \
  "$DEPLOY_PATH/shared/storage/framework/views" \
  "$DEPLOY_PATH/shared/storage/logs" \
  "$DEPLOY_PATH/bin"

echo "==> rollback.sh (repoints 'current' at the previous release — never reverses a migration)"
cat > "$DEPLOY_PATH/bin/rollback.sh" <<'ROLLBACK'
#!/usr/bin/env bash
set -euo pipefail
DEPLOY_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DEPLOY_PATH/releases"
CURRENT_TARGET="$(readlink -f "$DEPLOY_PATH/current" || true)"
PREVIOUS="$(ls -1dt "$DEPLOY_PATH"/releases/*/ 2>/dev/null | grep -vF "$CURRENT_TARGET" | head -n1 || true)"
if [ -z "$PREVIOUS" ]; then
  echo "No previous release to roll back to." >&2
  exit 1
fi
ln -sfn "${PREVIOUS%/}" "$DEPLOY_PATH/current"
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart queue-worker || true
fi
echo "Rolled back to: ${PREVIOUS%/}"
ROLLBACK
chmod +x "$DEPLOY_PATH/bin/rollback.sh"
chown "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_PATH/bin/rollback.sh"

echo "==> nginx vhost for ${APP_DOMAIN}"
cat > "/etc/nginx/sites-available/miautrix" <<NGINX
server {
    listen 80;
    server_name ${APP_DOMAIN};
    root ${DEPLOY_PATH}/current/public;

    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/miautrix /etc/nginx/sites-enabled/miautrix
rm -f /etc/nginx/sites-enabled/default

echo "==> enabling services"
systemctl enable --now php8.4-fpm
nginx -t
systemctl enable --now nginx

echo "==> done. Next: point DNS/Cloudflare at this host, obtain a TLS cert (e.g. certbot),"
echo "    then run infra/deploy.sh from the build machine for the first real release."
