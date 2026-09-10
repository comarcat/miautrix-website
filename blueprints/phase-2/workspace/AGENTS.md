<!--
  PHASE 2 — MERGE BLOCK, NOT A REPLACEMENT.

  The target repo already has AGENTS.md. Do NOT overwrite it. APPEND the section below
  ("## Phase 2") to the end of the repo's existing AGENTS.md. Bootstrap's guarded
  `rsync --ignore-existing` will not copy this file over the existing one.
-->

## Phase 2

Phase 2 is an additive change to the live site. Build order: `blueprints/phase-2/tasks.json`;
detail: `blueprints/phase-2/epics/`. Checkpoint tags are `p2-step-01` … `p2-step-48`.

**Release flow (changed this phase):** implement → `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && npm run build && ./vendor/bin/pest`
→ PR → CI check `ci` green → merge → `bash infra/deploy-staging.sh` → **owner reviews on
`staging.miautrix.tech` and writes `review/<id>.md` = `APROBADO`** → `bash infra/deploy.sh`
(production) → live verify. No merged change reaches production without `APROBADO`. `infra/deploy.sh`
is unchanged.

**Three Phase-2 non-negotiables (in addition to the repo's existing list):**

1. The three `config/site.php` flags (`themes.dynamic`, `analytics.record_page_views`,
   `csp.youtube_on_life`) default OFF and are flipped ON only in their epic's final task. A flag OFF
   must leave the site byte-identical to before Phase 2.
2. The GeoLite2 `.mmdb` is an un-committed operator prerequisite; `storage/app/geoip/` is git-ignored;
   every geo consumer (`GeoLocator`, `whoami`, `tool_downloads`, `page_views`) is null-safe without
   it, and **no test may require it**.
3. The Life channel never appears in `/feed.xml`; there is no Life feed. `/life` is theme-gated (404
   unless the active theme's `shows_life_blog` is true). No `mac` field in `whoami`. No third-party
   analytics or tracking JS anywhere.

Full Phase-2 governance, feature flags, and conventions: see `CLAUDE.md` in this directory.
