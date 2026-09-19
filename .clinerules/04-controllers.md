# Controllers Reference Catalog

This catalog outlines all Backpack Admin CRUD controllers (`app/Http/Controllers/Admin/`) and REST API controllers (`app/Http/Controllers/Api/V1/`), mapping their models, routes, operations, and permissions.

---

## 1. Backpack Admin Controllers (`app/Http/Controllers/Admin/`)

All Backpack CRUD controllers inherit from `Backpack\CRUD\app\Http\Controllers\CrudController` and use `App\Http\Controllers\Admin\Traits\ScopedCrud` for branch and data scope filtering.

| Controller | Model | Route Prefix | Key Permissions / Actions |
|---|---|---|---|
| `BranchCrudController` | `Branch` | `admin/branch` | `branch.view`, `branch.create`, `branch.update`, `branch.delete` |
| `LocationCrudController` | `Location` | `admin/location` | `location.view`, `location.create`, `location.update` |
| `DepartmentCrudController` | `Department` | `admin/department` | `department.view`, `department.create`, `department.update` |
| `DivisionCrudController` | `Division` | `admin/division` | `division.view`, `division.create`, `division.update` |
| `VerticalCrudController` | `Vertical` | `admin/vertical` | `vertical.view`, `vertical.create`, `vertical.update` |
| `DesignationCrudController`| `Designation` | `admin/designation` | `designation.view`, `designation.create`, `designation.update` |
| `DesigDeptTreeCrudController`| `DesignationDeptTree`| `admin/desig-dept-tree` | `desig_dept_tree.view`, `desig_dept_tree.manage` |
| `PersonCrudController` | `Person` | `admin/person` | `person.view`, `person.create`, `person.update` (via `PersonService`) |
| `PersonContactCrudController`| `PersonContact` | `admin/person-contact` | Contact numbers & email addresses |
| `PersonAddressCrudController`| `PersonAddress` | `admin/person-address` | Physical and billing addresses |
| `PersonBankingDetailCrudController`| `PersonBankingDetail`| `admin/person-banking-detail`| Bank accounts and IFSC codes |
| `UserCrudController` | `User` | `admin/user` | `user.view`, `user.create`, `user.update`, `user.assign_role` |
| `UserTypeCrudController` | `UserType` | `admin/user-type` | Master types (`Emp`, `Cust`, `Insurer`) |
| `RoleCrudController` | `Role` | `admin/role` | `role.view`, `role.manage` (Spatie RBAC) |
| `PermissionCrudController` | `Permission` | `admin/permission` | `permission.view`, `permission.manage` |
| `ModuleCrudController` | `Module` | `admin/module` | IAM module definitions |
| `ProcessCrudController` | `Process` | `admin/process` | IAM sub-process definitions |
| `PostPermissionCrudController`| `Permission` | `admin/post-permission` | Post-based permission bindings |
| `PostReportingCrudController`| `UserReporting` | `admin/post-reporting` | Reporting hierarchy management |
| `ApprovalHierarchyCrudController`| `ApprovalHierarchy`| `admin/approval-hierarchy`| Tiered approval levels & powers |
| `ReportingHierarchyCrudController`| `ReportingHierarchy`| `admin/reporting-hierarchy`| Organization tree definitions |
| `KeyvalueCrudController` | `Keyvalue` | `admin/keyvalue` | `keyword.view`, `keyword.manage` (Key-value pairs) |
| `KeywordMasterCrudController`| `KeywordMaster` | `admin/keyword-master` | Master enum dictionary groups |
### Vehicle & Accessories CRUD Controllers

| Controller | Model | Route Prefix | Key Actions |
|---|---|---|---|
| `BrandCrudController` | `Brand` | `admin/brand` | Manufacturer brands |
| `SegmentCrudController` | `Segment` | `admin/segment` | Segment categories (`PV`, `CV`, `LMM`) |
| `SubSegmentCrudController` | `SubSegment` | `admin/sub-segment` | Sub-segments under segments |
| `VehicleModelCrudController` | `VehicleModel` | `admin/vehicle-model` | Models (stem code without colour) |
| `VariantCrudController` | `Variant` | `admin/variant` | Variants (full OEM code with colour) |
| `ColorCrudController` | `Color` | `admin/color` | Color master mappings |
| `VehicleAccessoryCrudController`| `Accessory` | `admin/vehicle-accessory`| Catalog listing, import & export actions |

### Pricing Workflow Controllers (`app/Http/Controllers/Admin/Pricing/`)

| Controller | Route Prefix | Responsibility |
|---|---|---|
| `PricingWorkflowController` | `admin/pricing/workflow` | 7-stage import pipeline dashboard (Detect, Specs, Price, Addons, Rules, Calculate, Publish). |
| `HoldController` | `admin/pricing/hold` | Manages commercial pricing holds blocking quotations/bookings. |
| `InsuranceController` | `admin/pricing/insurance` | Insurance base rates, add-on percentages, and default plan setup. |
| `RtoRuleController` | `admin/pricing/rto` | RTO tax slabs, formulas, and fee structures. |
| `TcsConfigController` | `admin/pricing/tcs` | TCS limit thresholds and tax percentage setup. |
| `PricingResetController` | `admin/pricing/reset` | Controlled rollback and reset of pricing sessions. |

### CRM, Booking, Spares & Operations Controllers

| Controller | Model | Route Prefix | Responsibility |
|---|---|---|---|
| `LeadCrudController` | `Lead` | `admin/lead` | Lead management & stage tracking |
| `LeadSourceCrudController` | `LeadSource` | `admin/lead-source` | Lead acquisition sources |
| `CampaignCrudController` | `Campaign` | `admin/campaign` | Marketing campaigns & ROI |
| `EnquiryCrudController` | `Enquiry` | `admin/enquiry` | Customer vehicle enquiries |
| `QuotationCrudController` | `Quotation` | `admin/quotation` | Quotation generator, PDF preview, approvals |
| `BookingCrudController` | `Booking` | `admin/booking` | Order lifecycle (uses `BookingStateService`) |
| `RtoCrudController` | `XlRto` | `admin/rto` | Registration status & HSRP tracking |
| `SpareRequestCrudController` | `XlSpareRequest` | `admin/spare-request` | Workshop spare parts requisitions |
| `SpareOrderingreportController`| - | `admin/spare-ordering-report`| OEM parts purchase order reports |
| `SparePartwiseController` | - | `admin/spare-partwise` | Parts consumption & inventory reports |
| `HRJourneyController` | `Employee` | `admin/hr` | Transfers, promotions, and status changes |
| `UserImportExportController` | - | `admin/user-import-export`| Bulk user/employee Excel operations |

| `SystemSettingCrudController`| `SystemSetting` | `admin/system-setting` | System configuration parameters |

---

## 2. REST API Controllers (`app/Http/Controllers/Api/V1/`)

All API controllers extend `App\Http\Controllers\Api\V1\BaseController` and emit standard JSON responses.

### `AuthController` (`App\Http\Controllers\Api\V1\AuthController`)
- `POST /api/v1/auth/request-otp`: Sends 6-digit OTP to mobile or email.
- `POST /api/v1/auth/verify-otp`: Validates token, registers device session, and issues Sanctum bearer token.
- `GET /api/v1/auth/me`: Fetches authenticated user, profile, assigned branches/departments, and permissions.
- `POST /api/v1/auth/logout`: Revokes active Sanctum token and terminates device session.

### `DocController` (`App\Http\Controllers\Api\V1\DocController`)
- `POST /api/v1/docs/upload`: Uploads document file via `DocService` with optional AI tagging.
- `GET /api/v1/docs/group/{groupId}`: Lists documents within a specific group.
- `GET /api/v1/docs/{id}/download`: Downloads single document file.
- `POST /api/v1/docs/batch-download`: Streams ZIP archive of selected document IDs.

### `NotificationController` (`App\Http\Controllers\Api\V1\NotificationController`)
- `GET /api/v1/notifications`: Paginated list of notifications for the authenticated user.
- `POST /api/v1/notifications/{id}/read`: Marks notification as read.
- `POST /api/v1/notifications/read-all`: Marks all unread notifications as read.
- `POST /api/v1/notifications/device-token`: Registers/updates user FCM push token.

### `SystemSettingApiController` (`App\Http\Controllers\Api\V1\SystemSettingApiController`)
- `GET /api/v1/settings`: Public system settings (dealership info, logo, theme, public configs).
- `GET /api/v1/settings/{key}`: Reads specific system setting value.

### `PricingApiController` & `PricingController` (`App\Http\Controllers\Api\V1\Vehicle\Pricing\`)
- `GET /api/v1/vehicle/pricing/on-road`: Computes real-time on-road quotation payload using `PricingEngineService` (Ex-showroom + GST + TCS + RTO + Insurance + Fastag + Incidental + Selected Accessories).
- `GET /api/v1/vehicle/pricing/breakup/{variantCode}`: Returns detailed cost component breakup.
- `GET /api/v1/vehicle/pricing/snapshot/{variantCode}`: Retrieves pre-computed published snapshot JSON.
- `GET /api/v1/vehicle/pricing/accessories`: Fetches valid accessories for vehicle scope (`segment`, `model`, `variant`, `permit`).
