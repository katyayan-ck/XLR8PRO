# Plan: contextual help (F1), page tours, support requests with diagnostics, user manual (DEC-094, to-do W16 / W17)

> **Status (03-10-2026): 🟡 in progress** — phases B–E (help engine, tours, diagnostics, support requests) done 03-10; next: W16f guide wrap-up; articles (W17) last. Requirements in
> [FRS](../frs/help-and-support-frs.md).
> **Owner 01-10:** the user manual (W17) and the help-article **content** move to the end of the to-do list (§13), after
> all bugs are fixed and QA has vetted the functionality; the engine, pane, tours and support requests are built as planned.

## Context
The owner asked (01-10) for:
- help on every page with F1 (a right-side pane with the page's full help);
- a "more help" button that gathers diagnostics into a zip on a new support ticket, auto-assigned to a support admin
  who assigns an executive;
- an FRS, a developer guide and a user guide;
- a screen-wise and module / process-wise user manual with screenshots;
- an on-demand page tour.

What already exists and is reused:
- the ticket engine (`TicketService`, numbers, SLA, desk, owner / assignees, remarks, attachments, screens under
  `utils.tickets.*`);
- `DocsService` for attachments;
- Settings, the Notify service, and `ErrorRef` (error references in logs).

Laradocs stays the developer-guide viewer.

## Owner decisions (01-10, DEC-094)
1. Two small JS libraries, served locally through Basset: **html2canvas** (silent page screenshot) and **Driver.js**
   (tours).
2. Two new permissions: **`UTL_SUPP_ADMIN`** (receives new support tickets; the holder with the fewest open ones) and
   **`UTL_SUPP_EXEC`** (assignable). Granted to superadmin only; the owner attaches them to designations.
3. User manual **with screenshots via Playwright** (dev-only tool), with PII masking.
4. Build **after W15a**; plan and to-do now.

## Agent design choices (no business rule involved)
- **Own help engine, not Laradocs.** Markdown articles in `resources/help/`, versioned with the code, so a screen change
  and its help change land in one commit. Route name → article. `::: can CODE` sections. Rendered with Laravel's
  Markdown (league/commonmark, already installed) and cached.
- **Client ring buffer** (sessionStorage, per tab) for actions / network / errors. It never records typed values. A
  short-lived **server request trail** (cache, 50 entries per user, 2 h) gives server status + error refs without a new
  table.
- **The bundle is built on the server** (PHP `zip` extension present) from the client JSON plus server facts. It is
  attached to the ticket and purged by a daily job after the retention period.

## Phases (each its own commit with tests, records, guide sync)
| Phase | To-do | Deliverables |
|---|---|---|
| A | W16a ✅ | FRS, this plan, DEC-094, to-do rows |
| B | W16b | `App\Services\Platform\Help\HelpService` (article index, route resolution, permission sections, render + cache, search, coverage report); routes `utils.help.{pane,search,index,show}`; right-side pane component + `public/js/xl-help.js` (F1, `?` button, focus handling) in the theme layout; Help centre screen; tests |
| C | W16c | Driver.js via Basset (approved); tour steps from front matter / `data-xl-tour`; runner, skip-missing; "new" dot; tests |
| D | W16d | `public/js/xl-diag.js` ring buffer (actions, fetch / XHR / jQuery AJAX, JS errors, alerts), masking helpers; server trail middleware; html2canvas capture with sensitive-field blanking + preview; tests |
| E | W16e | migration: permissions `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC`, KeyValue support categories, settings `support.*`; `SupportRequestService` (bundle zip, masking, `Ticket::open`, least-loaded owner, executive-only assignee picker); support request screen + My support requests; bundle download rights; retention purge job; notifications; tests |
| F | W16f | developer guide `tech-guides/platform/17-help-support.md` (+ `16-reference.md`, `README`), user guide articles "Getting help" / "Support requests", article-writing guide |
| G | W17a | article template, module / process overview pages, coverage report wired to the Help centre |
| H | W17b | articles for every admin screen (~210 screen routes → ~120 articles), module by module, business wording flagged for owner review |
| I | W17c | Playwright (dev dependency) capture script with PII masking, superadmin + scoped user runs, screenshots in `public/help/img/` |
| J | W17d | screenshots into the articles, print stylesheet, owner review list |

## Verification
- Feature tests: pane article per route, permission sections, search, missing-article fallback, tour data, support
  request → ticket with owner = least-loaded `UTL_SUPP_ADMIN`, bundle contents and masking (no typed values / OTP /
  unmasked PAN, Aadhaar, mobile), download rights, purge job.
- JS: manual checks in Chrome / Edge / Firefox; F1 suppression; phone width; keyboard only.
- HTTP smoke as superadmin and a scoped user; the coverage report lists screens still without an article.
