# UI / design work — progress and resume notes

**Status (28-09-2026): paused.** It resumes after the Sales (booking) team's branch is merged, so the same changes can be
applied to their views. Decisions: DEC-066 (UI standards, shared layer) and DEC-067 (Tabler-parity shell, theme, AG-Grid,
dev UI kit). Commits on `dev/admin`: `080c15c` (DEC-066), `15415ed` (DEC-067). Neither is pushed yet.

## 1. What is done

### DEC-066 — standards and the shared UI layer
- **Rules** (`.ai/rules/ui.md`):
  - site date format everywhere;
  - Select2 for multi-selects;
  - drop-zone uploads;
  - modern, minimal, responsive screens (360 / 768 / desktop);
  - tokens only (no hex, no `bg-white`).
- **`public/js/xl-ui.js` + `public/css/xl-ui.css`**, loaded on every admin page from
  `resources/views/vendor/backpack/ui/inc/header_metas.blade.php`. It progressively enhances:
  - native date inputs → flatpickr showing the site format and submitting ISO;
  - `select[multiple]` → Select2;
  - `input[type=file]` → drop-zone;
  - bare tables → `.table-responsive`.

  Opt out with `data-xl="off"`.
- **Components:** `x-ui.date`, `x-ui.select`, `x-ui.upload`.
- **Helpers:** `site_datetime()` / `@sitedatetime`, and `DateFormatService::{phpDateTimeFormat, formatDateTime, isoFormat}`.
- **Converted:** all platform-utility screens, and the notification bell (one bell with tabs).
- **Dates:** 28 non-Sales views now display dates in the site format.

### DEC-067 — Tabler parity
| Piece | Files |
|---|---|
| Render-blocking theme bootstrap (mode incl. system, primary / base / font / radius) + Tabler 1.4 `tabler-themes.min.css` + `xl-theme.css` | `resources/views/vendor/backpack/theme-tabler/inc/theme_styles.blade.php` |
| Appearance panel (off-canvas): mode, 12 colours, base, font, radius, layout, reset | `inc/theme_settings.blade.php`, `inc/appearance_button.blade.php`, `public/js/xl-theme.js` (`XL.theme` API) |
| Layout choice per browser: `xl_layout` cookie (unencrypted, whitelisted) → `App\Http\Middleware\ApplyUiPreferences` (in `backpack.base.middleware_class`); layouts `horizontal` (default), `vertical`, `vertical_dark` | `bootstrap/app.php`, `config/backpack/base.php`, `layouts/{horizontal,vertical,vertical_dark}.blade.php`, `layouts/_vertical/menu_container.blade.php` |
| Tabler user block: photo over initials, online dot, name + designation, account menu | `inc/menu_user_dropdown.blade.php`, `inc/menu.blade.php` |
| AG-Grid: global hook wraps a **copy** of the module (v33+ exports are read-only), applies a Quartz theme whose params are Tabler CSS variables, disables legacy `styles/ag-theme-*.css`; date columns → site format | `header_metas.blade.php`, `public/js/xl-ui.js` |
| Dark-mode safety net for legacy markup (`bg-white`, `bg-light`, `text-black/dark`, `table-light`, inline light backgrounds / dark text) + Backpack input / checkbox colours | `public/css/xl-theme.css` |
| Dev UI kit `/admin/dev/ui/{index,forms,lists,elements,dashboard,chat,pages}` — static reference, gated by `platform.dev_ui_kit` (`XL_DEV_UI_KIT`, default local only) | `routes/backpack/dev.php`, `app/Http/Controllers/Admin/Dev/UiKitController.php`, `resources/views/admin/dev/ui/*` |
| Charts: ApexCharts 3.54.1 (MIT, pinned + SRI, Basset), used only on the kit dashboard; theme-aware pattern there | `resources/views/admin/dev/ui/dashboard.blade.php` |
| Tests | `tests/Feature/Admin/UiKitAndLayoutTest.php` (5) |
| Developer guide | `docs/utilities/ui-kit.md` → "Theme, layout and the dev UI kit" |

**Verified:**
- Headless Chrome at 1366 px and 390 px, light and dark, several primary colours, serif font, radius 1.5, all three layouts, the Appearance panel, and the legacy branch grid.
- Full suite: 338 passed, 2 skipped.

## 2. Sales / booking pass — done 28-09-2026 (DEC-068)
- AG-Grid pinned to 36.2.0 in 49 views; legacy grid CSS and per-view flatpickr / Select2 tags removed (71 files).
- 13 view-level flatpickr pickers now display the site format (`altInput` + `XL.flatpickrFormat()`), submit format unchanged;
  23 display dates → `site_date()` / `site_datetime()` (picker `value=` attributes kept in their picker's format).
- 227 colour classes → tokens (`bg-surface`, `bg-surface-secondary`, `text-body`); 284 hex colours in `<style>`, inline
  styles and JS → Tabler variables (screen only — `@media print` blocks and `admin/pdf/*` untouched); SweetAlert buttons read
  `XL.theme.token()`. `xl-ui.js` no longer wraps selects a page hides on purpose.
- 2 native date inputs converted; file inputs and multi-selects stay enhanced at runtime (identical result).

## 3. Later items — done 28-09-2026 (DEC-069)
- AG-Grid pinned in the remaining 37 views (all 87 now on 36.2.0; legacy grid CSS links dropped).
- List toolbars: `#quickFilter` shrinks and its group takes the row below 768px (`xl-ui.css`, covers ~85 views); booking
  list header wraps. Verified at 390px (booking, branch lists).
- Booking proofs moved to Docs (see `docs/domains/sales-booking.md`).

## 4. Still open (follow-ups)

1. ~~Pin AG-Grid~~ — done everywhere (§3). `public/css/ag-grid-tabler-theme.css` deleted (unused).
2. ~~Convert Sales / booking views~~ (done, see §2). Remaining: PDF views are exempt by design; (about 250 violations counted in the DEC-066 scan):
   - native date inputs and hard-coded date formats → `x-ui.date` / `@sitedate`;
   - list boxes → `x-ui.select`;
   - bare file inputs → `x-ui.upload`;
   - fixed pixel widths → grid classes.
3. **Hex colours and inline `<style>` outside Sales** (org, vehicle, pricing, utils legacy views); Sales done. (The safety net only covers the common cases.)
   - move shared styles to `xl-ui.css` / `xl-theme.css`, colours to tokens;
   - ~~`border-color:#80bdff`~~ — the Bootstrap-4 `.form-control:focus` override was removed from all 69 views (DEC-070);
     inputs now show Tabler's focus ring in the theme colour. Other hex values in ~107 non-Sales views remain.
4. ~~`menu_items.blade.php` inline `<style>`~~ — moved to `xl-theme.css` (DEC-070). Nested dropdown menus should still be checked in the vertical layouts on real data.
5. **Logo in dark mode:** `xl-theme.css` inverts the logo image to white in dark mode / dark sidebar. This is unverified, because the image did not load in the preview harness. Check it on the real site; a dedicated light logo file may be better.
6. **Real CRM dashboard:** the kit dashboard is static. The real `/admin/dashboard` should reuse its cards and chart pattern with data from services.
7. **Mobile header:** on phones the Appearance button is only in the user menu (by design). Confirm with users.
8. **Avatar:** `backpack_avatar_url()` still uses Gravatar (an external call with an email hash, `config/backpack/base.php`). Consider a local photo from the Person / Docs record.
9. After each conversion, check the screen in dark mode and with a non-default primary colour, at 390 / 768 / 1366.

## 4. How to verify quickly
- Kit: `/admin/dev/ui` (local).
- Theme state in the browser console:
  - `XL.theme.get()`;
  - `XL.theme.set('mode', 'dark')`;
  - `XL.theme.set('primary', 'red')`;
  - `XL.theme.set('layout', 'vertical')`.
- Headless screenshots, as used in DEC-066/067:
  1. Render the page as a user against `xlrm_testing` (a small script that boots the kernel, logs in with `loginUsingId`, and handles `Request::create('/admin/…', 'GET', [], ['xl_layout' => '…'])`).
  2. Rewrite asset URLs `http://localhost/` → `http://localhost/xlrm/public/`.
  3. Inject `localStorage` values for the mode and theme, and save the result under `public/_preview/`.
  4. Run `chrome --headless=new --screenshot --window-size=W,H --virtual-time-budget=9000`.
  5. For phone widths, put the page in a 390 px iframe, because desktop Chrome can't be narrower than about 500 px.
  6. Delete `public/_preview` afterwards.
  7. Content can look faded in screenshots: that is Backpack's `animated fadeIn` caught mid-animation.
