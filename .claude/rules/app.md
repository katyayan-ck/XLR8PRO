---
paths:
  - 'app/**'
---

# App

## Keep developer guides in step with code (models, services, utilities)
Every change to a model, service, facade, trait, event, job, component or config key must update its developer guide in the same change: docs/domains/*.md for business models and services (and docs/domains/reference.md for new or renamed models), docs/utilities/*.md plus docs/utilities/16-reference.md for platform utilities (result codes, events, settings, permissions), docs/utilities/ui-kit.md for shared UI pieces. New or changed public methods get their signature, return shape and an example; removed methods are removed from the guide; newly found defects are named with their BUG id. A public method that exists in code but not in a guide (or a guide describing behaviour the code no longer has) is a defect in the change.

## Guides are part of every change (user standing instruction, 29-09-2026)
Keep the developer guides, `docs/utilities/16-reference.md`, the module rules in `.ai/rules/modules/`, the data
dictionary (`docs/reference/data-dictionary*.md`) and the API docs (OpenAPI annotations) up to date on **every** change
to a model, service, module, business rule or API — in the same commit, not later.

## One error, response and exception pipeline (user standing instruction, 29-09-2026)
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
