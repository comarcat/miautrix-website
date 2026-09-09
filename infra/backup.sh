#!/usr/bin/env bash
# infra/backup.sh — dumps the production PostgreSQL database, and (with
# --restore-to-scratch-and-verify) proves the dump is actually restorable by restoring it into
# a scratch database and comparing every table's row count against production (§9 step 27).
#
# Usage:
#   bash infra/backup.sh                              # just dump, write to storage/backups/
#   bash infra/backup.sh --restore-to-scratch-and-verify   # dump + restore drill + row-count check
#
# Reads connection info from .env (DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD) the
# same way infra/deploy.sh does. Runs the actual pg_dump/psql work wherever those binaries are
# on PATH; if neither is available on this machine (the build machine deliberately has no
# PostgreSQL client installed — see infra/provision.sh's own "does NOT install PostgreSQL"
# note, which is about the LXC being app-tier-only, not about client tools), it re-executes
# itself over SSH on the LXC, which does have them (provision.sh installs postgresql-client)
# and already has network access to the DB server.
#
# A scratch database, never production, is what gets dropped: DROP DATABASE only ever targets
# $SCRATCH_DB, which is a name this script invents and owns end to end.

set -euo pipefail

MODE="${1:-dump}"

# Whitelist, not a passthrough — MODE ends up interpolated into a remote SSH command below,
# and this is the only guard against it ever carrying anything but one of these two literal
# values (security-auditor finding).
case "$MODE" in
    dump|--restore-to-scratch-and-verify) ;;
    *)
        echo "Unknown mode: ${MODE} (expected 'dump' or '--restore-to-scratch-and-verify')" >&2
        exit 1
        ;;
esac

# A dump this script writes is a full snapshot of production data — never left
# group/world-readable regardless of the caller's shell umask (security-auditor finding).
umask 077

echo "==> loading .env"
set -a
# shellcheck disable=SC1091
. ./.env
set +a

: "${DB_HOST:?Set in .env}"
: "${DB_PORT:?Set in .env}"
: "${DB_DATABASE:?Set in .env}"
: "${DB_USERNAME:?Set in .env}"
: "${DB_PASSWORD:?Set in .env}"

BACKUP_DIR="storage/backups"
STAMP="$(date -u +%Y%m%d%H%M%S)"
DUMP_FILE="${BACKUP_DIR}/${DB_DATABASE}-${STAMP}.sql"
SCRATCH_DB="${DB_DATABASE}_backup_verify"

run_locally() {
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"

    echo "==> 1/1 pg_dump ${DB_DATABASE} -> ${DUMP_FILE}"
    PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
        --no-owner --no-privileges "$DB_DATABASE" > "$DUMP_FILE"
    chmod 600 "$DUMP_FILE"

    if [ "$MODE" != "--restore-to-scratch-and-verify" ]; then
        echo "==> dump written to ${DUMP_FILE}"
        return 0
    fi

    echo "==> restore drill: creating scratch database ${SCRATCH_DB}"
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres \
        -c "DROP DATABASE IF EXISTS ${SCRATCH_DB};" \
        -c "CREATE DATABASE ${SCRATCH_DB};"

    echo "==> restoring dump into ${SCRATCH_DB}"
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$SCRATCH_DB" \
        -v ON_ERROR_STOP=1 -q -f "$DUMP_FILE"

    echo "==> comparing per-table row counts"
    TABLES="$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
        -t -A -c "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;")"

    MISMATCH=0
    for TABLE in $TABLES; do
        PROD_COUNT="$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
            -t -A -c "SELECT count(*) FROM \"${TABLE}\";")"
        SCRATCH_COUNT="$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$SCRATCH_DB" \
            -t -A -c "SELECT count(*) FROM \"${TABLE}\";")"

        if [ "$PROD_COUNT" != "$SCRATCH_COUNT" ]; then
            echo "    MISMATCH ${TABLE}: production=${PROD_COUNT} scratch=${SCRATCH_COUNT}" >&2
            MISMATCH=1
        else
            echo "    OK ${TABLE}: ${PROD_COUNT} rows"
        fi
    done

    echo "==> dropping scratch database ${SCRATCH_DB}"
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres \
        -c "DROP DATABASE IF EXISTS ${SCRATCH_DB};"

    if [ "$MISMATCH" -ne 0 ]; then
        echo "==> restore drill FAILED — row counts did not match for every table" >&2
        exit 1
    fi

    echo "==> restore drill passed — every table's row count matched production"
}

if command -v pg_dump >/dev/null 2>&1 && command -v psql >/dev/null 2>&1; then
    run_locally
    exit 0
fi

echo "==> pg_dump/psql not found locally — delegating to the LXC over SSH"
: "${LXC_HOST:?Set in .env}"
: "${LXC_SSH_KEY:?Set in .env}"
: "${DEPLOY_PATH:?Set in .env}"
: "${DEPLOY_USER:?Set in .env}"
: "${DEPLOY_PORT:?Set in .env}"

# Same SSH identity infra/deploy.sh uses ($DEPLOY_USER, never root) — the LXC's own copy of
# this script (deployed alongside the app) reads the same shared .env, so no connection
# details are re-transmitted over the wire here, only the mode flag is.
ssh -i "$LXC_SSH_KEY" -p "$DEPLOY_PORT" -o StrictHostKeyChecking=accept-new "${DEPLOY_USER}@${LXC_HOST}" \
    "cd $(printf '%q' "$DEPLOY_PATH")/current && bash infra/backup.sh $(printf '%q' "$MODE")"
