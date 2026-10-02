---
paths:
  - 'app/**'
---

# App

## Keep developer guides in step with code (models, services, utilities)
Every change to a model, service, facade, trait, event, job, component or config key must update its developer guide in the same change: tech-guides/modules/*.md for business models and services (and tech-guides/architecture/model-reference.md for new or renamed models), tech-guides/platform/*.md plus tech-guides/platform/16-reference.md for platform utilities (result codes, events, settings, permissions), tech-guides/platform/ui-kit.md for shared UI pieces. New or changed public methods get their signature, return shape and an example; removed methods are removed from the guide; newly found defects are named with their BUG id. A public method that exists in code but not in a guide (or a guide describing behaviour the code no longer has) is a defect in the change.

## Guides are part of every change (user standing instruction, 29-09-2026)
Keep the developer guides, `tech-guides/platform/16-reference.md`, the module rules in `.ai/rules/modules/`, the data
dictionary (`tech-guides/architecture/data-dictionary*.md`) and the API docs (`tech-guides/api/`, OpenAPI annotations) up to date on **every** change
to a model, service, module, business rule or API — in the same commit, not later.

## One error, response and exception pipeline (user standing instruction, 29-09-2026; extended by DEC-097)
- **Target (DEC-097, W19b):** every response / error code lives in one registry structured Module → Process → Activity
  (numeric `project_response_code` + lang message), used by both web results and the API format (W19a).
- **Business failures are `App\Support\Result`.** Carry a module-scoped code (`{MODULE}_{NAME}`, e.g.
  `PRICING_NOT_FOUND`, `SLS_QUOTE_GATE`) and a message from the module's lang file, so the message is customisable per
  module without code changes.
- **Error codes:**
  - Every code is registered once in `App\Enums\ErrorCodeEnum` (with an HTTP status + default message) or in its
    module's code list.
  - Never invent a code inline.
  - Never reuse a code for a different meaning.
- **Exceptions go through the central handler:**
  - `bootstrap/app.php` → `withExceptions`, using `BaseController::handleException()` for the API envelope and the
    branded error pages for the web.
  - Controllers do not `try / catch` just to re-shape an error.
  - Log with context (ids only, no PII).
- **API responses:** always the envelope `{http_status, success, code, message, data}`. The web shows `Result->message`
  in the page alert.
- **Admin flash messages** (`Alert::*`, `->with('success'|'error'|'warning'|'info', …)`) never carry typed wording: use
  `__('{module}.flash.{key}', [...])` from `resources/lang/en/{module}.php` (`utils.php` for Utilities / imports), or a
  `Result->message` (to-do W6). `tests/Unit/Lang/FlashMessagesLangTest` checks every key and placeholder. A caught
  exception's text goes through `App\Support\ErrorRef::userMessage($e)`, never `$e->getMessage()` (SQL / PHP errors
  become a reference id).

## Database access only through Eloquent (DEC-093, owner standing instruction 01-10-2026)
**No `DB::` queries (DEC-093, owner 01-10-2026):** every database read and write goes through an Eloquent model (entity writes through its entity service, DEC-050) — never `DB::table()`, `DB::select()`, `DB::statement()`, `DB::raw()` or `DB::connection()`. Allowed: transaction control (`DB::transaction()`, `beginTransaction` / `commit` / `rollBack`). Raw SQL fragments use the builder's `selectRaw` / `whereRaw` / `orderByRaw` / `havingRaw`; a table without a model gets one (extends `BaseModel`; `Model` only for pivots / logs without audit columns). Migrations are exempt (they must not depend on models), and so is schema tooling that reads `information_schema` (the guard's `EXEMPT` list, DEC-095 #21). Enforced by `tests/Unit/Architecture/NoDbFacadeQueriesTest` — a ratchet on `db-facade-baseline.json`: no file may add a use; converted files lower their count (to-do W15).
