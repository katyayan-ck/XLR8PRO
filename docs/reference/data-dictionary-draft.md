# Data dictionary: codes, keys and their formats (DRAFT for sign-off)

**Status:** DRAFT, 29-09-2026 (go-live to-do §2, steps F1–F3). Nothing is changed until you approve this. After sign-off
it becomes `docs/reference/data-dictionary.md`, a `CodeFormat` registry in code (F4), and every write validates
through it.

**Inventory:** a read-only script over the local `xlrm` database and the code (routes, enums, config).

**Style names used below:**
- `UPPER_SNAKE` = `ABC_DEF`;
- `UPPER-HYPHEN` = `ABC-DEF`;
- `lower.dotted` = `abc.def`.

## Summary of the audit

| Family | Rows | Today | Problem | Proposed |
|---|---|---|---|---|
| Permission | 275 | 171 `UPPER_SNAKE` · 88 `lower.dotted` (legacy, 850 grants) · 16 other (`user_type.view`, `*`) | Two conventions, duplicates (`rto.import` / `RTO_IMPORT`), wildcard `*` | `{mod}.{proc}.{act}` lower-case (below) |
| IAM module code | 13 (+2 junk soft-deleted) | `UPPER_SNAKE`, 3–6 chars | `DEMO_MODULE`, `DEMOMODULE2`, `LEGACY` | `^[A-Z]{2,5}$` |
| IAM process code | 57 | `UPPER_SNAKE`, 3–8 chars | — | `^[A-Z0-9]{2,8}$` |
| Activity | 35 distinct | VIEW 47, CREATE 24, EDIT 23, MANAGE 21, DELETE 18, + 30 business steps | Free text | A fixed dictionary (below) |
| Designation (Spatie role) | 76 | `code` `ACC_EXE` style ✅; `name` = display with spaces | Code is fine; `name` is used as the role name | Key = `code` `^[A-Z]{2,5}_[A-Z]{2,5}$`; name is free text |
| Branch / department / division / vertical | 3 / 7 / 25 / 2 | `UPPER_SNAKE` 2–6 chars | — | `^[A-Z0-9]{2,6}$` |
| Location | 16 | 15 `BKN` style + `LMM-WS` | One hyphenated | `^[A-Z0-9]{2,6}(-[A-Z0-9]{2,4})?$` |
| **Person code** | 215 | **160 Aadhaar numbers, 51 PANs**, 4 surrogates | **BUG-206: government IDs as a key** | `PRS-` + 6 digits (surrogate), with a remap |
| Employee code | 200 | `BMPL-0018` | — | `^[A-Z]{2,6}-[0-9]{4,6}$` (company prefix + number) |
| Segment / sub-segment | 4 / – | `PV`, `CV`, `BEV`, `LMM` | — | `^[A-Z]{2,5}$` |
| Vehicle model code | 68 | 54 `SCORPIO-N`, 14 `XUV700` | Consistent under DEC-049 | `^[A-Z0-9]+(-[A-Z0-9]+)*$`, ≤ 30 (DEC-049) |
| Variant code (full OEM code) | 3,518 | 13–18 chars alphanumeric | Colour-suffix split (BUG-173 / 199) | `^[A-Z0-9]{13,20}$`, last 2 = colour code |
| Keyword master code | 133 | 109 `UPPER_SNAKE`, 24 `UPPER-HYPHEN` | **5 duplicate pairs**: `SUB_SEGMENT` / `SUB-SEGMENT`, `FUEL_TYPE` / `FUEL-TYPE`, `BODY_MAKE` / `BODY-MAKE`, `BODY_TYPE` / `BODY-TYPE`, `INS_TYPE` / `INS-TYPE` | `^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$` ≤ 40, merge the pairs |
| Keyword value code | 5,459 | 3,440 `UPPER_SNAKE`, **1,903 with spaces**, 40 hyphen, 31 other (`GST%`, `_`, `SCV-RSO_SCV-RSO`) | Duplicates (`MODEL_CODE` / `MODEL CODE`, `GST%` / `GST %`, `GROUP1` / `GROUP_1`) | `^[A-Z0-9]+([_-][A-Z0-9]+)*$` ≤ 40; the label keeps the text |
| Setting key | 42 seeded (11 stored) | `lower.dotted` ✅ | — | `^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$` |
| Route name | 650 | 648 `lower.dotted` (with `-`) ✅ | 2 vendor routes | `^[a-z0-9-]+(\.[a-z0-9-]+)+$` (vendor exempt) |
| Error / Result code | 55 | `UPPER_SNAKE` ✅ | — | `^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$` |
| Entity type (platform) | 15 | `QUOTE`, `WA_THREAD` ✅ | — | `UPPER_SNAKE` ≤ 20 |
| Pricing sheet code / field code | 282 | `UPPER_SNAKE` / `lower_snake` ✅ | — | Keep |
| Insurance add-on / company code | 17 / – | `UPPER_SNAKE` ✅ | — | `^[A-Z0-9]+(_[A-Z0-9]+)*$` ≤ 40 |
| Accessory part no. | – | OEM part numbers | — | `^[A-Z0-9-]{3,25}$` (upper-cased, as the OEM writes it) |

## Permissions: proposed convention

**Format:** `{module}.{process}.{activity}`, all lower-case, e.g. `sls.bkng.cr`.
**Regex:** `^[a-z]{2,5}\.[a-z0-9]{2,8}\.[a-z]{2,4}(_[a-z]{2,8})?$`.

- **Module** and **process** are the IAM codes, lower-cased (`SLS` → `sls`, `BKNG` → `bkng`).
- **Activity** comes from this dictionary. Please confirm the abbreviations, or choose full words.

| Today | Proposed | Meaning |
|---|---|---|
| VIEW | `vw` | list / read |
| CREATE | `cr` | create |
| EDIT (+ legacy `update`) | `ed` | edit |
| DELETE | `dl` | delete / deactivate |
| MANAGE | `mg` | create + edit + delete + import (masters) |
| EXPORT | `ex` | export |
| IMPORT | `im` | import |
| REPORT | `rp` | reports |
| ADMIN | `ad` | module administration |
| APPROVE (future) | `ap` | approve / counter |
| business steps: KYC, DMS, RTO, OTF, REFUND, INVOICE, DELIVERY, FINANCE, INSURANCE, EXCHANGE, PAYMENT, FOLLOWUP, ORDER_VERIFY, DO, REGISTRATION, BROADCAST, MODERATE, UPLOAD, DESK, REQUEST, ACTIVATE, SEND, SMS_RAW, WA_INBOX, CALL, RECORDING_DOWNLOAD | `st_{step}` (e.g. `sls.bkng.st_kyc`) — or keep them as short words (`sls.bkng.kyc`) | a business step inside a process |

**Legacy dotted permissions (88 + 16):**
- Each maps to its `{MOD}_{PROC}_{ACT}` twin where one exists (e.g. `branch.view` → `org.brch.vw`), or is retired.
- `admin.dashboard` (granted to all 76 roles) → `sys.dash.vw`.
- `*` is removed; the SuperAdmin bypass is the Gate `before` hook.
- The mapping table is generated and reviewed with you before the migration.

**Migration shape:**
1. Rename the rows in place (ids are kept, so the 2,871 role grants survive) and merge duplicates.
2. Update the 94 files.
3. `/api/v1/auth/me` returns old + new names until the app switches (DEC-004).
4. Flush the permission cache.

## Decisions needed from you

1. **Activities:** abbreviations (`vw` / `cr` / `ed` / `dl` / `mg` / …) or full words (`view` / `create` / …)? And
   business steps as `st_kyc` or plain `kyc`?
2. **Person code (BUG-206):** replace PAN / Aadhaar keys with `PRS-000001` style surrogates and remap 14 tables
   (a mass update of live data — needs your approval and a backup), yes / no?
3. **Keyword clean-up:** merge the 5 duplicate masters and normalise the 1,903 value codes that contain spaces into
   `UPPER_SNAKE` (labels unchanged; a mapping table, reversible)? Or only enforce the format for new rows?
4. **Designations:** keep the Spatie role *name* as the display name (spaces allowed) and use `code` everywhere in
   code? (Recommended — nothing hard-codes role names today.)
5. **Location / employee formats** as proposed?

## After sign-off (F4–F7)

1. The `App\Support\Formats\CodeFormat` registry (family → regex, normaliser, example, message).
2. `Field::code(...)->family('permission')` and the rule `App\Rules\CodeFormat`.
3. `php artisan formats:audit` (reports offenders; runs in CI).
4. The IAM screens / entity services validate every write.
5. The migrations (permission rename, keyword merges, person remap if approved).
6. Tests; the rules update in `.ai/rules`.
