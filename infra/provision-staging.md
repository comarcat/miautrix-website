# Provisioning `staging.miautrix.tech`

**Operator checklist — run by a human, once, on the staging LXC.** Server provisioning is never an
autonomous task. This mirrors the shape of `infra/provision.sh` but as prose: do the steps in order,
then `bash infra/deploy-staging.sh` from the build machine takes over releases.

Staging exists for one reason: the Phase-2 release flow deploys `main` here for the sponsor to review
on `staging.miautrix.tech` **before** `bash infra/deploy.sh` promotes the same commit to production.
It must look and behave like production, with two differences — it is behind HTTP Basic auth and it
is `noindex`.

---

## 1. DNS

Add a DNS record for `staging.miautrix.tech` pointing at the staging LXC (proxied through Cloudflare,
same as the apex). Obtain a TLS cert for it (Cloudflare origin cert, or certbot on the box).

## 2. Nginx vhost + HTTP Basic auth

Create `/etc/nginx/sites-available/miautrix-staging` as a copy of the production
`/etc/nginx/sites-available/miautrix` vhost with:

- `server_name staging.miautrix.tech;`
- `root <STAGING_DEPLOY_PATH>/current/public;`
- **HTTP Basic auth on the whole server block**, so only the sponsor and the team can reach it:

  ```nginx
  auth_basic           "miautrix staging";
  auth_basic_user_file /etc/nginx/.htpasswd-staging;
  ```

  Create the password file (pick the user/pass, record them as `STAGING_BASIC_AUTH_USER` /
  `STAGING_BASIC_AUTH_PASS` in the build machine's `.env` so `deploy-staging.sh`'s verify step can
  authenticate):

  ```bash
  sudo apt-get install -y apache2-utils
  sudo htpasswd -c /etc/nginx/.htpasswd-staging <STAGING_BASIC_AUTH_USER>
  ```

- Everything else — the `location /`, `location ~ \.php$`, the `location ~ /\.(?!well-known).*` deny,
  the "security headers come from `App\Http\Middleware\SecurityHeaders`, not nginx" comment — is
  copied verbatim from production.

Enable it, drop the default, reload:

```bash
sudo ln -sf /etc/nginx/sites-available/miautrix-staging /etc/nginx/sites-enabled/miautrix-staging
sudo nginx -t && sudo systemctl reload nginx
```

## 3. Dedicated staging database — never production's

Create a **separate** PostgreSQL database and role for staging. It must not be the database
production uses (a task's local gate runs `php artisan migrate:fresh`, which drops every table —
sharing prod's database name means periodically wiping production; this bit the project once already,
2026-09-08).

```sql
CREATE ROLE miautrix_staging LOGIN PASSWORD '<pick-a-password>';
CREATE DATABASE miautrix_staging OWNER miautrix_staging;
```

## 4. `shared/.env` — `APP_ENV=staging` and the noindex behaviour

Lay out the release directory structure exactly as `infra/provision.sh` does
(`<STAGING_DEPLOY_PATH>/{releases,shared,current,bin}`, `shared/storage/...`). Then write
`<STAGING_DEPLOY_PATH>/shared/.env` from `.env.example` with:

- `APP_ENV=staging` — **required.** `App\Http\Middleware\SecurityHeaders` emits
  `X-Robots-Tag: noindex, nofollow` on every response when the environment is `staging` (E1-T8), so
  search engines never index this host. `deploy-staging.sh` refuses to deploy if this is not set.
- `APP_URL=https://staging.miautrix.tech`
- `CANONICAL_HOST=miautrix.tech` — **the same as production.** Staging still renders the production
  canonical link and shares the `.miautrix.tech` cookie domain; the noindex header is what keeps it
  out of the index, not a different canonical host.
- `DB_DATABASE=miautrix_staging`, `DB_USERNAME=miautrix_staging`, `DB_PASSWORD=<from step 3>`.
- `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` — set both (any `db:seed` aborts without them).
- The three Phase-2 flags default `false`; flip one to `true` on staging only to preview a
  feature-on state before its epic's final task lands.
- `STAGING_*` vars are read by `deploy-staging.sh` on the **build machine**, not here.

## 5. MaxMind GeoLite2 database

The GeoLite2 `.mmdb` is an un-committed per-environment prerequisite. Download `GeoLite2-City.mmdb`
(free MaxMind account + a license key) and place it at:

```
<STAGING_DEPLOY_PATH>/shared/storage/app/geoip/GeoLite2-City.mmdb
```

(`storage/` is symlinked into every release; `storage/app/geoip/` is git-ignored.) Do the same on
production. Every geo consumer is null-safe when the file is absent — the site works without it, it
just returns `null` for ISP/location.

## 6. Cloudflare bot-fight-mode exception for OG image URLs

Cloudflare's Bot Fight Mode / "Block AI bots" can stop Facebook/X/LinkedIn crawlers from fetching the
Open Graph preview image, which renders the share card blank (a real Phase-1 finding). Add a
**Cloudflare WAF custom rule** (or a Bot Fight Mode skip) that *allows* requests to the media path
used for OG images:

- Expression: `(http.request.uri.path contains "/media/") or (http.request.uri.path eq "/images/og-default.png")`
- Action: **Skip** → Bot Fight Mode (and Super Bot Fight Mode "Definitely automated" / "Verified bots"
  as needed), plus **Skip remaining custom rules**.

Apply this on **both** the apex zone and — if it is a separate zone — staging.

## 7. Cloudflare Redirect Rule — canonicalise the host (apply LAST, after 48h of parity)

`config('site.canonical_host')` is `miautrix.tech` (the apex), so the **non-canonical** host is
`www.miautrix.tech` and the rule 301s **www → apex**. Do **not** apply this until
`bash infra/host-parity-check.sh` has reported **0 diffs on three consecutive runs over ≥48h**
(blueprint §9.1). The kill switch is deleting this rule — it takes effect in seconds, no deploy.

**Cloudflare dashboard → Rules → Redirect Rules → Create rule:**

| Field | Value |
|---|---|
| Rule name | `www → apex canonical (301)` |
| When incoming requests match | Custom filter expression |
| Expression | `(http.host eq "www.miautrix.tech")` |
| Then | **Dynamic redirect** |
| Expression (target URL) | `concat("https://miautrix.tech", http.request.uri.path)` |
| Status code | `301` |
| Preserve query string | **On** |

Exact Terraform / API equivalent:

```
{
  "expression": "(http.host eq \"www.miautrix.tech\")",
  "action": "redirect",
  "action_parameters": {
    "from_value": {
      "status_code": 301,
      "target_url": { "expression": "concat(\"https://miautrix.tech\", http.request.uri.path)" },
      "preserve_query_string": true
    }
  }
}
```

**If `CANONICAL_HOST` is ever set to `www.miautrix.tech` instead**, invert the rule — the
**apex-to-www 301 Redirect Rule** is then:

| Field | Value |
|---|---|
| Rule name | `apex → www canonical (301)` |
| Expression | `(http.host eq "miautrix.tech")` |
| Then | **Dynamic redirect** |
| Expression (target URL) | `concat("https://www.miautrix.tech", http.request.uri.path)` |
| Status code | `301` |
| Preserve query string | **On** |

```
{
  "expression": "(http.host eq \"miautrix.tech\")",
  "action": "redirect",
  "action_parameters": {
    "from_value": {
      "status_code": 301,
      "target_url": { "expression": "concat(\"https://www.miautrix.tech\", http.request.uri.path)" },
      "preserve_query_string": true
    }
  }
}
```

## 8. Handover

Once steps 1–6 are done (7 is deliberately deferred), run from the build machine:

```bash
bash infra/deploy-staging.sh
```

and confirm `https://<STAGING_BASIC_AUTH_USER>:<pass>@staging.miautrix.tech/` returns `200` and the
response carries `X-Robots-Tag: noindex, nofollow`.
