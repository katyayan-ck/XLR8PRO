# Help & Support utility — FRS (DEC-094, to-do W16 / W17)

> **Status (01-10-2026): 📝 approved for build** — owner request 01-10; owner answers recorded in DEC-094. Build order:
> after W15a. Plan: [`../plans/2026-10-01-help-and-support-DEC-094.md`](../plans/2026-10-01-help-and-support-DEC-094.md).
> Extends [support-utilities-requirements.md](support-utilities-requirements.md) §5 (knowledge base) and §6 (helpdesk);
> the helpdesk itself is the existing ticket engine ([platform/06-tickets.md](../../platform/06-tickets.md)).

## 1. Purpose
Give every signed-in user help **where they are**:
1. **F1 help:** press F1 (or the `?` button in the top bar) on any admin screen → a pane slides in from the right with
   the full help for that screen.
2. **Page tour:** on demand, a step-by-step tour highlights the screen's parts.
3. **More help → support request:** a floating button in the pane opens a support-request form. The system gathers a
   diagnostic bundle (screenshot, page, recent actions, network calls, errors, user / access / scope details) as a
   zip on the new ticket. The ticket goes to the support admin, who assigns a support executive; the normal ticket
   routine resolves it.
4. **User manual:** the same articles, browsable by module → process → screen, with screenshots, printable.

## 2. Actors and permissions
| Actor | Permission | Can |
|---|---|---|
| Any signed-in user | — (only a login) | open help, search help, take tours, send support requests, see own requests |
| Support admin | `UTL_SUPP_ADMIN` (new) | receives every new support ticket as owner; assigns / reassigns executives; sees all support tickets and bundles |
| Support executive | `UTL_SUPP_EXEC` (new) | can be assigned; works assigned tickets; downloads their bundles |
| Ticket desk (existing) | `UTL_TCKT_DESK` | unchanged (IT / process tickets) |

Help text shows only the sections a user's permissions allow (a section for approvers is hidden from others). New
permissions are minted by migration and granted to superadmin only; the owner attaches them to designations.

## 3. F1 contextual help
### 3.1 Behaviour
- **Open:** F1 anywhere on an admin page (the browser's own F1 help is suppressed), the `?` top-bar button (touch /
  phone), or `Shift+?`. **Close:** Esc, the close button, or F1 again. Focus moves into the pane and back on close.
- **Pane** (right side, 420 px; full width on a phone): title, "What this screen is for", sections, field glossary,
  common tasks (numbered steps), rules / validations in plain words, FAQs, related screens (links), last-updated date.
  Buttons: **Take the tour** (when the screen has one), **Search help**, **Open the manual**, **Still need help?**
  (floating, bottom of the pane).
- **Which article:** the current route name (`sales.booking.edit`) → the article that lists it (one article may serve
  list / create / edit / show). No article → the module / process overview, else a "no help written yet" page with the
  support button (and the miss is logged so writers see the gaps).
- **Permission sections:** `::: can SLS_BKNG_APPROVE` … `:::` blocks render only for users with that permission.
- **Search:** titles, headings and text of articles the user can open; results link into the pane.
- **Speed:** articles are rendered once and cached per article + permission set; the pane loads with one request
  (target < 150 ms server time). No help data is loaded until the first F1.

### 3.2 Content source (own engine, not Laradocs)
- Articles are Markdown files **in the repository**, versioned with the code they describe:
  `resources/help/{module}/{process}/{screen}.md`, plus `_module.md` / `_process.md` overviews.
- Front matter:
  ```yaml
  title: Booking — edit
  routes: [sales.booking.edit, sales.booking.update]
  module: SLS
  process: BKNG
  permissions: [SLS_BKNG_VIEW]      # who may open it (any of)
  updated: 2026-10-01
  tour:                              # optional, see §4
    - { element: '#booking-customer', title: 'Customer', text: 'Name, mobile …' }
  ```
- Why not Laradocs: Laradocs serves the developer guides (`tech-guides/`) behind `/docs`. It has no route-to-page link,
  no permission filtering and no in-app pane, so it stays for developers.

## 4. On-demand page tour
- **Take the tour** in the pane (or `?tour=1` on the URL) runs the article's `tour` steps with **Driver.js** (MIT,
  ~5 KB, served locally through Basset). Each step highlights one element with a title and text; Next / Back / Done.
- Steps whose element is not on the page (hidden by permission or state) are skipped. Screens mark stable targets with
  `data-xl-tour="name"` (preferred over CSS ids).
- The tour never changes data. It is offered, never forced; a small "new" dot on `?` marks a screen whose article
  changed since the user last opened it.

## 5. Still need help? — support request
### 5.1 Form (Help → Support request)
Prefilled, editable: **What do you need?** (How do I…? / Something is not working / Wrong data / Access or permission /
Suggestion), **How urgent?** (Normal → P3, Urgent — I cannot work → P2), **Subject** (default: screen title),
**Describe the problem** (required), **Attach diagnostics** (on by default; shows what is included, with the
screenshot preview; the user can remove the screenshot or untick diagnostics), extra files (optional).

### 5.2 Diagnostic bundle (zip, attached to the ticket)
| File | Contents |
|---|---|
| `screenshot.png` | the page as the user saw it when they pressed F1 (html2canvas); password / OTP fields and fields marked sensitive (`data-xl-sensitive`: Aadhaar, PAN, bank account) are blanked before capture |
| `page.json` | URL (query tokens removed), route name, page title, screen size, browser, time, app version (commit), theme / density |
| `actions.json` | the last 50 actions in this tab: page loads, clicks (button / link label and selector — never typed values), form submits (field **names** only), tab / filter changes, validation messages and alerts shown |
| `network.json` | the last 50 AJAX / fetch calls: method, URL, status, time taken, size; the error body (first 2 KB, masked) for 4xx / 5xx |
| `errors.json` | JavaScript errors and `console.error` lines on this page |
| `server.json` | the user's last 50 server requests (route, status, time, error reference) from a short-lived trail, and the log lines of those error references (masked) |
| `user.json` | user id, name, employee code, designation, roles, permission count + list, denials, data scopes (DEC-071), branch / location, last login |

**Privacy rules (mandatory):**
- never capture typed values, passwords, OTPs, tokens, cookies or session ids;
- mask mobile (`98XXXXXX12`), e-mail, PAN, Aadhaar and bank numbers in every text file;
- the bundle is readable only by the requester, the support admin(s) and the assigned executive;
- it is deleted after `support.bundle_retention_days` (Settings, default 90).

### 5.3 Routing
- The support request becomes a ticket (`Ticket::open`): category from "What do you need?", priority from urgency,
  `ref_type = PAGE` with the route name, bundle as its attachment.
- **Owner:** the `UTL_SUPP_ADMIN` holder with the fewest open support tickets (ties → longest idle).
- The support admin assigns one or more `UTL_SUPP_EXEC` users (assignee picker limited to them).
- From then on it follows the ticket routine: SLA by priority, WAITING_USER pauses the clock, remarks, RESOLVED →
  requester closes or reopens, auto-close. The requester sees it under Help → My support requests (and Tickets).

## 6. User manual
- **Help centre** screen (`utils.help.index`): browse module → process → screen; search; print / save as PDF (print
  stylesheet). Same articles as F1 (one source), permission-filtered.
- **Screenshots:** a dev-only Playwright script signs in on `xlrm_testing` as a superadmin and as a scoped user, opens
  every screen that has an article, and saves `public/help/img/{screen}.png`. Before saving it masks mobiles, e-mails,
  PAN, Aadhaar and bank numbers, and blurs person-name cells marked `data-xl-pii`. It is re-runnable when screens change.
  Playwright is a dev dependency only (never deployed).
- **Writing:** the agent drafts every article from the code, FRS and workflow cards. Process owners review the business
  wording. An article is marked `reviewed: <name, date>` once checked.

## 7. Non-functional
- Keyboard and screen-reader accessible (focus trap in the pane, `aria-live` for search results); works at phone width.
- No new page weight until first use: help JS ~6 KB; Driver.js / html2canvas load on demand.
- Every help / support action is logged (who opened which article, searches with no result, tours finished, requests
  sent) for a small "help usage" report.
- Entity writes through services (DEC-050); database access through models only (DEC-093).

## 8. Acceptance criteria
1. F1 on any screen with an article opens that article in < 1 s; Esc closes it; F1 does not open the browser help.
2. A permission section is invisible to a user without that permission.
3. A tour runs its steps, skips missing elements and never submits anything.
4. "Still need help?" → the form shows the screenshot preview. Submitting creates a ticket owned by a support admin,
   with a zip holding the files of §5.2 and no password / OTP / typed value / unmasked PAN, Aadhaar or mobile.
5. Only the requester, support admins and assigned executives can download the bundle; it is purged after the
   retention period.
6. The support admin can assign only `UTL_SUPP_EXEC` holders; the ticket then follows the ticket routine.
7. The Help centre lists every article the user may open, grouped module → process → screen, and prints cleanly.
8. Each admin screen has an article (coverage report lists the missing ones).
