# AI context — how it's organised and loaded

One canonical, tool-agnostic directory. Tool-specific files are **generated** from it — edit here, never there.

| Layer | Path | Loaded | Budget |
|---|---|---|---|
| Guidelines | `.ai/guidelines/*.md` | **Always** (Boost merges them into root `CLAUDE.md` + `AGENTS.md`) | ≤ 2.5k tokens total |
| Rules | `.ai/rules/**/*.md` (frontmatter `paths:`) | **Automatically when matching files are touched** (synced to `.claude/rules/`) | ≤ ~120 lines each |
| Skills | `.ai/skills/<name>/SKILL.md` (frontmatter `name`, `description`) | **On demand** by description (Boost installs to `.claude/skills` + `.github/skills`) | any |
| Knowledge | `.ai/knowledge/**` | **Only when a rule/skill/task links it** | any |
| DB cards | `.ai/knowledge/db/<prefix>.md` (generated) | On demand (or live via Boost `database-schema`) | generated |
| State | `.ai/state/current.md`, `bugs-index.md` (generated) | Read at the start of a work session | small |
| Archive | `.ai/_archive/2026-09-26/**` + `MANIFEST.md` | Never (history only) | — |

## Regenerate after changes
```bash
php artisan ai:refresh-context      # DB schema cards + bugs index + sync .ai/rules → .claude/rules
php artisan boost:update            # rebuild root CLAUDE.md / AGENTS.md from .ai/guidelines, install .ai/skills
```

## Adding things
- **A rule:** new file under `.ai/rules/` (or `rules/modules/`) with `description:` and `paths:` frontmatter; keep it short and factual; link knowledge instead of inlining it.
- **A skill:** `.ai/skills/<kebab-name>/SKILL.md` with `name` + a trigger-rich `description`.
- **Knowledge:** long references, specs, runbooks under `.ai/knowledge/`; link them from the rule that needs them.
- **A decision:** `docs/decisions/decision-log.md` first; summarise locked ones in `.ai/knowledge/decisions.md`.

Never put secrets, customer data or credentials in any of these files.
