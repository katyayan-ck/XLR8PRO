# AI Findings — 22-09-2026

## Larastan (`vendor/bin/phpstan analyse`) OOMs on a full-project run in this dev environment

Added a "Development Workflow" rule to `CLAUDE.md`/`AGENTS.md` per explicit user request, mandating
Larastan validation before commits. Tried a full-project run to confirm it actually works here first
— it does not, at default or `--memory-limit=1G`: `VirtualAlloc() failed: [0x000005af] The paging
file is too small for this operation to complete`, followed by `Internal error: Class ... was not
found while trying to analyse it` (a downstream symptom of the OOM, not a real class-resolution
bug). This is the same family of environmental constraint as BUG-034 (`composer dump-autoload`
hanging) — a Windows dev-box resource limit, not a code defect.

**Workaround confirmed working**: scoped runs against a specific directory/file with a higher
`--memory-limit` (e.g. `vendor/bin/phpstan analyse app/Services/Org --memory-limit=2G`) complete
successfully. Used this for the rest of today's session instead of a full-project run.

**Real findings from the scoped run** (not fixed, out of scope for today's Person-system work,
noted here since Larastan surfaced them for the first time): `Division`/`Department`/`Location`/
`Vertical` models trigger `property.notFound` on `$is_active`/`$code` access from
`App\Services\Org\*Service` classes — these properties exist and work fine at runtime (confirmed by
this session's own passing feature tests), this is Larastan/PHPStan not seeing them because these
models have no `@property` PHPDoc block and Larastan's Eloquent extension can't always infer
dynamic/cast attribute types without one. This is a pre-existing gap across most models in this
codebase (not introduced today), not a real bug — flagging since the new mandatory-Larastan
workflow rule will surface it repeatedly going forward. Worth a follow-up task to either generate
`@property` docblocks (e.g. via `barryvdh/laravel-ide-helper`, if the app owner wants to add it) or
add targeted `@property` annotations to the most-touched models, so Larastan's signal-to-noise ratio
improves instead of every future scoped run repeating the same pre-existing noise.

**Recommendation for future sessions**: don't attempt a full `vendor/bin/phpstan analyse` with no
path argument in this environment — always scope it to the touched directory/files and pass
`--memory-limit=2G` (or higher) explicitly.
