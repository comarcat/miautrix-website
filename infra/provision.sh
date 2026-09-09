#!/usr/bin/env bash
# infra/provision.sh — one-time setup for a fresh Debian 13 ("trixie") LXC.
# Run ONCE, as root, on the LXC itself (not the build machine):
#   ssh root@<lxc-ip> 'bash -s' < infra/provision.sh
#
# Prefer running it as a transient systemd unit instead of directly over the SSH session —
# many systemd-logind configurations kill all of a user's processes when their login session
# ends (KillUserProcesses), which SIGHUPs a plain `nohup ... &` mid-install if the client
# disconnects. A transient unit survives that:
#   scp infra/provision.sh root@<lxc-ip>:/root/provision.sh
#   ssh root@<lxc-ip> "systemd-run --unit=provision --collect \
#     --setenv=APP_DOMAIN=<domain> --setenv=DEPLOY_PUBKEY='<pubkey>' \
#     bash -c 'bash /root/provision.sh > /root/provision.log 2>&1'"
#   ssh root@<lxc-ip> "journalctl -u provision.service --no-pager"   # check status/log after
#
# What this does NOT do, on purpose:
#   - Does NOT install the PostgreSQL *server*. This project's database is a separate,
#     already-running server (see .env's DB_HOST) — the LXC is web/app tier only. It does
#     install postgresql-client (§9 step 27) — infra/backup.sh needs pg_dump/psql to reach
#     that remote server for the backup/restore drill, and this LXC has network access to it
#     already; the build machine deliberately does not.
#   - Does NOT install Redis. Queue/cache/session all use Laravel's database driver.
#   - Does NOT clone the site or run migrations — that is infra/deploy.sh's job, run
#     afterwards from the build machine.
#
# Safe to re-run: every step below is idempotent (apt install is a no-op on an installed
# package, mkdir -p never fails on an existing dir, the nginx vhost is overwritten with the
# same content, systemctl enable/restart are both idempotent).

set -euo pipefail

# Never rely on an interactive login shell's environment — this script is also meant to run
# under `systemd-run` (so a client-side SSH disconnect can't SIGHUP-kill a long apt-get/composer
# install via systemd-logind's session cleanup), and transient units set neither HOME nor
# COMPOSER_HOME. Composer refuses to run without one. Set it explicitly and unconditionally.
export HOME="${HOME:-/root}"
export COMPOSER_HOME="${COMPOSER_HOME:-$HOME/.composer}"

DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/miautrix}"
APP_DOMAIN="${APP_DOMAIN:?Set APP_DOMAIN before running (the production hostname, no scheme)}"
DEPLOY_PUBKEY="${DEPLOY_PUBKEY:-}"   # optional: an SSH public key to authorize for $DEPLOY_USER

echo "==> apt update/upgrade"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo "==> installing nginx, PHP 8.4-FPM + required extensions, Node 24 LTS, git, composer, ufw, supervisor, postgresql-client"
apt-get install -y \
  nginx \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache \
  git unzip curl ufw ca-certificates gnupg supervisor \
  postgresql-client

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

# A fixed-path, no-argument wrapper (not a raw `sudo cp`) so the sudoers grant below can be
# exact rather than a blanket "deploy may cp anything anywhere as root" — that would let a
# compromised deploy key overwrite any file on the box, not just this one config.
cat > /usr/local/bin/miautrix-sync-queue-supervisor.sh <<'SYNC_SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
cp /var/www/miautrix/current/infra/supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf
supervisorctl reread
supervisorctl update
SYNC_SCRIPT
chmod 755 /usr/local/bin/miautrix-sync-queue-supervisor.sh

# infra/deploy.sh's REMOTE_RESTART step runs these as $DEPLOY_USER via sudo — grant exactly
# these, passwordless, nothing broader. A blanket NOPASSWD:ALL would let a compromised deploy
# key do anything root can; this scopes it to only what deploy actually needs.
cat > /etc/sudoers.d/deploy-reload <<SUDOERS
${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm, /usr/bin/systemctl reload nginx, /usr/bin/supervisorctl restart queue-worker, /usr/local/bin/miautrix-sync-queue-supervisor.sh
SUDOERS
chmod 440 /etc/sudoers.d/deploy-reload
visudo -cf /etc/sudoers.d/deploy-reload

echo "==> firewall (80, 443, 22 only)"
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> release directory structure"
# storage/ is owned by $DEPLOY_USER but GROUP-writable by www-data: PHP-FPM runs as www-data
# (Laravel writes sessions/cache/logs at request time), while $DEPLOY_USER (deploy-time,
# composer/artisan over SSH) is already a member of the www-data group from the usermod call
# above. Owner-only 755 here was a real bug — PHP-FPM couldn't write anything, so every request
# 500'd with nothing in Laravel's own log (it couldn't write that either). setgid keeps new
# files/dirs created later inheriting the www-data group instead of whichever process made them.
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" \
  "$DEPLOY_PATH" \
  "$DEPLOY_PATH/releases" \
  "$DEPLOY_PATH/shared" \
  "$DEPLOY_PATH/bin"
install -d -o "$DEPLOY_USER" -g www-data -m 2775 \
  "$DEPLOY_PATH/shared/storage" \
  "$DEPLOY_PATH/shared/storage/app" \
  "$DEPLOY_PATH/shared/storage/app/private-media" \
  "$DEPLOY_PATH/shared/storage/framework" \
  "$DEPLOY_PATH/shared/storage/framework/cache" \
  "$DEPLOY_PATH/shared/storage/framework/sessions" \
  "$DEPLOY_PATH/shared/storage/framework/views" \
  "$DEPLOY_PATH/shared/storage/logs"

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
    server_name ${APP_DOMAIN} www.${APP_DOMAIN};
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

echo "==> supervisor config for the queue worker (§9 step 27) — a bootstrap placeholder only.
    provision.sh runs before any release is cloned, so there's nothing to copy the real file
    FROM yet; infra/deploy.sh overwrites this from the repo's own infra/supervisor/
    queue-worker.conf on every release, which is the actual source of truth from then on —
    kept in sync automatically instead of two hand-maintained copies drifting apart."
cat > "/etc/supervisor/conf.d/queue-worker.conf" <<SUPERVISOR
[program:queue-worker]
process_name=%(program_name)s
directory=${DEPLOY_PATH}/current
command=php ${DEPLOY_PATH}/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=${DEPLOY_PATH}/shared/storage/logs/queue-worker.log
stopwaitsecs=3600
SUPERVISOR

echo "==> enabling services"
systemctl enable --now php8.4-fpm
systemctl enable --now supervisor
nginx -t
systemctl enable --now nginx

# supervisor was possibly already running from a previous provision — reread/update rather
# than relying on `enable --now` alone to have picked up a conf.d file added just above.
supervisorctl reread
supervisorctl update

echo "==> done. Next: point DNS/Cloudflare at this host, obtain a TLS cert (e.g. certbot),"
echo "    then run infra/deploy.sh from the build machine for the first real release."
