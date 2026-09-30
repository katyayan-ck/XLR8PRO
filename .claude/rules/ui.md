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
- **AG-Grid:** never pass a `theme` option — the global `createGrid` hook (`header_metas`, DEC-067) gives every grid
  the Quartz Theming-API theme bound to Tabler tokens (mode / primary / font / radius follow live) and switches off
  legacy `styles/ag-theme-*.css`. New grids: pinned `ag-grid-community@36.2.0`, no legacy CSS. Pin library versions
  (no unversioned CDN URLs); don't copy-paste export code — reuse the shared helper when one exists.
- **Theme & shell (DEC-067):** Appearance panel (`inc/theme_settings`, `public/js/xl-theme.js`) sets colour mode,
  primary colour, base, font, radius (Tabler 1.4 `data-bs-theme-*`, per browser) and the menu layout (`xl_layout`
  cookie → `ApplyUiPreferences`). Style only with tokens so all of it reaches your screen; charts / canvas read colours
  via `XL.theme.token('--tblr-primary')` and rebuild on `XL.theme.onChange`. `public/css/xl-theme.css` holds shell,
  kit patterns (chat, KPI, kanban) and the dark-mode safety net for legacy markup — not a licence for new hex.
  Reference markup: dev UI kit `/admin/dev/ui` (local only, `XL_DEV_UI_KIT`). Charts: ApexCharts 3.54.1 via `@basset`.
- **Shared UI layer (DEC-066):** `public/js/xl-ui.js` + `public/css/xl-ui.css` load on every admin page and
  auto-enhance date inputs (flatpickr, site format), `select[multiple]` (Select2), file inputs (drop-zone) and bare
  tables (`.table-responsive`); opt out with `data-xl="off"`. In new code use the components:
  `<x-ui.date name=… :value=… [time]>`, `<x-ui.select name=… :options=… [multiple] [source=url]>`,
  `<x-ui.upload name=… [multiple] [:url=… :fields=… reload]>`; JS dates via `XL.formatDate(value, withTime)`;
  Blade dates via `@sitedate` / `site_date()` and `@sitedatetime` / `site_datetime()`. Libraries (flatpickr 4.6.13,
  Select2 4.1.0-rc.0) come only from `config/backpack/{ui,theme-tabler}.php` — never a per-view CDN tag.
- **Labels & validation names:** `resources/lang/en/{module}.php`, used via `__('module.fields.x')` — never re-typed.
- **Accessibility:** every input has a `<label for>`; buttons have text or `aria-label`; images have `alt`.
- No new front-end packages without approval; no `console.log`/`alert()` left in code.
- Before shipping a visual change, re-verify the screen's validation, AJAX calls and JS still work
  (HTTP smoke + manual check of the changed flow).
- Git history is the backup for replaced views; delete orphans instead of copying them to backup folders.

## Site date format everywhere (display, lists, pickers)
Every date/datetime shown or entered follows the site setting `display.date_format` (DateFormatService): Blade via `@sitedate`/`site_date()` (datetime = site date + time), grids/JS via the shared site-format helper, pickers via flatpickr with `dateFormat`/`altFormat` from the site format. Never hard-code `->format('d-m-Y')`, `toLocaleDateString`, or native `type="date|datetime-local"` inputs. Store/submit ISO (Y-m-d); only display follows the setting.

## Multi-selects use Select2 (shared include), never list boxes
Every multi-select (and any long single select) is a Select2 widget from the ONE shared, version-pinned include + initializer (not per-view CDN tags, not Backpack PRO select2 fields). Plain `<select multiple>` list boxes are not allowed. Searchable, with placeholder, clear button, Bootstrap 5/Tabler theme, and AJAX source for large lists (e.g. people pickers).

## File uploads use the shared drop-zone uploader
Every file upload uses the shared drop-zone component: drag-and-drop + click-to-browse, thumbnail/icon preview per file, name/size, remove-from-list before upload, client-side type/size checks from Settings (`docs.allowed_mimes`, `docs.max_upload_kb`), progress, and server errors shown per file. No bare `<input type="file">` in screens. Files still go through DocsService/media library.
An image / file field that already holds a file (logo, favicon, photo, brochure …) always shows the **current** file
(or the built-in default, labelled so) next to the drop-zone, with a **Remove** button (confirm) that clears it —
owner rule 30-09; reference: Utilities → Settings → Site.

## Modern, minimal, responsive screens (phone, tablet, desktop)
Every screen is modern, elegant and minimal (Tabler tokens, consistent page shell/cards, clear hierarchy, empty/loading/error states) and must work at 360px (6" Android/iOS), 768px tablet and desktop: no fixed pixel widths, tables in `.table-responsive` or stacked cards on phones, forms single-column on phones, touch targets ≥ 40px, dropdowns fit the viewport. Verify at those three widths before shipping. No hex/`bg-white`/inline `<style>` blocks — shared CSS and Tabler variables (dark mode safe).

## Dense, standard form screens (user standing instruction, 29-09-2026)
- Every form screen uses the one standard shell: the same page header (small eyebrow line + title + actions on the
  right), minimal spacing and the compact type scale, so the most data fits on one screen. No per-screen header styles.
- Every card on a form is **collapsible and draggable** (the user's order is remembered) and shows
  **required filled / required total** in its header. The shared layer (`public/js/xl-ui.js`) does this for cards
  inside forms; opt out with `data-xl="off"`. Put required inputs inside their card and mark them `required`, so the
  counter is right.
- Prefer the space-saving kit elements wherever they fit: Select2 (`<x-ui.select>`), flatpickr (`<x-ui.date>`), the
  drop-zone (`<x-ui.upload>`), badges, button groups, tabs, accordions and collapsible cards. Check the dev UI kit
  (`/admin/dev/ui`) before building anything new, and reuse it.
- Density (font size, spacing / margin / padding scale) is a per-user choice with a site default (Settings
  `ui.density.*` + the Appearance panel). Style only with the shared CSS variables so the controls reach every screen;
  never hard-code px font sizes or paddings.
- Performance on every screen: `loading="lazy"` + `decoding="async"` on images (the shared layer adds it to images
  without the attribute), load large grids and off-screen tabs / cards by AJAX when they are opened, keep inline scripts
  small, and let Basset cache CDN assets.
- Error pages (403 / 404 / 419 / 429 / 500 / 503) use the shared branded templates in `resources/views/errors/`. Never
  show a raw stack trace or framework page to users.
