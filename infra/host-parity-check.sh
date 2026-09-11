#!/usr/bin/env bash
# infra/host-parity-check.sh — the §9.1 parity harness for the www ↔ apex canonicalisation.
#
# For every listed public path, fetch it from BOTH the apex and the `www.` host, strip the
# per-request bits that legitimately differ (the CSP nonce and the CSRF token), and `diff`
# the rest. Any difference means the two hosts are not serving identical content and the
# Cloudflare 301 Redirect Rule (infra/provision-staging.md step 7) must NOT be applied yet.
#
# The sponsor runs this on three consecutive days; three clean runs over >=48h is the gate
# for applying the redirect. It reads reality against reality — it never compares to a stored
# golden file.
#
# Usage:
#   CANONICAL_HOST=miautrix.tech bash infra/host-parity-check.sh
#   bash infra/host-parity-check.sh            # falls back to CANONICAL_HOST from ./.env, else miautrix.tech
#
# Optional: BASIC_AUTH_USER / BASIC_AUTH_PASS (for a host still behind HTTP Basic auth).
#
# Exit: 0 = every path matched on both hosts; 1 = at least one diff (paths listed).

set -uo pipefail

# --- resolve the two hosts ------------------------------------------------------------------
if [ -z "${CANONICAL_HOST:-}" ] && [ -f ./.env ]; then
  CANONICAL_HOST="$(grep -E '^CANONICAL_HOST=' ./.env | head -n1 | cut -d= -f2- | tr -d '"'"'"'')"
fi
APEX_HOST="${CANONICAL_HOST:-miautrix.tech}"
# The non-canonical sibling: add or drop the leading "www." from the apex.
case "$APEX_HOST" in
  www.*) WWW_HOST="${APEX_HOST#www.}" ;;
  *)     WWW_HOST="www.$APEX_HOST" ;;
esac

CURL_AUTH=()
if [ -n "${BASIC_AUTH_USER:-}" ] && [ -n "${BASIC_AUTH_PASS:-}" ]; then
  CURL_AUTH=(--user "$BASIC_AUTH_USER:$BASIC_AUTH_PASS")
fi

# --- the public paths that must be byte-identical on both hosts ---------------------------
PATHS=(
  /
  /about
  /experience
  /skills
  /projects
  /resume
  /blog
  /connect
  /contact
  /robots.txt
  /feed.xml
  /sitemap.xml
)

# --- strip the bits that legitimately vary per request -----------------------------------
# 1. CSP nonce:            nonce="RANDOM"                -> nonce="__NONCE__"
# 2. Livewire/CSRF token:  <meta name="csrf-token" ...> -> __CSRF__
#                          name="_token" value="RANDOM" -> value="__CSRF__"
#                          "csrfToken":"RANDOM"          -> "csrfToken":"__CSRF__"
# 3. wire:id / wire:snapshot checksums also churn per request.
normalise() {
  sed -E \
    -e 's/nonce="[^"]*"/nonce="__NONCE__"/g' \
    -e 's#<meta name="csrf-token"[^>]*>#<meta name="csrf-token" content="__CSRF__">#g' \
    -e 's/name="_token" value="[^"]*"/name="_token" value="__CSRF__"/g' \
    -e 's/"csrfToken":"[^"]*"/"csrfToken":"__CSRF__"/g' \
    -e 's/wire:id="[^"]*"/wire:id="__WIRE__"/g' \
    -e 's/wire:snapshot="[^"]*"/wire:snapshot="__WIRE__"/g' \
    -e 's/data-csrf="[^"]*"/data-csrf="__CSRF__"/g'
}

fetch() {
  # $1 = host, $2 = path
  curl -sS "${CURL_AUTH[@]}" -H "Accept: text/html" "https://$1$2"
}

fail=0
for p in "${PATHS[@]}"; do
  a="$(fetch "$APEX_HOST" "$p" | normalise)"
  w="$(fetch "$WWW_HOST"  "$p" | normalise)"
  if [ "$a" = "$w" ]; then
    printf '  OK   %s\n' "$p"
  else
    printf '  DIFF %s\n' "$p"
    diff <(printf '%s\n' "$a") <(printf '%s\n' "$w") | head -n 20
    fail=1
  fi
done

if [ "$fail" -eq 0 ]; then
  echo "host parity: 0 diffs across ${#PATHS[@]} paths ($APEX_HOST vs $WWW_HOST)"
  exit 0
fi

echo "host parity: DIFFERENCES found ($APEX_HOST vs $WWW_HOST) — do NOT apply the 301 rule" >&2
exit 1
