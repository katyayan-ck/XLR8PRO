# Workflow (all AI tools)

**Git**
- Never work on `main`. Branches: `feature/*` or `refactor/*` (working branch: `dev/admin`, kept in sync with `stage`, the shared team branch).
- Commit at checkpoints with `type(scope): message` (feat, fix, refactor, docs, test, chore, security).
  **Never push, force-push or rewrite history without explicit approval in that turn.**

**Stop and ask (never decide alone)** — the user approves high-risk items explicitly:
history rewrite/push · any non-local DB operation · destructive changes to real data (drop tables/columns
with rows, irreversible type changes, mass remaps) · deleting tracked files outside an approved list ·
environment/machine changes · new or major-upgraded dependencies · business-rule ambiguity or locked-spec
conflict · auth/permission/secret changes · UAT-visible behaviour changes beyond an obvious bug fix.

**Logging (mandatory)**
- Decision → `docs/decisions/decision-log.md` (DEC-NNN, append-only, written before the change).
- Change → `docs/refactor/ai-changelogs-DD-MM-YYYY.md` (files, before → after, reason, DEC id).
- New bug found anywhere → `docs/refactor/known-bugs-report.md` immediately (BUG-NNN; never delete
  entries; update status in place; keep the index table current). Check `.ai/state/bugs-index.md` first.

**Quality gates (every change)**
1. `php -l` on touched files; `vendor/bin/pint --dirty --format agent`.
2. Scoped `vendor/bin/phpstan analyse <files> --memory-limit=2G`.
3. `php artisan test --compact` (or the narrowest relevant `--filter`) — runs on `xlrm_testing`.
   Known pre-existing failures are listed in `.ai/state/current.md`; don't add new ones.
4. HTTP smoke of **only the touched screens** as superadmin **and** a scoped non-superadmin user (seconds). Run the full suite periodically, and the full screen sweep (`php artisan test --group=smoke`, a few minutes) only before merges — never after every change.

**Database**
- Schema changes are **Laravel migrations** (guarded with `Schema::hasColumn/hasTable`, working `down()`),
  run on local only. Never `dropIfExists` a live table. Other environments get migrations via deploy.
- Refresh the test copy after local schema/data changes: `php artisan testing:refresh-db --force`.

**Output**
- Full files when creating; precise edits when changing. No placeholder "rest unchanged" code.
- Don't create documentation files unless asked or required by this workflow.
