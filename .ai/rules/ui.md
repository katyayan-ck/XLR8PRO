---
description: Blade/CSS/JS standards for the Backpack/Tabler admin. Load when touching views or front-end assets.
paths:
  - resources/views/**
  - resources/js/**
  - resources/css/**
  - public/css/**
  - public/js/**
  - resources/lang/**
---

# UI standards (Track A — Backpack + Tabler; Track B uses Filament)

- Minimal, high-density Tabler style; one shared look for cards, page shells and AG-Grid tables —
  converge a screen to the shared pattern when you touch it.
- **Colours:** Tabler/Bootstrap tokens (`var(--tblr-*)`, `bg-body`, `text-body-secondary`) — never hardcoded
  hex / `bg-white` (breaks dark mode). Bootstrap **5** classes only (`ms-/me-`, `float-end`, `mb-3` not `form-group`).
- **AG-Grid:** include `public/css/ag-grid-tabler-theme.css` on every grid page; pin library versions
  (no unversioned CDN URLs); don't copy-paste export code — reuse the shared helper when one exists.
- **Dates:** display via `@sitedate(...)` / `site_date()`; flatpickr `dateFormat` from `SITE_DATE_FORMAT`.
- **Labels & validation names:** `resources/lang/en/{module}.php`, used via `__('module.fields.x')` — never re-typed.
- **Accessibility:** every input has a `<label for>`; buttons have text or `aria-label`; images have `alt`.
- No new front-end packages without approval; no `console.log`/`alert()` left in code.
- Before shipping a visual change, re-verify the screen's validation, AJAX calls and JS still work
  (HTTP smoke + manual check of the changed flow).
- Git history is the backup for replaced views; delete orphans instead of copying them to backup folders.
