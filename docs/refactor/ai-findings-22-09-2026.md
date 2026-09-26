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

## Major architectural finding: Sales controllers violate the new DRY/SSOT Model-Service-Controller rule

Per the user's newly-recorded rule (`.ai/rules/architecture.md`, "DRY/SSOT layering: data ops in
Models, business logic in Services, thin Controllers"), checked how the three Sales controllers
audited today against the FRS actually stack up:

- `BookingCrudController.php` — **14,645 lines**
- `EnquiryCrudController.php` — **2,743 lines**
- `QuotationCrudController.php` — **2,673 lines**
- Total: **20,061 lines**, almost entirely inline in the controllers — validation, pricing/discount
  math, status-transition logic, history writes, cross-model orchestration (Quotation↔Booking↔
  Enquiry↔XFinance↔XlInsurance↔XlRto), grid-building, and PDF assembly all live directly in
  controller methods.
- The only Service-layer presence for this whole domain is `app/Services/BookingStateService.php`
  (60 lines, one method — `transitionTo()`).

This is a large, pre-existing violation of the newly-recorded rule, not something introduced today.
**Not attempted as part of today's audit** — extracting 20k lines of revenue-critical Sales logic
into proper Model/Service layers is a multi-session architectural refactor in its own right, not a
"clean up while you're in there" change, and the repo's own non-negotiable rules require a dedicated
`refactor/*` branch, full-file changes one module at a time, and explicit sign-off before large
changes to code this size and this critical. Flagging here so it's on record rather than silently
skipped, and so a future session can pick it up as its own scoped effort (Booking first, since it's
by far the largest) if/when the user wants to prioritize it.
