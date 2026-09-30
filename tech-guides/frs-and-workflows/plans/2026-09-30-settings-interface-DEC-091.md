# Plan: one categorised Settings interface (DEC-091, to-do W13)

> **Status (30-09-2026): 🟡 in progress** — Phase 1 ✅ (catalogue + tabbed screen); Phase 2 ✅ (site / dealership applied); Phase 3 ✅ (communication); W13g ✅ (Site tab feedback); Phase 4 ✅ (pricing holds / TCS); Phase 5 ✅ (user behaviour); Phase 6 next. To-do rows W13, W13a–W13f.

## Context
The owner found settings on several screens: Utilities → Settings (`SettingsService`, 56 keys in 20 prefix groups, one
flat list), the legacy System Setting CRUD (`utils/system-setting`), and the pricing TCS / Hold screens. The request
(30-09): one categorised interface — Site / dealership, Communication, Pricing, User behaviour, plus every other module
and utility setting — changed only by settings managers (pricing also by pricing managers), taking effect at once on the
web and the app / API, and shown nowhere else.

## Owner decisions (30-09, DEC-091)
1. **Permissions:** "site.settings.manage" = `UTL_SETTINGS_MANAGE` (exists); "sales.pricing.manage" = `PRC_WKFL_MANAGE`
   (pricing managers already hold it). No new naming style.
2. **Price-list hold:** Settings → Pricing shows and changes the global / per-list hold; the pricing process keeps its
   hold steps; both write through `PricingHoldService`. The separate Hold screen goes.
3. **Secrets (SMTP password …):** stored encrypted (`encrypted` type, app key), shown masked, blank = keep; the mail
   service reads them at send time.

## Design
- **Catalogue** — `config/settings_ui.php`: tabs (in order) → sections → keys, each key with its input (text, url,
  email, number, switch, select with options, image, textarea, secret), help text and, where needed, the permission.
  Values and defaults stay in `config('platform.settings')` + `SettingsService` (the only writer); new keys are added to
  the seeds. Service-backed sections (TCS rules → `TcsConfigService`, holds → `PricingHoldService`) are rendered by a
  small section handler instead of plain keys.
- **Screen** — Utilities → Settings becomes the tabbed interface (left tab list, one form per section, save per
  section, dirty-state guard, search across all tabs). `UTL_SETTINGS_MANAGE` sees every tab; `PRC_WKFL_MANAGE` alone
  sees only Pricing. Read-only view is dropped (the owner wants settings visible only to managers).
- **Tabs:**
  1. **Site / dealership** — `site.name` ("Bikaner Motors"), `site.url` (https://www.BikanerMotors.com), `site.logo`,
     `site.favicon`, `site.address`, `site.email`, `site.phone`, `site.gstin`, `site.support_email`; `display.date_format`;
     applied by a boot-time `ApplySiteSettings` (Backpack project name / logo / favicon, mail from-name, PDF headers).
     Replaces `brand.name` / `branding.logo` (migrated, old keys read as fallback).
  2. **Communication** — `comms.enabled.{mail,sms,whatsapp,push}` (global switches, enforced in `CommsRouter` /
     `NotifyService`), `mail.smtp.{host,port,username,password(secret),encryption,from_address,from_name}` (applied to
     the mailer at send time), `mail.signature` (appended by `EmailService`), plus existing `mail.*`, `sms.*`,
     `whatsapp.*`, `telephony.*`, `comms.*`, `notify.quiet_hours`, `templates.*`.
  3. **Pricing** — price-list holds (global + per list), TCS threshold / rate (current rule via `TcsConfigService`),
     `pricing.insurance.*`, `pricing.rto.round_up_to`, `pricing.dealer_charges.include_cod`.
  4. **User behaviour** — `ui.appearance_enabled`, `ui.density.*`, `account.can_change_{display_name,email,photo,mobile,
     aadhaar,pan,password,dob,doj,marital_status,gender}`, password policy — enforced by `MyAccountService` and the API
     profile endpoints.
  5. **Security** — `security.*` (idle lock / logout, CSP, unlock attempts).
  6. **Data access** — `scope.enabled`, `scope.unassigned_rows`.
  7. **Documents** — `docs.*`.  8. **Tickets & SLA** — `sla.*`, `ticket.*`.  9. **Chat & approvals** — `chat.*`,
     `approval.*`.
- **Single place** — the legacy System Setting routes redirect to the new screen (then removed with its menu entry);
  the TCS config and Hold screens redirect to Settings → Pricing; menu shows one "Settings" item.
- **Immediate effect** — `SettingsService::set()` already busts the cache and emits `SettingsChanged`; runtime-applied
  config (Backpack branding, mailer) reads settings per request / per send, never at config-cache time. API: new
  `GET /api/v1/app-settings` (public subset: site name, logo, favicon URLs, channel switches, which profile fields are
  editable) for the mobile app.

## Phases
1. **Catalogue + screen:** `config/settings_ui.php`, new seeds, tabbed screen, permissions (UTL / PRC), save per section
   through `SettingsService`; tests (access matrix, save, secret masked / kept).
2. **Site / dealership applied:** `ApplySiteSettings` (layout name, logo, favicon, login, PDFs, mail from-name); key
   migration from `brand.name` / `branding.logo`; tests + smoke.
3. **Communication:** channel switches, SMTP from settings, signature; tests with `Mail::fake()`.
4. **Pricing tab:** holds + TCS via their services; old screens redirect; tests.
5. **User behaviour:** new profile-field flags enforced in `MyAccountService` + API; appearance switch; tests.
6. **Single place + API:** legacy screens / menu removed, `app-settings` endpoint + API docs; guides; smoke as
   superadmin, a settings manager, a pricing manager and a plain user.

## Verification
Feature tests per phase; HTTP smoke of the settings tabs for four roles; a change on the screen visible on the next
request (web) and in `GET /api/v1/app-settings` without a cache clear; full suite + full PHPStan before the merge.
