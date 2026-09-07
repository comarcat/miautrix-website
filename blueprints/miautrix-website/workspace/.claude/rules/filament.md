---
description: Filament admin panel, resource and authorization conventions
paths:
  - "app/Filament/**"
  - "app/Policies/**"
  - "app/Providers/Filament/**"
---

# Admin panel conventions

- **Generate, then edit.** Every resource starts as
  `php artisan make:filament-resource <Model> --generate`. Hand-writing a resource from scratch is the
  single largest avoidable cost in this build.
- One resource per model. Max 250 lines. Extract form and table schemas into
  `app/Filament/Resources/<Model>/Schemas/` when a resource grows past that.
- **Authorization is a Policy, never an inline check.** Filament calls the model's Policy
  automatically; if a resource needs a rule, the rule goes in the Policy so the public site and the
  API surface get it too.
- Every panel route is behind `auth` **and** `EnsureTwoFactorEnabled`. A resource may not opt out.
- Validation rules that also apply outside the panel live in the FormRequest; the Filament schema
  mirrors them, it does not own them.
- Destructive bulk actions must state the action and the selected count in the confirmation, and must
  soft-delete rather than hard-delete.
- Every mutation through the panel writes exactly one `audit_logs` row inside the same database
  transaction as the change, via the model observer — never from the resource class.
- Widgets query through a cached Action, not directly in the widget's `getData()`.
- Telescope is registered only when `app()->environment('local')` **and** `TELESCOPE_ENABLED=true`.
  Both conditions, always.
