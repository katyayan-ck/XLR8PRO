# FRS and workflows — for agents

The requirement documents (FRS / locked specs) and short workflow cards. **Read the workflow card first** (1–2 pages);
open an FRS only for the section the task touches, never whole.

**Precedence:** a **locked** spec beats informal chat and older rules. If code or a request conflicts with a locked spec,
stop and ask. Decisions recorded later in `docs/decisions/decision-log.md` (DEC-NNN) amend a spec only where they say
so (e.g. DEC-073 amends pricing spec §3.3).

## Workflow cards (`workflows/`)

| Card | Covers | Detail guide |
|---|---|---|
| [pricing-process.md](workflows/pricing-process.md) | The 11-step Pricing Process (gate → publish → `getPricing`), automatic recalculation, holds | `modules/pricing.md` |
| [sales-lifecycle.md](workflows/sales-lifecycle.md) | Lead → enquiry → quotation (priced, approved) → booking → work-lists → delivery / refund; statuses | `modules/crm-enquiry-quotation.md`, `modules/sales-booking.md` |
| [approvals.md](workflows/approvals.md) | The approval engine: topics, rules, levels, counters, "highest level wins" | `platform/07-approvals.md`, `08-…` |
| [change-workflow.md](workflows/change-workflow.md) | How any change is made here: decision → code → tests → guides → logs → commit | `.ai/guidelines/10-workflow.md` |

## Plans (`plans/`)

The approved design plans (DEC-071 data scoping, DEC-072 My Account + dashboard, DEC-073 pricing redesign, DEC-083
pricing masters), each with a status header kept current — index and open items: [plans/README.md](plans/README.md).

## FRS / specs (`frs/`)

| Area | Document | Status |
|---|---|---|
| Vehicle pricing (machine rules) | [frs/pricing-machine-spec-v3.1.md](frs/pricing-machine-spec-v3.1.md) | **Locked** v3.1.1 (31-08-2026); §3.3 amended by DEC-073; GAP-01…11 open until instructed |
| Vehicle pricing (human guide) | [frs/pricing-human-guide-v3.1.md](frs/pricing-human-guide-v3.1.md) | reference |
| Sales: enquiry → quotation → booking | [frs/sales-combined-frs.md](frs/sales-combined-frs.md) | FRS (Quotation v1.0, July 2026) |
| Person / user identity | [frs/person-system-frs.md](frs/person-system-frs.md) | FRS (25-07-2026) |
| Platform utilities (Settings, Notify, Chat, Docs, Task, Ticket, Approval, Topics, Templates, Email, SMS, WhatsApp, Telephony) | [frs/platform-utilities-frs.md](frs/platform-utilities-frs.md) | FRS v1.1 (26-09-2026); the approval engine §7–8 is the spec |
| Support utilities (knowledge base, conversations, ticket extensions) | [frs/support-utilities-requirements.md](frs/support-utilities-requirements.md) | requirements draft (its "confirmed packages" section is not accurate for this repo) |
| Help & support (F1 help, tours, support requests with diagnostics, user manual) | [frs/help-and-support-frs.md](frs/help-and-support-frs.md) | approved for build (DEC-094, W16 / W17) |
| Accessory catalog | [frs/accessory-catalog-frs.md](frs/accessory-catalog-frs.md) | FRS + dev guide (01-08-2026; the importer is now the typed-sheet one, DEC-083) |
| Shared services health | [frs/shared-services-audit-24-09-2026.md](frs/shared-services-audit-24-09-2026.md) | audit snapshot (24-09-2026); links to `.ai/rules/*` files it names are archived |
| Formats and data dictionary | [../architecture/data-dictionary-draft.md](../architecture/data-dictionary-draft.md) | draft — waits for the owner's sign-off (to-do §2) |

Old AI-context copies of the pricing spec (`PRICING_AI_CONTEXT.md`, `AISERVICES.md`, `pricing-module-inputs.txt`) and
the reference workbooks were superseded and moved to `_backup/docs/reference/pricing/`.
