# Workflow (all AI tools)

**Git:** never work on `main` (working branch `dev/admin`, synced with the team branch `stage`). Commit at checkpoints as
`type(scope): message` (feat, fix, refactor, docs, test, chore, security). **Never push, force-push or rewrite history
without explicit approval in that turn.**

**Stop and ask (never decide alone):** history rewrite/push · any non-local DB operation · destructive changes to real
data (drops with rows, irreversible type changes, mass remaps) · deleting tracked files outside an approved list ·
environment/machine changes · new or major-upgraded dependencies · business-rule ambiguity or locked-spec conflict ·
auth/permission/secret changes · UAT-visible behaviour changes beyond an obvious bug fix.

**Records — update in the same commit as the change (user standing instruction):**
- Decision → `docs/decisions/decision-log.md` (DEC-NNN, append-only, before the change).
- Change → append to `docs/changelog.md` under today's `## YYYY-MM-DD` (files, before → after, reason, DEC/BUG ids).
- To-do → `docs/todo.md` Part 1: move the item's status. Task **completed** → append an accomplishment to Part 2 under
  today's date: what and why (to-do/DEC/BUG ids), files/routes/settings/migrations, how verified, what is left.
- Bugs → check `.ai/state/bugs-index.md` first; a new bug goes to `docs/bugs/open.md` immediately (next BUG-NNN, index
  row + entry); a fixed bug gets its Fixed line and moves, row and full entry, to `docs/bugs/closed.md`. Never delete an
  entry. Then `php artisan ai:refresh-context`.
- Plans → a new approved plan is saved in `tech-guides/frs-and-workflows/plans/` (and its index); when work on a
  plan moves, update its status header and the index row.
- Guides → the matching `tech-guides/` file for any change to a model, service, business rule, screen standard or API.
- Handoff → rewrite `.ai/state/handoff.md`: just done, in progress (exact next step, files, uncommitted work), open
  questions for the owner, how to verify. A new session must be able to continue from it alone.
- **Date-wise copies (user standing instruction):** keep `docs/daily/DD-MM-YYYY/` (today) in step with the cumulative
  files in the same commit — `handoff.md` = `.ai/state/handoff.md`, `changelog.md` = today's changelog entries,
  `accomplishments.md` = today's accomplishments. First commit of a day: create the folder (`docs/daily/README.md`).
- Code is commented (PHPDoc on every class and public method; inline comments only for non-obvious logic, with the
  DEC/BUG id) and formatted with pint before every commit.

**Continuity — resume from exactly where work stopped (user standing instruction):** a crash, a new session or a
different model must be able to continue from the files alone, never from memory. So:
- **On every task completion and every commit** update, in that same commit: the bug files (new / fixed / moved), today's
  accomplishments, the to-do row, the changelog and the handoff (cumulative + `docs/daily/DD-MM-YYYY/`). No commit leaves
  them stale; a task is not "done" until they say so.
- **Before starting a task** mark its to-do row 🟡 in progress and add it to the handoff's *In progress*.
- **During long tasks** (more than one step, or anything running in the background) keep the handoff's *In progress*
  current at each checkpoint: the exact next step, files touched, uncommitted changes, commands / jobs running, what was
  decided and why. Commit or note work in progress before any risky or long operation.
- **To resume:** read `.ai/state/handoff.md` → the named to-do rows → `git status` / `git log -5` → continue at the
  recorded next step; verify before redoing anything.

**Quality gates (every change)**
1. `php -l` on touched files; `vendor/bin/pint --dirty --format agent`.
2. Scoped `vendor/bin/phpstan analyse <files> --memory-limit=2G`.
3. The narrowest tests: `php artisan test --compact --filter=…` (runs on `xlrm_testing`); known failures are in the
   handoff — don't add new ones. Full suite periodically; `php artisan test --group=smoke` only before merges.
4. HTTP smoke of only the touched screens as superadmin **and** a scoped non-superadmin user.

**Database:** schema changes are Laravel migrations (guarded with `Schema::hasColumn/hasTable`, working `down()`), run
locally only; never `dropIfExists` a live table; other environments get migrations via deploy. After local schema
changes migrate the test copy too (`DB_DATABASE=xlrm_testing php artisan migrate`).

**Output:** full files when creating; precise edits when changing; no placeholder code. No new documentation files
unless asked or required by this workflow.
