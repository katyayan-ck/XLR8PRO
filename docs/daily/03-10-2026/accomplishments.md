# Accomplishments — 03-10-2026

Tasks completed today, with full details (the date-wise copy). The same entries are in `docs/todo.md` Part 2 under
`## 03-10-2026`; append every new entry to **both**.

### W18m (Redis part) — ready for Redis; duplicate-job risk fixed (DEC-095 #33, BUG-230)

**Delivered:**
- The app needs no code change to move cache, sessions and queue to Redis. IT sets five `.env` values per
  environment (guide in `16-reference.md` §4, notes in `.env.example`).
- On the way, fixed BUG-230: the queue gave up on a job after 90 s while pricing imports may run 30 minutes, which with
  two workers would run an import twice. The limit is now 1900 s, and a test keeps it above every job's timeout.

**Verified:** `QueueRetryAfterTest` fails on the old setting and passes on the new.
**Left:**
- Playwright E2E waits for the owner's go to install `@playwright/test` plus a Chromium download.
- IT provides Redis for UAT / production.

### W16b — F1 help on every screen (DEC-094)

**Delivered:** pressing F1 (or the new `?` in the top bar) on any admin screen opens a help pane on the right with that
screen's article, a search box and a link to the Help centre. Articles are Markdown files kept with the code. They can
hide sections from users without a permission, and screens without an article show a friendly "not written yet". The
Help centre lists the articles by module and, for settings managers, how many screens still lack help.
**Verified:** 4 new tests plus 91 related tests; screens checked for superadmin and a scoped user; PHPStan clean.
**Left:**
- W16c page tours (Driver.js), W16d diagnostics, W16e support requests, W16f guide.
- Writing the articles themselves is last (§13).

### W16c — page tours (DEC-094)

**Delivered:** a screen whose help article lists tour steps gets a **Take the tour** button in the F1 pane (also
`?tour=1` in a link). The tour highlights the screen's parts one by one and skips any part the user cannot see. The `?`
button shows a small dot when the screen's help changed since the user last read it. The tour library loads only on
screens that have a tour.
**Verified:** `HelpTest` (5 tests); page smoke for superadmin and user 40.
**Left:** the tours themselves are written with the articles (§13); next is W16d diagnostics.

### End-to-end tests running (DEC-095 #34)

**Delivered:** with Playwright installed by the owner, `npm run e2e` runs the browser smoke in Chromium against the local
app. The sign-in page check passes. The signed-in walk (dashboard → bookings → coming-soon) runs once a test account's
credentials are given in `E2E_USER` / `E2E_PASSWORD`. The owner also ran the KYC masking on the servers; the local
databases show nothing left to mask.
**Left:** a dedicated E2E test account (owner) to enable the signed-in run.

### W16d — diagnostics for support requests (DEC-094)

**Delivered:** every admin page now quietly keeps what support needs when a user asks for help: the last 50 clicks,
form submits (field names only), messages, network calls and script errors in that tab, plus the user's last 50
server requests with any error reference. Personal data (Aadhaar, PAN, mobile, e-mail, tokens) is masked, and nothing a
user types is recorded. A screenshot of the page can be taken with sensitive fields blanked.
**Verified:** new `DiagnosticsTest`; 116 related tests; page smoke for superadmin and user 40.
**Left:** W16e — the support-request form that shows this, builds the zip and opens the ticket.

### W16e — "Still need help?" support requests (DEC-094)

**Delivered:** from the F1 pane a user can send a support request: what they need, how urgent, a description and, by
default, the page's diagnostics with a screenshot preview they can drop.
- **Ticket:** it becomes a ticket owned by the least-busy support admin.
- **Diagnostics zip:** masked, kept privately and readable only by the requester, support admins and the
  executives they assign. Deleted after 90 days.
- **Screen:** Help → Support requests lists them; support admins assign executives there.
- **Permissions:** two new ones, `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC`, held only by superadmin until the owner grants
  them to designations.

**Verified:** `SupportRequestTest`; 123 related tests; pages checked for superadmin and a scoped user.
**Left:**
- The owner grants the two support permissions to designations.
- W16f guide wrap-up; help articles last (§13).

### Support requests reworked to the owner's rules (03-10, DEC-096; BUG-232, BUG-233)

**Delivered:**
- **Ticket page:** diagnostics (screenshot, page, actions, network, errors, server, user, zip) show as a card on the
  ticket page for the support team only — support admins, the owner, assignees and snoopers. The requester never sees
  them and has no remove button: nothing is posted to the conversation.
- **Removed:** the separate Support Requests section.
- **Ticket rules:** support admins see and manage every support ticket; support tickets take only support executives as
  assignees.
- **Switch:** Settings → Support turns the pane's "Still need help?" on or off.
- **My support tickets** (user menu): every user sees their own tickets and can raise a plain one.
- **Screenshots work again:**
  - The library was swapped to html2canvas-pro (the old one failed on every page).
  - The base page is captured sharp, without the help pane or the theme's fade-in.
  - A failed capture is shown and recorded, never silent.
- **E2E smoke:** passes with the owner's test account.

**Verified:**
- `SupportRequestTest` (6), `HelpTest`, `DiagnosticsTest`, platform / IAM suites.
- Real-browser captures (Chromium) on dashboard, My Account, bookings, tickets; light and dark.
- Pages 200 for superadmin and user 40.

**Left:**
- The owner grants `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC` to designations.
- Firefox capture to be re-checked by the owner (no Firefox in the local Playwright).
- W16f, the help-usage log.

### W16f — help usage log and the finished help & support guide (DEC-094)

**Delivered:** the help system now records how it is used: articles opened, screens with no help, searches (and those
that find nothing), tours finished and support requests sent. Search text is masked. Settings managers see a 30-day
report on the Help Centre: totals, most-read articles, screens people wanted help for, failed searches. Old events are
purged after 180 days (a setting). The developer guide is complete, with writing conventions for the help articles.
**Verified:** new tests; 97 related; page smoke for superadmin and user 40.
**Left:**
- W16 is done. Writing the help articles and the user manual (W17) stays last, after QA.
