# Workflow — making a change in this repo

The loop every agent follows (full rules: `.ai/guidelines/10-workflow.md`, always loaded via `CLAUDE.md` / `AGENTS.md`).

1. **Orient:** `.ai/state/handoff.md` → the task's rows in `docs/todo.md` → `.ai/state/bugs-index.md` (known bugs in
   the area) → the guide for the area (`tech-guides/README.md` load map). Read the real files before changing them.
2. **Decide:** a new decision → `docs/decisions/decision-log.md` (DEC-NNN) **before** the change. Stop and ask for:
   business-rule ambiguity or a locked-spec conflict, auth / permission / secret changes, destructive data changes,
   non-local DB operations, new dependencies, deleting tracked files, UAT-visible behaviour beyond an obvious fix,
   pushes / history rewrites.
3. **Build:** controller → service → model; entity writes only through the entity service; `Result` for business
   failures; the API envelope; labels from `resources/lang/en/{module}.php`; schema changes as guarded migrations with
   a working `down()`.
4. **Verify:** `php -l`; `vendor/bin/pint --dirty --format agent`; scoped phpstan; the narrowest tests
   (`php artisan test --compact --filter=…`, on `xlrm_testing`); a smoke of the touched screens as superadmin (user 1)
   and a scoped user (user 40). Full suite periodically; `--group=smoke` only before merges.
5. **Record (same commit):**
   - the guide for what changed (`tech-guides/…`);
   - `docs/changelog.md` (append under today's `## YYYY-MM-DD`: files, before → after, reason, DEC / BUG ids);
   - `docs/todo.md` (the item's status; an accomplishment entry when a task completes);
   - the plan's status header in `tech-guides/frs-and-workflows/plans/` when the work belongs to a plan (new approved
     plans are saved there);
   - `docs/bugs/open.md` for a new bug (immediately), or move a fixed one to `docs/bugs/closed.md` with its details;
   - rewrite `.ai/state/handoff.md` (done / in progress / open questions / how to verify);
   - the same entries in today's date-wise files `docs/daily/DD-MM-YYYY/{handoff,changelog,accomplishments}.md`
     (create the folder on the day's first commit — `docs/daily/README.md`);
   - `php artisan ai:refresh-context` when bugs, rules or schema changed.
6. **Commit:** `type(scope): message` on the working branch (never `main`); never push without approval in that turn.
