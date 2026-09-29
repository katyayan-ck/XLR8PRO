# AI context — how it's organised and loaded (DEC-031, DEC-086)

One canonical, tool-agnostic directory. Tool-specific files are **generated** from it — edit here, never there.
Guides for humans and agents live in `tech-guides/` (start at `tech-guides/README.md`, the load map).

| Layer | Path | Loaded | Budget |
|---|---|---|---|
| Guidelines | `.ai/guidelines/*.md` | **Always** (Boost merges them into `CLAUDE.md` + `AGENTS.md`) | ≤ 2.5k tokens total; no dates or volatile state (keeps the cached prompt prefix stable) |
| Rules | `.ai/rules/**/*.md` (frontmatter `paths:`) | **Automatically when matching files are touched** (synced to `.claude/rules/`) | ≤ ~120 lines each |
| Skills | `.ai/skills/<name>/SKILL.md` (frontmatter `name`, `description`) | **On demand** by description (Boost installs to `.claude/skills` + `.agents/skills`) | any |
| Guides | `tech-guides/**` | **On demand** — the load map picks the few files a task needs | any |
| DB cards | `.ai/knowledge/db/<prefix>.md` (generated) | On demand (or live via Boost `database-schema`) | generated |
| State | `.ai/state/handoff.md`, `bugs-index.md` (generated) | Read at the start of a work session | small |
| Records | `docs/` (todo, changelog, bugs, decision log) | Grep, never whole | — |
| Backup | `_backup/` (git-ignored) | Never (superseded files; reads denied in `.claude/settings.json`) | — |

## Token hygiene (DEC-086)
- Always-loaded text (`CLAUDE.md` / `AGENTS.md`) is short and **stable**: every session starts with the same prefix,
  so the prompt cache is reused. Put anything that changes daily in `.ai/state/handoff.md`, never in guidelines.
- Rules are path-scoped; long references go to `tech-guides/` and are linked, not inlined.
- Only skills this project uses are installed (`boost.json`); each skill description costs tokens in every session.
- `.claude/settings.json` denies reading `_backup/`, `vendor/`, `node_modules/`, `storage/` and binary workbooks / PDFs.
- Search before reading: grep, then read the lines you need (`offset` / `limit`), not whole large files.

## Regenerate after changes
```bash
php artisan ai:refresh-context      # DB schema cards + bugs index (from docs/bugs/open.md) + sync .ai/rules → .claude/rules
php artisan boost:update            # rebuild CLAUDE.md / AGENTS.md from .ai/guidelines, install .ai/skills
```

## Adding things
- **A rule:** a file under `.ai/rules/` (or `rules/modules/`) with `description:` and `paths:` frontmatter; short and
  factual; link guides instead of inlining them.
- **A skill:** `.ai/skills/<kebab-name>/SKILL.md` with `name` + a trigger-rich `description`.
- **A guide / spec / plan / workflow:** under `tech-guides/` in its folder, plus a row in that folder's README.
- **A decision:** `docs/decisions/decision-log.md` first; regenerate `tech-guides/architecture/decisions-summary.md`.

Never put secrets, customer data or credentials in any of these files.
