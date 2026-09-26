# XCELR8 Project — AI Guidelines

## Project Overview

**XCELR8 (Xceler8 / XLR8 / XLRM)** is BMPL's dealer management system.  
**Stack:** Laravel 12, PHP 8.3, Backpack 7, MySQL/MariaDB, Spatie Permission, Sanctum.

## Core Behavior for All AI Agents

1. **Read `.ai/rules/index.md` first** — it is the master index. Always load the relevant rule files before generating code.
2. **Open real service files** before generating code for any service — match its public contract exactly.
3. **Services over controllers** — all business logic goes in dedicated service classes.
4. **Never use `Model::all()`** on large tables.
5. **SQL first by default** — deliver raw SQL unless user explicitly requests Laravel migration.
6. **Full complete files** — no `// rest unchanged` unless user asks for a patch.
7. After every sprint/feature: offer `php artisan changelog:add` and `php artisan laradocs:make`.

## Do NOT Generate

- Filament resources (Backpack 7 is the admin panel)
- `KeywordHelper` usage (deprecated, use `KeywordValueService`)
- Raw phone/PAN/Aadhaar sanitization (use `PersonService`)
- New parallel service for something already listed in `.ai/rules/services.md`
- SQL FK constraints on new module tables
- Model::all() or SELECT * without constraints on large tables
- Approval Engine SQL/schema/code unless user explicitly requests it

## Key Spec References

- Vehicle Pricing: Xceler8 Vehicle Pricing Machine Spec v3.1.1 (locked 2026-08-31)
- Approval Engine: Final Behavioural Specification + Addendum A (locked 2026-08-23/24)
- Quotation: FRS v1.0 (July 2026)
- Person/User: FRS 2026-07-25

Locked spec beats informal chat. Always.
