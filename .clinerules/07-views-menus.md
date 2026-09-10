# Views, Menus & UI Reference Catalog

This catalog outlines custom Backpack views, menu structures, custom columns/fields, and domain-specific UI layouts under `resources/views/`.

---

## 1. Backpack Admin Menu Structure

The admin sidebar navigation is defined in `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` and organized into hierarchical modules:

- **Dashboard**: Direct link to `/admin/dashboard`.
- **Pricing & Vehicles**:
  - Pricing Workflow: `/admin/pricing/workflow` (7-stage pipeline)
  - Commercial Holds: `/admin/pricing/hold`
  - Insurance Rules: `/admin/pricing/insurance`
  - RTO Rules: `/admin/pricing/rto`
  - TCS Config: `/admin/pricing/tcs`
  - Vehicle Masters: Segments (`/admin/segment`), Sub-Segments (`/admin/sub-segment`), Models (`/admin/vehicle-model`), Variants (`/admin/variant`), Colors (`/admin/color`), Brands (`/admin/brand`)
  - Accessories: `/admin/vehicle-accessory`
- **Sales & CRM**:
  - Leads: `/admin/lead`
  - Enquiries: `/admin/enquiry`
  - Quotations: `/admin/quotation`
  - Lead Sources: `/admin/lead-source`
  - Campaigns: `/admin/campaign`
- **Operations & Workshop**:
  - Bookings: `/admin/booking`
  - Spares Requisitions: `/admin/spare-request`
  - Spares Inventory / Reports: `/admin/spare-ordering-report`, `/admin/spare-partwise`
  - RTO & Registration: `/admin/rto`
- **Organization & IAM**:
  - Hierarchy: Branches (`/admin/branch`), Locations (`/admin/location`), Departments (`/admin/department`), Divisions (`/admin/division`), Verticals (`/admin/vertical`), Designations (`/admin/designation`)
  - People & Employees: Persons (`/admin/person`), Employees (`/admin/hr`), Users (`/admin/user`), User Types (`/admin/user-type`)
  - Security & Access: Roles (`/admin/role`), Permissions (`/admin/permission`), Modules (`/admin/module`), Processes (`/admin/process`)
- **System & Utilities**:
  - Key-Value Master: `/admin/keyword-master`, `/admin/keyvalue`
  - Settings: `/admin/system-setting`

---

## 2. Domain Custom Views (`resources/views/admin/`)

### Pricing Workflow (`resources/views/admin/pricing/`)
- `pricing/workflow/index.blade.php`: Session dashboard displaying active stage, pending gates, and stage actions.
- `pricing/workflow/start.blade.php`: Upload dropzone for new Price List workbooks and WEF date selector.
- `pricing/workflow/vehicle-info.blade.php`: Incomplete vehicle review grid with Export/Import Vehicle Info buttons.
- `pricing/workflow/prices.blade.php`: Price List verification grid with status flags.
- `pricing/workflow/addons.blade.php`: Addons and discounts management interface.
- `pricing/workflow/rules.blade.php`: RTO & Insurance rules review interface.
- `pricing/workflow/impact-summary.blade.php`: Final pre-calculation gate summarizing complete vs skipped vehicles.
- `pricing/hold/index.blade.php`: Commercial pricing hold toggles.
- `pricing/insurance/`: `base-rules.blade.php`, `addon-rates.blade.php`, `defaults.blade.php`.
- `pricing/rto/`: `index.blade.php`, `create.blade.php`, `edit.blade.php`.
- `pricing/tcs/index.blade.php`: TCS configuration interface.

### CRM & Quotations (`resources/views/admin/quotation/`)
- `quotation/create.blade.php` & `edit.blade.php`: Quotation builder with live on-road price calculation, accessory checklist, and discount breakdown.
- `quotation/preview.blade.php`: Print-ready customer quotation layout.
- `quotation/history.blade.php`: Version history of quotation modifications.

### HR & Employee Journey (`resources/views/admin/hr/`)
- `hr/transfer.blade.php`: Branch, department, and location transfer workflows.
- `hr/promotion.blade.php`: Designation and payroll progression forms.
- `hr/status-change.blade.php`: Status changes (Active, On-Leave, Resigned, Terminated).

### Spares Management (`resources/views/admin/spare-request/`)
- `spare-request/list.blade.php` & `create.blade.php`: Workshop spare parts requisition.
- `spare-request/orderingreport.blade.php`: OEM parts order fulfillment tracking.
- `spare-request/partwise.blade.php`: Part-by-part consumption and stock analysis.

---

## 3. Custom Backpack UI Overrides (`resources/views/vendor/backpack/`)

- `crud/buttons/vehicle_accessory_export.blade.php`: Custom button triggering accessory catalogue export.
- `crud/buttons/vehicle_accessory_import.blade.php`: Custom button modal for uploading accessory Excel files.
- `crud/columns/`: Custom column renderers (`checklist_dependency.blade.php`, `json.blade.php`, `multidimensional_array.blade.php`, `phone.blade.php`, `view.blade.php`).
- `crud/fields/`: Custom form inputs (`checklist_dependency.blade.php`, `select2_from_ajax_multiple.blade.php`, `json.blade.php`, `date_range.blade.php`).
- `ui/inc/menu_items.blade.php`: Dealership admin sidebar menu.
