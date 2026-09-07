# miautrix-website — agent instructions

Self-hosted IT portfolio platform: a public Blade/Tailwind site backed by a Filament 5 admin CMS,
on Laravel 13 / PHP 8.4+ / PostgreSQL 18 / Redis 8. One administrator, no public signup.

## Commands

| Task | Command |
|---|---|
| Local services up | `docker compose up -d --wait` |
| Install | `composer install` · `npm ci` |
| Dev server | `php artisan serve` — http://127.0.0.1:8000 |
| Build | `npm run build` |
| Format check | `./vendor/bin/pint --test` |
| Static analysis | `./vendor/bin/phpstan analyse` |
| Tests | `./vendor/bin/pest` · one file: `./vendor/bin/pest tests/Feature/X.php` |
| Migrate / seed | `php artisan migrate --force` · `php artisan db:seed --force` |

**Gate:** `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest && npm run build`

## Non-negotiable

1. Never commit `.env`, a key, a token, or any real credential.
2. Never run `php artisan migrate:fresh` outside a local reset — never in a deploy script.
3. Never accept SVG uploads. Allowed types: JPEG, PNG, WebP, PDF, DOCX, ZIP.
4. Never expose Telescope outside `local`, and never remove the MFA requirement on `/admin`.
5. Validation lives in FormRequests, authorization in Policies — never inline in a controller.
6. Every model declares `$fillable` explicitly; `$guarded = []` is banned.
7. Icons are Heroicons or Lucide SVG. Never emoji.
8. Never mark a task done with a failing gate command.

PHP 8.4 is a hard floor (Pest 5 requires it). Filament 5 and Livewire 4 are upgraded together, never
one alone.

Full architecture, boundaries, design tokens and team workflow: see `CLAUDE.md` in this directory.
Build order: `blueprints/miautrix-website/tasks.json` and `blueprints/miautrix-website/epics/`.
