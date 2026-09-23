# Xceler8 Vehicle Live Pricing
## Plain-language guide for managers, operations, and other developers / AI assistants

**Document:** Human companion to *Xceler8 Vehicle Pricing Machine Spec v3.1*  
**Date locked:** 31 August 2026  
**Who this is for:** Pricing managers, operations staff, new developers, and any AI that needs the story rather than the protocol.

If you are a compiler or an implementation agent, prefer the Machine Spec. If you need to explain the system to a person, start here.

---

## 1. What problem this solves

A Mahindra-style dealership sells many vehicles. Each vehicle is a **variant in a colour**. The factory (OEM) publishes an **ex-showroom** price. The customer does not pay that number. They pay an **on-road** price built from:

- Ex-showroom (sometimes a colour premium)
- Dealer incidental charges, FastTag, TRC, RTO tape, COD
- Road-side assistance (RSA)
- Extended warranty (Shield)
- Accessories packs
- Insurance (several companies, several year-plans, several add-ons)
- RTO / tax (depends on permit: Private, Passenger, Goods)
- TCS
- Minus schemes: cash discount, exchange bonus, corporate discount, RSA/Shield/accessory discounts

Those ingredients change on different calendars. OEM price lists move often. RSA and Shield move rarely. Insurance and RTO rules move almost never. The old habit of “one giant Excel into one giant import” mixed those clocks, hung the server, and silently overwrote good rules with blank rows.

The new system is a **gated pipeline**. One team member with the `manage_pricing` permission walks the pipeline. Each stage has a download, a human check, an upload, and a written count. Nothing is published to quotation until Calculate & Publish.

---

## 2. How a vehicle is identified (this never changes)

Think of four nested boxes:

1. **Segment** — PV (cars), CV (commercial), BEV, LMM, CSD, etc.
2. **Sub-segment** — a slice of that segment (can temporarily share the segment name).
3. **Model** — “Thar”, “Bolero”. The **model code is the OEM Model name**, uppercased, **without** the colour.
4. **Variant + colour** — one database row per colour. The **full OEM Code** includes the last two characters as the colour (WD, GR, BL…).

Example: variant stem `ASEP23QWSC1` in five colours becomes five rows:

- `ASEP23QWSC1WD`
- `ASEP23QWSC1GR`
- `ASEP23QWSC1BG`
- `ASEP23QWSC1SB`
- `ASEP23QWSC1BL`

Most colours share the same ex-showroom. A few colours cost extra. Because colour lives on the variant row, a colour premium is just a different price on a different OEM Code.

**Excel header rule used everywhere in Xceler8:**

- Column called **Model** or **Variant** → match our *display / custom* names.
- Column called **OEM Model** or **OEM Variant** → match the factory names.

People misspell Bikaner, Diesel, Personal. A **synonym** list maps nicknames back to the official code before matching.

---

## 3. When is a vehicle “complete”?

A brand-new code found on a Price List is created as **Incomplete**. We store only:

- OEM Model, OEM Variant, full OEM Code
- Colour = last two characters
- Segment guessed from the sheet name (“Price List PV” → PV)

We do **not** invent fuel, seats, CC, or a selling status. Incomplete is not the same as Inactive.

| Status | Meaning |
|---|---|
| **Incomplete** | Missing required facts. Fresh factory codes start here. Cannot be sold. |
| **Active** | Complete **and** on sale. |
| **Inactive** | Taken off sale by us. May still be complete. |
| **Discontinued** | Factory stopped making it. |

**Always required** before Active: Segment, Sub-segment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST %, Permit, Taxi Price flag, Custom Model, Custom Variant, Display Name, Colour Name.

**Also required depending on insurance:**

- Private or 4-wheeler Passenger + petrol/diesel → engine **CC**
- Private or 4-wheeler Passenger + electric → **Motor**
- Goods → **GVW**
- 3-wheeler Passenger → none of those three

Only Inactive vehicles may stay incomplete on purpose. An Active vehicle that fails the checklist is a bug.

---

## 4. The pipeline, in the order a human actually clicks

There can be **only one open pricing run**. If last week’s run was abandoned, you Resume it or Discard it. You cannot start a second one.

```
  [1] Upload Price Lists + pick sheets + WEF date
              |
              v
  [2] System finds new OEM codes → you download Vehicle Info
              |
              v
  [3] You fill Vehicle Info → upload → only complete vehicles can later be priced
              |
              v
  [4] Same Price List is imported as money (history kept by date)
              |
              v
  [5] Download Add-ons & Discounts → you confirm or edit → upload
              |
              v
  [6] Insurance & RTO: keep what is already in the system, or import new workbooks
              |
              v
  [7] Impact summary (how many new, how many still incomplete, what changed)
              |
              v
  [8] Optional Hold (stop quotes on PV / CV / All) → Calculate & Publish snapshots
              |
              v
  [9] Reopen holds. Quotation can now ask for a vehicle and get a full JSON price.
```

A queue worker must be running:

`php artisan queue:work --timeout=1800 --tries=1`

If the screen sits on “Detecting” or “Importing” with no movement, the worker is usually down.

---

## 5. What each Excel is for

### 5.1 Pricing.xlsx — changes often

Sheets you can multi-select: Price List PV, CV, BEV, LMM, LMM TZU, CSD.

This file is used **twice**:

- First pass: “are there OEM Codes we have never seen?”
- Later pass: “write the rupee columns against those codes.”

CSD can also arrive from a small separate “CSD Index Codes” book.

**WEF (With Effect From)** is the date these prices become true. Importing the same date again updates the current row. Importing a new date retires the old row (it stays in the table, marked ended) and writes a new one. We do not throw history away.

### 5.2 Addon-N-Discounts.xlsx — changes sometimes

Five sheets, five different “who does this apply to” rules:

1. **Dealer Charges** — Segment + Permit + Model. “PV / Any / Any” means every PV. A later row “PV / Any / Thar” **replaces the whole PV rule for Thar**, it does not merge field by field. Amounts sit in named columns: Incidental, FastTag, TRC, RTO Tape, COD.
2. **RSA** — Segment + Model. Several year options. The first paid year is the quotation default.
3. **Shield** — Shield pack + Transmission + Fuel. Scheme 1 is the default. The vehicle’s own Shield Pack column selects the row. A vehicle with a blank pack only matches “Any pack” rules.
4. **Exchange** — Model + Variant. OEM share + dealer share. Schemes can appear or disappear over the years.
5. **Corporate** — Model + Variant + category (CAT A, BULK 1, …). Same share pattern.

**Danger that already bit us:** if the export prints a blank/zero row for every model under an “Any” rule, the next import treats those zeros as overrides and wipes the real amounts. Exports are therefore **only the rules that actually exist in the database**. If you need a new override, you add that row yourself.

When a sheet is imported, **only that family is retired**. Importing RSA does not touch Shield. Old rows are switched off as of the WEF; they are not deleted.

### 5.3 Insurance.xlsx and RTO-Rules.xlsx — change rarely

The screen will say “rules already exist — keep them or upload new.”

Insurance companies are **not** a separate directory. They live as names on “which company is default for this model + permit” and on the premium rows. If a company is added or dropped, you re-import Insurance.xlsx.

RTO “Tax Factor” is English, not a number (“% of Rounded Up ESR”). The number sits in Tax Slab. The system stores the sentence and the number in two different columns. Putting the sentence into a decimal column is what produced the big red SQL errors.

Matching rule: **the most specific row wins.**

Taxi vehicles (`taxi_price = Yes`) must choose Private or Passenger at quote time. Both RTO options are pre-calculated. Insurance follows the mapped “Insu permit.”

---

## 6. How the on-road figure is built (what quotation will see)

On first open of a quote the system should load:

- RSA 1 year (first paid option)
- Shield Scheme 1
- Every dealer charge that matches
- Default insurer + standard cover (own-damage + third-party + Nil Depreciation + Consumables, unless you configured another standard)
- RTO for the vehicle’s permit (or a Private/Passenger choice if taxi)
- Accessories from the existing Accessory module
- TCS: 1 % above ₹10 lakh unless you changed the config

The customer can tick other insurance add-ons, other companies, other RSA years. If they remove RSA / Shield / accessories below a threshold, the related discount does not vanish — it moves to a **withheld** bucket stored only inside the quotation JSON (audit), not as a live price-list change.

Cash versus credit-note split of discounts still happens at quotation time.

The JSON always contains the **same keys**. Empty things are zero or null. Front-end and quotation ignore zeros. That way every vehicle speaks the same language.

Until the Stock module exists, quotation can toggle **New VIN / Old VIN** (default New).

---

## 7. Holds — stopping sales while you calculate

At Calculate, or from the Hold screen, you can freeze:

- one list (PV, CV, LMM, CSD, …), or
- All lists

Frozen lists must not accept new quotes or bookings. When snapshots are published you Reopen the lists you want live.

---

## 8. What “history” means in practice

There are two layers.

**Layer A — live tables with dates (what we actually use today)**  
Every price and every rule row has `wef_date`, `expired_on`, `is_active`. Yesterday’s RSA is still in the RSA table; it is just switched off. You can reconstruct “what was live on date D.”

**Layer B — extra history tables**  
Tables named `*_history` exist for OEM prices, add-ons and discounts. The current importer does **not** copy into them yet. Reconciliation today uses Layer A plus the **snapshot JSON** written at Calculate (the frozen on-road for each complete vehicle).

Discarding a session does **not** rewind Layer A. If you imported a bad price book and discarded the session, you must import the previous good book again.

A developer Reset URL can wipe sessions, profiles, prices and snapshots and delete vehicle rows created after a cutoff date. It **keeps** header labels, add-ons, discounts, RTO, insurance, TCS and synonyms — because those are the slow-moving books you would only replace on purpose.

---

## 9. Precautions (the ones that already cost us days)

1. Run the queue worker. Timeouts of 5 minutes on the web request are normal; the job is supposed to finish in the background.
2. Price List workbooks look small (2 MB) and still explode memory if PHP loads every sheet at once. The importer must read **one sheet**, a block of columns, a chunk of rows, then throw the sheet away.
3. Never calculate when the add-on import wrote **zero** rows. That means the sheet headers were not recognised or the database columns did not match.
4. After a bad add-on or rules export, upload the **original** factory/ops workbooks, not the file the system just gave you, if that file is full of zeros.
5. Do not treat “Any” as “print every model.” “Any” means “one rule, many vehicles.”
6. Multi-select the Price List sheets you actually want. LMM and TZU are two sheets even if an old dropdown glued them together.
7. Logs live in `storage/logs/pricing/pricing_process_session_<id>.log` when `PRICING_PROCESS_LOG=true`.
8. Only people with permission `manage_pricing` should see this menu.
9. Colour is not a separate master. Do not look for a colours table.
10. If the website says route `login` or `pricing.workflow.*` is missing, the pricing route file is not loaded. The dashboard can still work.

---

## 10. What is already built vs what is still open

**Built and in daily use in this sprint**

- The staged screens and one-session lock
- New-vehicle detect from Price Lists
- Vehicle Info download / upload and the completeness checklist
- Price import with WEF
- Add-on and discount import / export (after the “do not seed zeros” fix)
- RTO and insurance import with text tax formulas stored correctly
- Hold and TCS screens
- Developer reset
- Per-run log files
- The shape of the pricing JSON and the engine that is supposed to fill it

**Still to finish or to prove with a clean end-to-end run**

- Pulling every insurance add-on rate off the wide premium sheet (many addon rows still come out empty)
- Writing the extra `*_history` tables (optional hardening)
- A real insurance-company master if names must be curated outside the Excel
- The exact “invoice value” formula used inside IDV % (waiting on business)
- Signing off Calculate & Publish on a full PV+CV+CSD set
- Confirming accessories, taxi dual-permit, Shield pack, withheld discounts and cash/credit split inside a live quotation
- Teaching more synonyms so “PEROSNAL” never hits the database
- Updating the project changelog and developer docs after this slice

---

## 11. How another person (or AI) should behave when changing this

- Treat the Machine Spec as law. If chat history and the spec disagree, the spec wins until a human changes the spec.
- Deliver **full files**, not patches, unless asked.
- Database changes = **migrations**. Combined file, `dropIfExists` only when building a table from scratch. Prefer `ALTER` for columns we already have.
- Every pricing table keeps audit columns and soft delete.
- Models live under `App\Models\Vehicle\Pricing` and must not overwrite BaseModel casts.
- Matching and money live in **services**, not in controllers.
- Column titles in Excel will change. Logic talks to `field_code`.
- After a feature lands, offer a changelog entry and a laradocs page.

---

## 12. Tiny worked example

Sheet “Price List PV”, WEF 27 Aug 2026, new OEM Code `KEISMED323SDEGR`, OEM Model `XUV300`, OEM Variant `W8 DIESEL`.

1. Detect does not find that code → creates model `XUV300`, variant `KEISMED323SDEGR`, colour `GR`, status Incomplete, segment PV.
2. Vehicle Info export lists it with the rest of the fleet, sorted under PV / XUV300.
3. Ops fills Fuel Diesel, 5 seats, 4 wheels, MT, 2WD, body make/type, GST, Private, Taxi = No, display names, colour name Green, CC 1497. Import marks it Complete; ops sets Active.
4. Price import writes ex-showroom 1,234,000 against `KEISMED323SDEGR`.
5. Dealer Charges already have `PV / Private / Any`. That row applies. No extra XUV300 row is generated.
6. Insurance Co maps XUV300 Private → USGI default. Premium sheet supplies OD factor and TP. NilDep + Consumables pre-ticked.
7. RTO matches Private + 4 wheels + diesel + CC band. Tax Factor sentence is stored; slab  is the number.
8. Calculate writes a snapshot JSON with every key. Quotation reads it, shows on-road, and can switch company or drop RSA (discount moves to withheld).

---

## 13. Where to click (admin)

Base path: `/admin/pricing/`

- Workflow home, start, vehicle info, prices, addons, rules, impact, calculate
- Hold / reopen
- TCS
- RTO and Insurance browsers / test-calculate
- Reset (developers)

API consumers call the existing Pricing controller `getPricing` — there must be only one class with that name.

---

*End of human guide. Pair with Machine Spec v3.1.1 for tables, state names, acceptance tests, and file paths.*
