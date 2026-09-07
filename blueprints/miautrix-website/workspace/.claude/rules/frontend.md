---
description: Blade, Tailwind v4, tokens, fonts, motion and accessibility conventions
paths:
  - "resources/**"
  - "public/**"
  - "vite.config.js"
---

# Frontend conventions

- **Tokens only.** No raw hex, no raw px, no arbitrary Tailwind values in a Blade file. Every colour
  resolves to a `--color-*` custom property defined in `resources/css/app.css`.
- The three-block theme pattern is mandatory and is the only correct one:
  `:root { … }`, then `@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { … } }`,
  then `:root[data-theme="dark"] { … }`. Never define a colour only inside a media block.
- On dark backgrounds, link and label text uses `--color-accent-text`, not `--color-accent`.
- **Tailwind v4 scans only what `@source` names.** `resources/css/app.css` declares
  `@source "../views";`, `@source "../js";` and `@source "../../app";` so the class scanner never
  walks `blueprints/`, `vendor/` or `node_modules/`.
- **Fonts are self-hosted.** `public/fonts/*.woff2`, `font-display: swap`, `<link rel="preload">` on
  the two above-the-fold weights. A request to `fonts.googleapis.com` or `fonts.gstatic.com` in any
  environment is a defect.
- **Icons are Heroicons or Lucide inline SVG. Emoji are never icons.**
- Motion: 200–350ms, ease-out, transform and opacity only. Scroll reveal is opacity + 12px translate
  driven by one shared IntersectionObserver in `resources/js/reveal.js`. No animation library.
  `@media (prefers-reduced-motion: reduce)` renders the final state with no transition.
- Accessibility is a build gate, not polish: one `<h1>` per page, landmarks on every layout, a visible
  focus ring on every focusable element, a programmatic label on every input (placeholder-only is a
  defect), errors as text beside the field, touch targets ≥44×44px, and no horizontal body scroll at
  375 / 768 / 1024 / 1440 px.
- Every list and async surface specifies all three of loading, empty and error. An entity with no
  published rows renders **nothing** — never a stub or placeholder card.
- Livewire: `wire:model.blur` by default; `wire:model.live` requires a comment justifying the cost.
  Derived data goes in a `#[Computed]` property, never in `render()`.
