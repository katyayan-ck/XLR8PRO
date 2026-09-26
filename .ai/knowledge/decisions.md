# Locked decisions (summary — full record in `docs/decisions/decision-log.md`)

| DEC | Decision |
|---|---|
| 001 | Two tracks: Track A stabilises this app for UAT; Track B = greenfield `xceler8` + ETL switch-over |
| 002 | Track B UI = Filament 5 + Livewire 4; this app stays Backpack |
| 003 | Full DB normalisation, manifest-driven, with automated code fixing + `LegacyAttributes` safety net |
| 004 | API v1 contract preserved for the mobile app; v2 alongside in Track B |
| 005 | PHP 8.4 (done); technology chosen on merit, no downgrades |
| 006 | Post-UAT: DB-level audit (triggers/SPs/DB users), self-hosted AI, real comms vendors |
| 007 | Auto mode; every decision logged; manual approval for high-risk items |
| 011 | Tests run on `xlrm_testing` (full local copy) |
| 018 | Spatie roles = designations; Role screen retired (→ Designation) |
| 019 / 026 | PHP 8.4.26 via Laragon; WAMP/XAMPP retired |
| 022 | `admin.dashboard` granted to every designation |
| 023 | Quotation Pending / Enquiry Erroneous hidden until Track B |
| 025 | BUG-104 booking columns added (types approved) |
| 028 | Support utilities adopted: Knowledge Base, conversations (all variants), extended tickets + SLA calendars |
| 029 | Chat = all variants; KB = internal + customer-facing; SLA/retention decided later; VOTF branch = enquiry → FSC |
| 030 | Dead-code purge (reference-checked) |
| 031 | This AI-context structure |
| — | Approval Engine: FRS v1.1 §7–8 is the spec; parallel counter-offer model (highest level wins; approvers never reject) |
| — | Vehicle Pricing Machine Spec v3.1.1 locked; its GAP-01…11 stay open until instructed |

Open decisions: ticket intake channels (SUP-DEC-003, needs explanation), SLA targets/calendars, retention,
enabling data scoping, approval scope dimensions (company/zone/state/desk), gscreds.json history purge.
