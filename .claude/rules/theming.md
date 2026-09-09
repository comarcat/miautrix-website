---
description: Design token and shared-component conventions for the Console and Matrix themes
paths:
  - "resources/css/**"
  - "resources/views/components/**"
  - "app/View/Components/**"
---

# Theming conventions

- **One component tree, never two.** Both themes render the exact same Blade files under
  `resources/views/components/`. A theme difference is a `[data-theme]` CSS override, never a
  parallel `themes/matrix/` or `themes/technical/` directory.
- **Every color in a component is a `--color-*` custom property.** No raw hex value, no Tailwind
  arbitrary color value (`bg-[#00ff41]`), ever, in a shared component.
- **Theme A ("Console" — the internal cookie/data-theme value is still `technical`, kept for
  cache-key/backward-compat reasons; only the user-facing label changed) respects
  `prefers-color-scheme`** and can be manually overridden. Its tokens live on `:root` and under
  `@media (prefers-color-scheme: dark)`; its `--font-sans` is Courier New, overridden explicitly
  back to IBM Plex Sans inside `[data-theme='matrix']` so the two don't leak into each other.
- **Theme B ("Matrix") is always dark and overrides system preference entirely.** Its tokens live
  under `[data-theme="matrix"]`. The theme switcher UI must make this override visible to the
  visitor — never a silent surprise.
- **`#00FF41` (Matrix's accent) never colors full-paragraph body text.** It is for accents, borders,
  headers, links, and focus rings only. Body copy always uses `--color-foreground`.
- **The Matrix destructive pairing (`--color-destructive` on `--color-on-destructive`) is flagged
  for a manual render check** (blueprint §7, §15) — do not assume the token math alone clears WCAG
  AA at normal text size; verify at ≥18px/bold or paired with an icon.
- **The theme cookie (`miautrix_theme`) is read server-side before the first byte renders.** A
  client-only theme toggle that causes a flash on load is a defect, not an acceptable trade-off.
- **Focus rings use `--color-ring` and are never removed**, including in Matrix, where the ring must
  stay visible against `#000000`.
- Motion respects `prefers-reduced-motion: reduce` — scroll-reveal renders its final state
  immediately when the user has that preference set.
