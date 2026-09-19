# System Checkup & Coding Standards Audit Report: Xcelr8 DMS (BMPL)

**Date:** September 12, 2026  
**Project:** BMPL / Xceler8 DMS (`xlrm`)  
**Scope:** Whole-codebase architectural, static-typing, naming, module-process-activity, and engineering standard audit  
**Standard References:**
- `.ai/xcelr8-conventions.md` (Module/Process/Activity, Route, FormRequest, RBAC, Code Standards)
- `.clinerules` (Domain Invariants, SSOT Service Catalog, Data/Cache Rules)
- Laravel 12 & PHP 8.3 / 8.4 Engineering Standards

---

## 1. Executive Summary & Quality Scorecard

Across 380 active PHP files in `app/`, `routes/`, `config/`, and `tests/`, this audit evaluates compliance with modern Laravel standards and project-specific architectural rules.

| Domain / Standard Area | Current Compliance | Risk Level | Primary Finding |
|---|:---:|:---:|---|
| **1. Strict Typing (`declare(strict_types=1)`)** | **0% (0 / 380 files)** | Critical | No file in `app/` or `tests/` declares strict typing. |
| **2. Route Definition Syntax** | **~45% Non-Compliant** | High | 42+ routes in `routes/backpack/booking.php` and `routes/backpack/core.php` use deprecated string controller syntax (`'Controller@action'`). |
| **3. Request Validation Architecture** | **~30% Standardized** | Moderate | Over 85 actions across CRUD and workflow controllers perform inline `$request->validate()` or manual `Validator::make` calls instead of Form Requests. |
| **4. Controller Layout & Namespacing** | **Flat / Unpartitioned** | Moderate | 40+ CRUD controllers are placed flat in `app/Http/Controllers/Admin/` without Module/Process grouping. |
| **5. Model Naming & Namespacing** | **Fragmented Legacy** | Moderate | 32 models under `app/Models/Module/` violate PSR naming with `X_`, `Xl_`, `Xl` prefixes (e.g. `XlSpareMaster`, `X_Vh_Order`, `Xessories`). |
| **6. SSOT Service Consumption** | **Partial Bypasses** | High | Large controllers (e.g., `BookingCrudController`) query raw Eloquent models directly instead of using cached helpers in `OrgService` / `VehicleService`. |
| **7. Method Signatures & Return Types** | **~25% Typed** | Moderate | Public controller action signatures and service methods frequently lack parameter and return type declarations. |
| **8. Automated Test Coverage** | **Low (~39 assertions)** | Critical | Zero integration/feature tests for the 7-stage Pricing Engine and Booking status workflows. |

---

## 2. Categorized Findings & Violations

### 2.1 Category 1: Strict Typing & Type Safety
- **Missing Strict Types**: `declare(strict_types=1);` is omitted in 100% of application files.
- **Untyped Controller Actions**: Public methods across Backpack and API controllers lack explicit return types (`View`, `Factory`, `JsonResponse`, `RedirectResponse`) and typed parameters.
- **Implicit Mixed Returns**: Several domain services return mixed shapes or undeclared array payloads without PHPDoc array shape annotations or DTOs.

### 2.2 Category 2: Routing Standards & Deprecated Syntaxes
- **Files Affected**: `routes/backpack/booking.php`, `routes/backpack/core.php`
- **Violation**: 42+ routes use deprecated string syntax:
  ```php
  // Deprecated string controller action syntax
  Route::get('booking/errors', 'BookingCrudController@erroneousEntries')->name('booking.errors');
  Route::get('booking/errors/data', 'BookingCrudController@erroneousEntriesData')->name('booking.errors.data');
  Route::post('booking/order-verify/{id}', 'BookingCrudController@orderVerify')->name('booking.order-verify');
  Route::get('booking/pending/sales-order', 'BookingCrudController@pendingorder')->name('booking.pending-order');
  ```
- **Standard Requirement**: Laravel 12 requires callable array tuple syntax `[BookingCrudController::class, 'erroneousEntries']` for reliable IDE navigation, refactoring, and static analysis.

### 2.3 Category 3: Form Request Layer vs Inline Validation
- **Findings**: Over 85 inline `$request->validate()` occurrences across `app/Http/Controllers/Admin/`.
- **Workflow Controllers Affected**:
  - `PricingWorkflowController`: `startDetect`, `vehicleInfoImport`, `pricesImport`, `addonsImport`, `rulesImport` execute raw validation.
  - `HoldController` & `TcsConfigController`: Validate model codes and limits directly inside controller methods.
  - `HRTransferController` & `HRRelievingController`: Perform multi-field validation inline.
- **Standard Requirement**: According to `.ai/xcelr8-conventions.md`, every create, update, or multi-step workflow operation must have a dedicated Form Request class.

### 2.4 Category 4: Model Namespacing & Non-Standard Prefixes
- **Legacy Models**: 32 Eloquent models remain under `app/Models/Module/` using legacy non-standard prefixing:
  - `App\Models\Module\Booking\Xessories` (duplicates modern `App\Models\Vehicle\Accessory`)
  - `App\Models\Module\Booking\X_Vh_Order`, `X_Vh_Stock`, `Xl_DSA_Master`, `Xl_Refunds`
  - `App\Models\Module\Finance\XFinance`, `XFinanceTa`, `XlFinancier`
  - `App\Models\Module\Insurance\XlInsurance`, `XlInsurer`
  - `App\Models\Module\Rto\XlRto`, `XlRtoRules`
  - `App\Models\Module\Spare\XlSpareMaster`, `XlSpareOrder`, `XlSpareRequest`, `XlSpareRequestDetail`, `XlSpareStock`, `XlSpareConsumed`, `XlSpareTransit`, `XlSpareBilledRo`
- **Standard Requirement**: Models must be in standard singular PascalCase and grouped under clean domain namespaces (e.g. `App\Models\Booking\*`, `App\Models\Spare\*`, `App\Models\Finance\*`).

### 2.5 Category 5: SSOT Cache & Service Layer Bypasses
- **Violation**: Large legacy controllers (e.g., `BookingCrudController.php` with 7,000+ lines) directly execute raw Eloquent queries (`Branch::where(...)`, `Stock::where(...)`) instead of delegating to cached SSOT services (`OrgService`, `VehicleService`, `KeywordValueService`).
- **Standard Requirement**: Violates §1 and §7 of `.clinerules`: *"Do not hit org/vehicle master tables directly from new code when OrgService / VehicleService / KeywordValueService already cache them."*

### 2.6 Category 6: Automated Test Coverage Deficiencies
- **Test Inventory**: 11 unit test files (39 assertions) and 1 example feature test.
- **Untested Business-Critical Areas**:
  1. **Vehicle Pricing Engine**: `PricingEngineService`, `InsuranceService`, `RtoService`, `TcsService`, and `PricingSessionService` have zero automated tests.
  2. **Pricing Importers**: `PriceListPricingImporter`, `VehicleInfoImportService`, `AddonDiscountImportService`, and `RulesWorkbookService` are untested.
  3. **Booking Engine & Quotations**: `QuotationCrudController`, `BookingStateService`, and booking financial receipt validations have zero automated tests.

---

## 3. Prioritized & Safe Remediation Roadmap

All refactorings must be governed by the **20 Non-Negotiable Rules** (preserving 100% existing business behavior, no repository-wide single-step rewrites, test verification at each phase).

- **Phase 1: Route Syntax Modernization (Zero Logic Risk)**: Convert 42+ string controller routes to callable class tuples `[Controller::class, 'method']`.
- **Phase 2: Pricing Workflow Form Requests**: Extract dedicated Form Requests for Pricing, Hold, and TCS workflows preserving exact rules and response contracts.
- **Phase 3: Characterization Test Suites**: Build regression test suites for Pricing Engine and Booking State transitions before model refactoring.
- **Phase 4: Incremental Strict Typing & Type Signatures**: Apply `declare(strict_types=1)` and return types file-by-file.
- **Phase 5: Model & SSOT Service Consolidation**: Migrate `App\Models\Module\*` models to standard namespaces with backwards-compatible aliases.

