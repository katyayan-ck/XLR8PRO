# 17 — Help & support (F1 help, tours, support requests)

> DEC-094, to-do W16. FRS: [help-and-support-frs.md](../frs-and-workflows/frs/help-and-support-frs.md).
> Built so far: **W16b help engine** (03-10-2026). Tours (W16c), diagnostics (W16d) and support requests (W16e) follow;
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
