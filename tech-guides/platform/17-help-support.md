# 17 — Help & support (F1 help, tours, support requests)

> DEC-094, to-do W16. FRS: [help-and-support-frs.md](../frs-and-workflows/frs/help-and-support-frs.md).
> Built: **W16b help engine**, **W16c page tours**, **W16d diagnostics**, **W16e support requests** (03-10-2026);
> this guide grows with them (W16f). Help **content** (the articles themselves) is written last (§13).

## F1 help pane — how it works
- Every signed-in admin page carries `<meta name="xl-help">` (current route name, endpoint URLs, labels) and loads
  `public/js/xl-help.js` (`resources/views/vendor/backpack/ui/inc/header_metas.blade.php`). The top bar has a `?` button
  (`data-xl-help-open`, `theme-tabler/inc/topbar_right_content.blade.php`).
- **F1**, **Shift+?** (outside inputs) or the `?` button toggle a right-side pane (420 px; full width on a phone); Esc
  closes it; focus moves into the pane and back. Nothing is fetched until the first open.
- The pane calls `utils.help.pane?route=<route name>` once per page, shows the article and a search box, and fires
  `xl:help-loaded` (detail = the pane JSON) for the tour runner (W16c).
- `window.XL.help.open()` / `.close()` open it from code.

## Page tours (W16c)
- An article's `tour` front matter lists steps `{ element: <CSS selector>, title, text }`. Mark stable targets in the
  screen with `data-xl-tour="name"` and use `'[data-xl-tour=name]'` as the element (preferred over ids / classes).
- Driver.js **1.3.1** (MIT, approved in DEC-094) is loaded through Basset **only on pages whose article has a tour**
  (`header_metas.blade.php`).
- The pane shows **Take the tour** when the article has steps. The tour also starts from a link with `?tour=1`.
- `XL.help.tourSteps(tour)` keeps only steps whose element is on the page and visible; a bad selector is skipped. With
  no step left, the pane says so and nothing runs. Tours never change data.
- **"New" dot:** the page meta carries `article: {key, updated, tour}` (looked up without logging a miss). When
  `localStorage['xl.help.seen.'+key]` differs from `updated`, the `?` button shows a dot until the article is opened.

## Diagnostics collector (W16d)
Gathered for a support request (W16e builds the zip; FRS §5.2). **Never records what a user types.**
- **Browser — `public/js/xl-diag.js`** (every signed-in admin page; ring buffers in `sessionStorage['xl.diag']`, per
  tab, 50 each):
  - `actions`: page loads, clicks (label + selector), form submits (field **names** only, `_token` dropped), alerts /
    validation messages shown;
  - `network`: fetch and XHR (so jQuery AJAX too) — method, URL, status, ms, size; for 4xx / 5xx the first 2 KB of the
    body, masked;
  - `errors`: `window.onerror`, unhandled promise rejections, `console.error`.
  - `XL.diag.snapshot()` → `{page{url, route, title, screen, viewport, browser, time, version, theme}, actions,
    network, errors}`.
  - `XL.diag.screenshot()` → JPEG data URL of the visible page via **html2canvas-pro 1.5.11** (MIT fork of html2canvas;
    the original 1.4.1 cannot parse Tabler 1.4's `color()` / `oklch()` / `color-mix()` and failed on every page —
    BUG-232; owner approved the swap 03-10). Cached by Basset, loaded only when called. When a capture fails the pane
    says so and the reason lands in `errors.json`. In the copy only, password / OTP inputs and anything marked
    `data-xl-sensitive` (mark Aadhaar, PAN and bank-account fields with it) are blanked; the help pane is left out.
  - `XL.diag.mask(text)` / `XL.diag.cleanUrl(url)` mirror the server helpers.
- **Server — `App\Http\Middleware\RecordRequestTrail`** (admin middleware stack): each request of the signed-in user
  → `{method, route, path (cleaned), status, ms, ref (ErrorRef on 5xx), at}`; the idle heartbeat is skipped.
- **`App\Services\Platform\Help\DiagnosticsService`:**

  | Method | Does |
  |---|---|
  | `record(int $userId, array $entry): void` | prepend to the trail (cache `help.trail.{id}`, `TRAIL_SIZE` 50, `TRAIL_MINUTES` 120) |
  | `trail(int $userId): array` | newest first |
  | `static mask(string $text): string` | Aadhaar → `XXXXXXXX1234` (spaces / dashes too), PAN → `XXXXXX234F`, mobile → `XXXXXX3210` (+91 too), e-mail → `r***@domain`, 32+ char tokens → `[token]` |
  | `static cleanUrl(string $url): string` | path + query with `token / _token / password / otp / signature / code / key / secret / api_key / access_token` values → `[removed]`, then `mask()` |

## Support requests — "Still need help?" (W16e)
- **Pane:** the footer button opens a form in the pane: what you need (5 categories), urgent (→ P2, else P3), subject
  (default: page title), description, "Attach diagnostics" (on), and a screenshot preview that can be left out. It posts
  JSON to `utils.support.store`; the request becomes a **ticket** the user follows under Utilities → Tickets.
  `XL.help.support()` opens the form from code.
- **Diagnostics are for the support team only (owner 03-10):** they show as a **Diagnostics** card on the ticket page
  (`tickets/show.blade.php` includes `support/_diagnostics.blade.php`: screenshot, page facts, Actions / Network / Errors /
  Server / User tabs, zip download) for support admins (`UTL_SUPP_ADMIN`) and the ticket's owner, assignees and
  snoopers. The **requester never sees them**, cannot download them (403) and nothing about them is posted to the
  ticket conversation (so there is nothing to remove). There is no separate support section or menu.
- **Support tickets in the ticket engine** (`TicketService`):
  - `isSupportDesk()`: support admins see and manage every `SUP_*` ticket like the service desk.
  - `update()` refuses assignees who are not `UTL_SUPP_EXEC` holders (`SUPPORT_NOT_EXECUTIVE`).
  - `supportExecutiveIds()` feeds the ticket page's assignee picker.
- **`App\Services\Platform\Help\SupportRequestService`:**

  | Method | Does |
  |---|---|
  | `submit(User $user, array $input): Result` | `Ticket::open` (category `SUP_*`, P2 / P3, title + masked description, `ref_type = SUPPORT`, owner = `supportAdmin()`), a `SupportRequest` row, the zip (when diagnostics are on), Notify to the owner; ok data `{id, ticket_id, number}`; `SUPPORT_CATEGORY` on an unknown category |
  | `supportAdmin(): ?int` | active `UTL_SUPP_ADMIN` holder with the fewest open `SUP_*` tickets; ties → the one without a new support ticket longest; null when nobody holds it |
  | `forTicket(Ticket $t): ?SupportRequest` | the request behind a ticket |
  | `canViewDiagnostics(?Ticket $t, User $u): bool` | any `UTL_SUPP_ADMIN`, or the ticket's OWNER / ASSIGNEE / SNOOPER — never the requester or followers (unless they also hold one of those roles) |
  | `bundleFile(SupportRequest $r): ?string` / `bundleContents(SupportRequest $r): ?array` | the zip's path / its decoded files `{files, screenshot}` for the card; null when none or purged |
  | `purge(): int` | deletes zips older than `support.bundle_retention_days` (the request and ticket stay); daily job `PurgeSupportBundles` 02:45 |
  | `holders(string $permission): array` | active user ids holding a permission (role or direct) |
- **Zip** (`storage/app/private/support/{id}/diagnostics-{id}.zip`, disk `local`, never public): `page.json`,
  `actions.json`, `network.json`, `errors.json` (from `XL.diag.snapshot()`), `server.json` (the user's trail + the log
  lines of its error references, last 2 MB of `laravel.log`), `user.json` (roles, permissions, data scopes, last login),
  `screenshot.png|jpg` (PNG / JPEG only, signature-checked, ≤ `support.max_screenshot_kb`). Every text file passes
  `DiagnosticsService::mask()`.
- **Switch:** setting `support.pane_requests` (Settings → Support, default on). Off → the pane shows no "Still need
  help?" (the page meta carries `support: null`) and `utils.support.store` answers 403 `SUPPORT_PANE_DISABLED`.
- **My support tickets** (user menu, every signed-in user, always on): `utils.support.mine` lists the tickets the user
  raised (newest first) with a **New support ticket** form → `utils.support.open` (POST) → `submit()` without
  diagnostics → the new ticket's page. Same routing (category, P2 when urgent, least-loaded support admin).
- **Routes:** `utils.support.store` (POST JSON → 201 / 403 / 422), `utils.support.mine`, `utils.support.open`,
  `utils.support.download` (support team only; 403 / 410).
- **Permissions:** `UTL_SUPP_ADMIN`, `UTL_SUPP_EXEC` (process UTL / SUPP), granted to superadmin only — attach them to
  designations on Org → Designation. Ticket categories `SUP_HOWTO`, `SUP_NOT_WORKING`, `SUP_WRONG_DATA`, `SUP_ACCESS`,
  `SUP_SUGGESTION` (KeyValue `TICKET_CATEGORY`).
- **Menu:** Utilities → Help Centre and user menu → My support tickets (every signed-in user).
- Tests: `tests/Feature/Platform/SupportRequestTest.php`.

## Writing an article
Files under `resources/help/` (`config('platform.help.path')`), versioned with the code — change a screen and its help in
the same commit: `{module}/{process}/{screen}.md`, overviews `_module.md` / `_process.md`.

```markdown
---
title: Booking — edit
routes: [sales.booking.edit, sales.booking.update]   # the screens it serves (route names)
route_prefix: sales.booking                          # overviews only: fallback for every route under it
module: SLS
process: BKNG
permissions: [SLS_BKNG_VIEW]                         # who may open it (any of); omit = everyone
updated: 2026-10-01
tour:                                                # optional (W16c)
  - { element: '[data-xl-tour=customer]', title: Customer, text: Name, mobile … }
---
## What this screen is for
…

::: can SLS_BKNG_ORDER_APPROVE
### For approvers
Shown only to users with this permission (several codes: `::: can A, B` = any of).
:::
```
- Markdown is rendered by CommonMark; raw HTML is **escaped**, unsafe links are dropped.
- An article with invalid front matter is skipped and logged (`Help article front matter is invalid`).
- A route with no article and no matching overview shows the "not written yet" message and logs `Help article
  missing` with the route, so writers see the gaps (also listed in the Help centre's coverage report).

## `App\Services\Platform\Help\HelpService`
| Method | Returns |
|---|---|
| `articles(): array` | every article keyed by path without `.md`: `{key, title, routes, route_prefix, module, process, permissions, updated, tour, body, mtime}`; cached per file-set signature |
| `forRoute(string $route, User $user): ?array` | the article listing the route, else the overview with the longest `route_prefix`, else `null` (logged); only articles the user may open |
| `find(string $key, User $user): ?array` | one article by key when the user may open it |
| `canOpen(array $article, User $user): bool` | `permissions` empty or the user has one of them |
| `render(array $article, User $user): string` | HTML with the user's `::: can` sections; cached per article version + the user's answers for the codes it checks |
| `search(string $q, User $user, int $limit = 20): array` | `[{key, title, snippet}]` over title (3), headings (2), text (1); hidden sections are never searched |
| `coverage(): array` | `{total, covered, missing[]}` — admin GET screen routes with / without an article of their own |
| `path(): string` | the articles directory |

## Routes (`routes/backpack/utils.php`, every signed-in user)
| Route | Does |
|---|---|
| `utils.help.index` `GET admin/utils/help` | Help centre: search + articles by module; coverage for `UTL_SETTINGS_MANAGE` |
| `utils.help.pane` `GET admin/utils/help/pane?route=` | JSON `{ok, missing, key, title, html, updated, tour, url}` (missing: `{ok, missing: true, title, message}`) |
| `utils.help.search` `GET admin/utils/help/search?q=` | JSON `{ok, results: [{key, title, snippet, url}]}` (q 2–100 chars) |
| `utils.help.show` `GET admin/utils/help/article/{key}` | one article as a page; 404 when missing or not allowed |

Wording: `resources/lang/en/utils.php` → `help.*`. Styles: `public/css/xl-ui.css` (`.xl-help-*`).
Tests: `tests/Feature/Platform/HelpTest.php` (fixture articles in a temp folder via `config(['platform.help.path' => …])`).
