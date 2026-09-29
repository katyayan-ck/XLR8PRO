# UI kit — dates, selects, uploads, responsive screens

Project-wide standards (`.ai/rules/ui.md`, DEC-066) and the shared pieces that implement them. Every admin page loads
`public/js/xl-ui.js` + `public/css/xl-ui.css` and the pinned libraries (flatpickr 4.6.13, Select2 4.1.0-rc.0 — from
`config/backpack/{ui,theme-tabler}.php`, cached locally by Basset). **Never add per-view CDN tags for these.**

## What happens automatically on every page
| Element | Becomes |
|---|---|
| `<input type="date">`, `<input type="datetime-local">`, `[data-xl-date]` | flatpickr showing the **site format** (`display.date_format`, + `display.time_format`); the submitted value stays ISO (`Y-m-d` / `Y-m-d H:i`) |
| `<select multiple>`, `select[data-xl="select2"]` | Select2 — search, chips, clear, placeholder |
| `<input type="file">` | drop-zone — drag & drop / browse, thumbnails, name + size, remove before upload, type / size checks from Settings (`docs.allowed_mimes`, `docs.max_upload_kb`) |
| bare `<table class="table">` | wrapped in `.table-responsive` (scrolls on phones) |
| AG-Grid date columns (field `*_date`, `*_at`, `dob`, header "… Date") | formatted with the site format |
| `.card` with a `.card-header` inside a `<form>` | **collapsible + draggable** card with a **required filled / total** badge (to-do U1, below) |
| `<img>` without a `loading` attribute | `loading="lazy"` + `decoding="async"` (to-do U4) |
| Backpack `date` / `datetime` columns | site format (Backpack config is set from the setting at boot) |

Opt out for one element or a whole block with `data-xl="off"`. Content added later (AJAX, modals) is enhanced too.

## Use the components in new code
```blade
{{-- date / date-time (submits ISO) --}}
<label class="form-label" for="xl-deadline">Deadline</label>
<x-ui.date name="deadline" :value="$task->deadline" time />
<x-ui.date name="from" :value="request('from')" class="form-control-sm" placeholder="From" min="2026-04-01" />

{{-- Select2 (never a list box) --}}
<x-ui.select name="assignees[]" :options="OrgService::teamOptions()" :selected="$ids" multiple placeholder="Pick people" />
<x-ui.select name="branch" :options="OrgService::branches()" :selected="$branch" placeholder="All branches" />
<x-ui.select name="customer" :source="$yourSearchUrl" placeholder="Search customer" />  {{-- AJAX: your endpoint returns {results:[{id,text}]}, reads ?q= --}}

{{-- uploads --}}
<x-ui.upload name="file" />                                         {{-- posts with the surrounding form --}}
<x-ui.upload name="files[]" multiple accept=".pdf,image/*" />
<x-ui.upload name="file" multiple reload :url="route('utils.docs.store')"
             :fields="['ref_type' => 'BOOKING', 'ref_id' => $booking->id, 'collection' => 'kyc']" />   {{-- AJAX, per-file progress + errors --}}
```
**Your own flatpickr** (a view that needs custom options): keep your submit `dateFormat`, show the site format —
`flatpickr('#x', { dateFormat: 'Y-m-d', altInput: true, altFormat: XL.flatpickrFormat() })` (`XL.flatpickrFormat(true)`
adds the time). `XL.dateFormat` / `XL.dateTimeFormat` / `XL.flatpickrFormat()` are defined in the page head, so they work
even in scripts that run before `xl-ui.js`. Never load flatpickr / Select2 per view — the pinned global copies are always there.

Displaying dates: `@sitedate($d)` / `site_date($d)`, `@sitedatetime($d)` / `site_datetime($d, '—')`. In JavaScript:
`XL.formatDate(value)` / `XL.formatDate(value, true)`. **Never** `->format('d-m-Y')`, `toLocaleDateString()`, or a
hard-coded format.

## Screen checklist (before you ship)
- [ ] Looks like the rest: Tabler card / page shell, clear title, primary action on the right, empty state, errors shown.
- [ ] Works at **360px** (6" phone), **768px** (tablet) and desktop — no fixed pixel widths (use grid classes / `.xl-toolbar`
      for filter bars), tables inside `.table-responsive`, forms single column on phones, tap targets ≥ 40px.
- [ ] Dates in the site format; pickers are `x-ui.date`; multi-selects are `x-ui.select`; uploads are `x-ui.upload`.
- [ ] Colours only from Tabler tokens / classes (`bg-body`, `text-secondary`, `var(--tblr-*)`) — no hex, no `bg-white`,
      no inline `<style>` blocks (put shared styles in `public/css/xl-ui.css`).
- [ ] Every input has a `<label for>`; icon buttons have `aria-label`.
- [ ] Checked as superadmin **and** a scoped user.

Helper classes: `.xl-toolbar` (wrapping filter bar), `.xl-empty` (empty state), `.xl-scroll-y`, `.xl-pre`,
`.xl-page-head`, `.xl-stack-sm` (stack children on phones).

## Verifying at phone / tablet widths
Chrome DevTools device mode (iPhone 12 Pro 390px, iPad Mini 768px). Desktop Chrome windows can't be narrower than
~500px, so resize the browser only for tablet checks.

## Form cards: collapse, reorder, required counter (to-do U1)
Every `.card` that has a direct `.card-header` and sits inside a `<form>` gets, from `xl-ui.js` (no per-view code):
- a **collapse chevron** in the header; a collapsed card shows only its header;
- a **drag grip** when the card has card siblings: drag it, or focus it and press **Alt + ↑ / ↓**;
- a **required badge** `filled/total` (amber until complete, then green; hidden when the card has no required field). It
  counts Backpack's `.form-group.required` wrappers plus any `[required]` control outside one (a radio / checkbox group
  counts once), and updates live on `input` / `change` (Select2's jQuery `change` too).

The order and the collapsed cards are saved per screen in the browser (`localStorage` `xl.cards:{path}`, record ids in the
path are ignored, so every edit page of an entity shares one layout). When the browser flags an invalid field inside a
collapsed card on submit, the card opens so the field can be focused.

For the counter to be right, keep each required input inside its card and mark it `required` (or use a Backpack
FormRequest rule `required`). Opt out a card or a whole form with `data-xl="off"`. A card without a header is left alone.

## List screens: shared grid look, toolbar, messages (Sales UI pass, 30-09)
- **Grid container:** `<div id="myGrid" class="ag-theme-quartz xl-grid" …>` — `xl-grid` gives the standard centred
  headers, group-header padding and `.action-cell` buttons (no per-view `<style>` for these).
- **Loader:** `<div class="xl-grid-wrap"><div id="gridLoader" class="xl-grid-loader" style="display:none;">…` — the page
  script shows it with `style.display = 'flex'`.
- **Header customise popover:** `<div id="columnBubble" class="xl-col-bubble" style="display:none;">` with a
  `<div class="xl-col-bubble-body">` list; the script toggles `style.display`.
- **Toolbar:** search `class="form-control … xl-toolbar-search"`, export icons `class="xl-export-icon"`.
- **Form bits:** `.required-mark`, `.readonly-label`, `.readonly-value`, `.field-frozen`, `.form-group.readonly-field`.
- **Libraries:** export / validation / mask / SweetAlert libraries load with `@basset('<pinned URL>')` (cached locally);
  the AG-Grid tag stays the pinned `ag-grid-community@36.2.0` build.
- **Messages:** `XL.notify(message, type?)` — a Noty toast (types warning (default) | error | success | info; "fail /
  error / unable" in the text → error). Never `alert()`; `confirm()` stays where the code needs the answer.

## Density: text size and spacing (to-do U3)
| Setting (site default) | Values | Default |
|---|---|---|
| `ui.density.text` | `xs` (14 px root) · `sm` (15 px) · `md` (Tabler standard, 16 px) · `lg` (17 px) | `sm` |
| `ui.density.space` | `compact` (×0.5) · `cozy` (×0.75) · `comfortable` (Tabler standard) | `compact` |

- Each user can override both in the **Appearance** panel (*Text size*, *Spacing*; "Site default" follows Settings).
  The choice is saved in that browser (`localStorage` `xl.theme`).
- The render-blocking bootstrap (`inc/theme_styles.blade.php`) sets `<html data-xl-text data-xl-space>` before the first
  paint, so nothing jumps. `App\Support\UiDensity::defaults()` validates the settings (an unknown value → the Tabler
  standard).
- CSS (`xl-ui.css`): the text size scales the root font size (Tabler sizes everything in `rem`); the spacing scale
  (`--xl-space`) tightens card padding, form-group margins, the page header and the page body. **Style with rem and
  Tabler variables** so your screen follows both; never hard-code px font sizes or paddings.
- JS: `XL.theme.set('text', 'lg')`, `XL.theme.set('space', '')` (back to the site default).

## Theme, layout and the dev UI kit (DEC-067)
**Appearance panel** — the palette icon in the top bar, or *Appearance* in the user menu. Every user can choose:
- colour mode: light / dark / system;
- colour scheme: 12 Tabler colours;
- theme base: slate / gray / zinc / neutral / stone;
- font: sans / serif / mono / comic;
- corner radius: 0 – 2;
- text size: XS / S / M / L, and spacing: compact / cozy / roomy (U3; default = the site setting);
- menu layout: top menu / sidebar / dark sidebar.

The choice is saved in that browser only. All of it is Tabler 1.4's own theming, so **your screen follows it for free
as long as you style with tokens** (`bg-*-lt`, `text-secondary`, `var(--tblr-*)`).

```js
XL.theme.get();                        // {mode, primary, base, font, radius, text, space, layout}
XL.theme.set('primary', 'teal');       // also 'mode' → light|dark|system, 'layout' → horizontal|vertical|vertical_dark
XL.theme.token('--tblr-primary');      // resolved colour, for charts / canvas
XL.theme.onChange(state => rebuildMyChart());
```

**AG-Grid:**
- Don't pass `theme`, and don't add `ag-theme-quartz.css` to new screens.
- The global hook gives every grid a Quartz theme built on Tabler variables, so dark mode, the primary colour, the font and the radius apply live. It also switches off the legacy stylesheet on old screens.
- Every view loads `https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js` (all 87 pinned, DEC-069);
  copy that tag, never an unversioned one, and no grid stylesheet (the old `ag-grid-tabler-theme.css` was deleted, DEC-069).
- List toolbars: the `#quickFilter` search box may carry a desktop width; below 768px `xl-ui.css` lets its group take the
  row and the box shrink. Header action groups use `flex-wrap`, never `flex-nowrap` with fixed-width selects.

**Charts:** ApexCharts 3.54.1 (MIT).
- Load it with `@basset('https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js')` on the page that needs it.
- Take its colours from `XL.theme.token()`, and rebuild the chart on `XL.theme.onChange`.
- The UI-kit dashboard source is the pattern to copy.

**Dev UI kit** — `/admin/dev/ui`:
- Pages: Overview · Forms · Lists & tables · Elements · CRM dashboard · Chat · Pages.
- Static reference markup to copy; it reads and writes no data.
- On for `APP_ENV=local`. Elsewhere it returns 404 unless `.env` has `XL_DEV_UI_KIT=true`.
- Linked from the user menu when it is on.

**Legacy screens:** `public/css/xl-theme.css` has a dark-mode safety net for `bg-white`, `bg-light`, `text-black`, `text-dark`, `table-light` and common inline light colours. It is a stop-gap: convert a screen to tokens when you touch it.

## Error pages (go-live to-do U8)
- **Public / signed-out:** `resources/views/errors/{401,403,404,419,429,500,503}.blade.php` extend
  `errors/xl.blade.php` (standalone; styles in `public/css/xl-errors.css`; works with the database down). A new code
  page = `@extends('errors.xl')` + the `code` / `title` / `message` sections (optional: `reference`, `actions`).
- **Admin panel:** `resources/views/vendor/backpack/theme-tabler/errors/layout.blade.php` (in-shell; dashboard + back).
- **500s** show `App\Support\ErrorRef::get()`; the same id is in the log context of every exception.
