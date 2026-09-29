# Architecture — how the app is built

| Guide | Read it when | Covers |
|---|---|---|
| [core.md](core.md) | Writing any model / service / import | `BaseModel`, `User`, traits (`HasColumnTransformations`, `HasTreeStructure`, `HasDataScope`), **entity services** (`EntityService` + `Field`, the only write path, DEC-050), `Result`, `site_date()` |
| [model-reference.md](model-reference.md) | Finding which model / service owns a table | Every model → table → writer → guide |
| [legacy-utils.md](legacy-utils.md) | Touching lookups or old helpers | `KeywordValueService` + KeyValue writers, `SynonymService`, `IdentifierService`, the legacy settings stack |
| [data-dictionary-draft.md](data-dictionary-draft.md) | Codes, keys, formats | Proposed formats for every code family (draft; the owner's sign-off pending, to-do §2) |
| [decisions-summary.md](decisions-summary.md) | "Why is it like this?" | The locked decisions in one table; full text in `docs/decisions/decision-log.md` |

**Also always true** (details in `CLAUDE.md` / `AGENTS.md`, from `.ai/guidelines/20-architecture.md`):
- **Layers:** controller → service → model; controllers stay thin.
- **Permissions** `{MOD}_{PROC}_{ACT}`; module / process codes in `.ai/rules/admin-backpack.md`.
- **API envelope** + central error rendering (`App\Exceptions\ApiExceptionRenderer`, messages in
  `resources/lang/en/errors.php`, DEC-085).
- **Jobs** set `$timeout`, `$tries` and implement `failed()`.
- **DB schema:** the generated cards in `.ai/knowledge/db/{prefix}.md` (`php artisan ai:refresh-context`).
