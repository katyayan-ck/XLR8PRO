# Jobs, Console Commands & Excel I/O Reference Catalog

This catalog outlines all background queue jobs (`app/Jobs/`), Artisan console commands (`app/Console/Commands/`), and Excel import/export classes (`app/Imports/`, `app/Exports/`).

---

## 1. Background Jobs (`app/Jobs/`)

| Job Class | Queue | Description / Workflow Role |
|---|---|---|
| `App\Jobs\Vehicle\Pricing\DetectPricingWorkbookJob` | `default` | Processes uploaded pricing Excel workbook in background to detect missing vehicle variants and create stubs. |
| `App\Jobs\Vehicle\Pricing\ImportPriceListsJob` | `default` | Ingests ex-showroom prices for complete vehicles in 50-row chunks; creates/updates live prices and sets change flags. |
| `App\Jobs\Vehicle\Pricing\CalculatePricingSessionJob` | `default` | Iterates across all active variants to pre-compute and store published on-road snapshots in JSON format. |
| `App\Jobs\Vehicle\Pricing\RecalculateVehiclePricingJob`| `default` | Re-calculates on-road snapshots for specific affected variants following spec or rule modifications. |
| `App\Jobs\Vehicle\Pricing\ProcessPricingWorkbookJob` | `default` | Orchestrates end-to-end pricing session workbook ingestion pipeline. |
| `App\Jobs\ImportEnquiriesJob` | `default` | Asynchronously processes bulk customer enquiry imports from external lead platforms. |
| `App\Jobs\SendHistoryNotification` | `notifications` | Dispatches asynchronous push/email notifications following entity status state transitions. |

---

## 2. Console Commands (`app/Console/Commands/`)

| Command Signature | Class Name | Description & Arguments |
|---|---|---|
| `vehicle:accessories:import {path}` | `ImportVehicleAccessories` | Purges and reloads vehicle accessories catalogue from specified Excel file using `AccessoryService`. |
| `vehicle:accessories:export {--path=}`| `ExportVehicleAccessories` | Exports all accessory items, prices, and vehicle scopes to an Excel workbook. |
| `users:import {path}` | `ImportUsersCommand` | Imports full employee, person, branch, department, and role matrices from Master Excel template. |
| `rbac:import-master {path}` | `ImportRbacMaster` | Imports modules, processes, and permission matrices from standardized Excel sheets. |

---

## 3. Excel Importers (`app/Imports/`)

### Multi-Sheet Masters & Organizations
- `EmployeeMasterImport`: Master orchestrator handling all organizational and employee sheets:
  - `Sheets\BranchSheet`: Inserts/updates `Branch` records.
  - `Sheets\LocationSheet`: Inserts/updates `Location` records under branches.
  - `Sheets\DepartmentSheet`: Ingests `Department` records.
  - `Sheets\DivisionSheet`: Ingests `Division` records under departments.
  - `Sheets\VerticalSheet`: Ingests business `Vertical` records.
  - `Sheets\DesignationSheet`: Ingests `Designation` records with grade/level.
  - `Sheets\DesignationTreeSheet`: Builds reporting trees and parent-child linkages.
  - `Sheets\PostSheet`: Defines organizational posts.
  - `Sheets\SegmentSheet` & `SubSegmentSheet` & `ModelSheet`: Vehicle master categories.
  - `Sheets\EmployeeSheetImport`: Imports employee records with Person, contacts, address, and banking.
  - `Sheets\RulesSheetImport` / `RulesUserImporter`: Multi-branch / multi-scope employee rules.
- `RbacMasterImport`: Reads modules, processes, and permission definitions.
- `VehicleAccessoriesImport`: Ingests multi-sheet accessory catalogue (Accessory, Ceramic, PPF, Maxicare, GPS_VLTD, RTO_Tape, Kazam).
- `VehicleInfoImport`: Ingests vehicle technical specifications (Fuel, Transmission, Seating, CC, GVW, Motor, Wheels).
- `VehicleDefineImporter`: Legacy multi-sheet vehicle definition importer.

### Importer Concerns & Value Objects (`app/Imports/Concerns/`)
- `Concerns\PersonBuilder`: Creates/updates `Person`, `PersonContact`, `PersonAddress`, and `PersonBankingDetail`.
- `Concerns\EmployeeBuilder`: Builds `Employee` and assigns designation, department, branch, and location.
- `Concerns\PivotWriter`: Writes multi-assignment pivot rows (`EmployeeBranchAssignment`, `EmployeeLocationAssignment`, etc.).
- `Concerns\MasterDataSeeder`: Seeds missing reference records on-the-fly during batch imports.
- `Concerns\CodeGenerator`: Standardized code generator for auto-incremented entity codes.
- `ValueObjects\EmployeeRowDTO`: Strongly typed DTO representing an ingested employee record row.

---

## 4. Excel Exporters (`app/Exports/`)

- `VehicleAccessoriesExport` (`App\Exports\VehicleAccessoriesExport`): Maatwebsite Excel export class producing formatted accessory catalogue workbooks with model display names, part numbers, MRP, and discount matrices.
