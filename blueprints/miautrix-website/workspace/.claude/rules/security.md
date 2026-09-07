---
description: Authentication, uploads, headers, secrets and deployment safety rules
paths:
  - "app/Http/**"
  - "app/Rules/**"
  - "config/**"
  - "infra/**"
  - "scripts/**"
---

# Security conventions

- **MFA is mandatory, not optional.** TOTP enrolment plus recovery codes is required for the single
  administrator; `EnsureTwoFactorEnabled` gates every `/admin` route. Recovery codes are displayed
  exactly once at enrolment and their storage location is documented in `docs/runbook.md`. There is no
  other account-recovery path — that residual risk is accepted on record.
- Login is rate limited (5 attempts per minute per IP **and** per email), sessions expire after
  `SESSION_LIFETIME` minutes of inactivity, and every login attempt — success or failure — writes an
  `audit_logs` row with the actor, IP and user agent.
- **Uploads: SVG is never allowed.** The permitted set is JPEG, PNG, WebP, PDF, DOCX, ZIP. Every upload
  is validated on the client-declared extension **and** the sniffed MIME type; raster images are
  re-encoded through the image pipeline; the stored filename is generated, never taken from the
  upload. Files live outside the web root on the `media_private` disk and are served only through
  `MediaController`, which re-checks authorization and sets
  `Content-Disposition` and `X-Content-Type-Options: nosniff`.
- Security headers are set in one place, `app/Http/Middleware/SecurityHeaders.php`:
  `Content-Security-Policy` (no `unsafe-inline` for `script-src`; nonce-based),
  `Strict-Transport-Security: max-age=31536000; includeSubDomains`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`,
  `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- The contact form collects the minimum viable fields, carries a honeypot and a rate limit, stores no
  analytics cookie, embeds no third party, and states its retention period in the privacy notice.
- **Secrets never appear in the repository, a log line, or a rendered page.** Shell scripts load them
  with `set -a; . ./.env; set +a` and must never echo a variable that holds one.
- `infra/deploy.sh` runs `config:cache route:cache view:cache`, `migrate --force` and a queue restart.
  It must never contain `migrate:fresh`, `db:wipe`, or any command that drops data.
- PostgreSQL and Redis bind to `127.0.0.1` on the production LXC. UFW allows only 80/tcp, 443/tcp and
  the restricted SSH port.
