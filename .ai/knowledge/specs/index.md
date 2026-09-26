# Specifications index (read the one you need; never inline them into prompts wholesale)

Locked specs beat informal chat and beat older rules. If code or a request conflicts with a locked spec, stop and ask.

| Area | Document | Status |
|---|---|---|
| Vehicle pricing (machine rules) | `docs/reference/pricing/Xceler8_Vehicle_Pricing_Machine_Spec_v3.1.md` | **Locked** v3.1.1 (2026-08-31) |
| Vehicle pricing (human guide) | `docs/reference/pricing/Xceler8_Vehicle_Pricing_Human_Guide_v3.1.md` | reference |
| Pricing AI context / services | `docs/reference/pricing/PRICING_AI_CONTEXT.md`, `AISERVICES.md` | reference (partly stale — rules win) |
| Sales: enquiry → quotation → booking | `docs/reference/Sales-Combined_FRS.md` | FRS (Quotation v1.0 July 2026) |
| Person / user identity | `docs/reference/Person-System.md` | FRS 2026-07-25 |
| Platform utilities (Settings, Notify, Chat, Docs, Task, Ticket, Approval, Topics, Templates, Email, SMS, WhatsApp, Telephony) | `docs/refactor/Platform-Utilities-FRS.md` | FRS v1.1 (2026-09-26) — **approval engine §7–8 is the spec** (lock lifted, DEC-001/plan) |
| Support utilities (KB, conversations, ticket extensions) | `docs/refactor/missing-info-utilities.md` | requirements draft (its "confirmed packages" section is not accurate for this repo) |
| Accessory catalog | `docs/dev-guide/Vehicle_Accessory_Catalog_FRS_and_Dev_Guide.md` | FRS + dev guide |
| Shared services health | `docs/reference/Shared-Services-Utilities-Catalog.md` | audit (24-09-2026) |
| Programme plan & decisions | `docs/decisions/decision-log.md` (DEC-NNN) | authoritative log |
| Older module docs | `docs/modules/*`, `docs/api/*`, `docs/database/*` | may be stale — verify against code |
