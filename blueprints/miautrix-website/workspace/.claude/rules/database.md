---
description: Schema, migration, model and query conventions for miautrix-website
paths:
  - "database/**"
  - "app/Models/**"
---

# Data layer conventions

- **Never invent a migration filename.** Create migrations with `php artisan make:migration` and refer
  to them as "the migration that command emitted". The timestamp prefix is chosen by the tool.
- **Never edit a migration that has already run** on any environment. Add a new one.
- **`php artisan migrate:fresh` is a local reset only.** It must never appear in `infra/deploy.sh`,
  in CI, or in any script that can run against a non-local database.
- Every model sets `$fillable` explicitly. `$guarded = []` is banned — it is a mass-assignment hole.
- Every publishable content entity carries: `slug` (unique, indexed), `published` (boolean, indexed),
  `featured` (boolean), `sort_order` (integer), `seo_title`, `meta_description`, `canonical_url`,
  `og_title`, `og_description`, `og_image_id`, `created_at`/`updated_at`, and `deleted_at`.
- **Deletion policy.** Content entities soft-delete. Pivot rows cascade on delete of either parent.
  Media referenced by a *published* entity cannot be hard-deleted — the delete path must fail with
  `MediaStillReferencedException`. `audit_logs` are append-only: no update path, no delete path.
- Foreign keys are declared in the migration with an explicit `onDelete` behaviour. A relation with no
  stated cascade is a defect.
- Index every column a public page filters or orders by: `slug`, `published`, `sort_order`,
  and every foreign key.
- Prefer Eloquent. Drop to the query builder only for one measurably slow query, with a comment
  naming the measurement. **Do not build a repository layer.**
- Seeders must be idempotent enough to run twice: use `updateOrCreate` keyed on `slug`, never blind
  `create` in a seeder that ships with the app.
- Tests run against a real PostgreSQL 18 (`miautrix_test`), never SQLite — the schema uses
  PostgreSQL-specific constraints SQLite silently accepts.
