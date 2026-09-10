# Models Reference Catalog

This catalog documents the ~140 Eloquent models across `app/Models/` grouped by domain namespace, outlining their database tables, primary keys, relationships, and key behaviors.

---

## 1. Base Models & Core Traits

| Model / Trait | Namespace | Description / Key Attributes |
|---|---|---|
| `BaseModel` | `App\Models\BaseModel` | Abstract base class with auto-audit (`created_by`, `updated_by`, `deleted_by`), soft deletes, and standardized timestamp casting. |
| `User` | `App\Models\User` | Core authenticatable user model; links to `Person` (`person_code`), `Employee`, `UserRoleAssignment`, `UserDataScope`. Implements Sanctum tokens and Spatie Permissions. |
| `UserDataScope` | `App\Models\UserDataScope` | Stores fine-grained user data scopes (`scope_type`, `scope_id`, `is_active`). |
| `HasAuditFields` | `App\Models\Traits\HasAuditFields` | Trait automatically setting creator, updater, and deleter user IDs on Eloquent lifecycle events. |
| `HasColumnTransformations`| `App\Models\Traits\HasColumnTransformations`| Handles uppercase, lowercase, slug, and trim transformations based on `config/column_transformations.php`. |
| `HasCommunications` | `App\Models\Traits\HasCommunications` | Polymorphic relationship helper for SMS, emails, notifications, and chat threads. |
| `HasSlug` | `App\Models\Traits\HasSlug` | Automatic slug generation for models with title/name fields. |
| `HasTreeStructure` | `App\Models\Traits\HasTreeStructure` | Recursive parent-child traversal helpers (`parent()`, `children()`, `ancestors()`, `descendants()`). |
| `GraphTraversalTrait` | `App\Models\Traits\GraphTraversalTrait` | Node and edge traversal helpers for workflow graph nodes. |
| `ScopedQuery` | `App\Models\Traits\ScopedQuery` | Scoping macros and query builders for multi-branch and org isolation. |

---

## 2. Admin & Organization Models (`App\Models\Admin`)

| Model | Table | Key Fields & Relationships |
|---|---|---|
| `Branch` | `xlr8_admin_branches` | `code`, `name`, `is_active`. Has many `Location`, `EmployeeBranchAssignment`. |
| `Location` | `xlr8_admin_locations` | `code`, `name`, `branch_code`, `pincode`. Belongs to `Branch`. |
| `Department` | `xlr8_admin_departments` | `code`, `name`, `is_active`. Has many `Division`, `DesignationDeptTree`. |
| `Division` | `xlr8_admin_divisions` | `code`, `name`, `department_code`. Belongs to `Department`. |
| `Vertical` | `xlr8_admin_verticals` | `code`, `name`, `is_active`. Business verticals. |
| `Designation` | `xlr8_admin_designations`| `code`, `name`, `grade`, `level`, `is_active`. |
| `DesignationDeptTree` | `xlr8_admin_desig_dept_trees` | Pivot/hierarchy linking `Department` and `Designation` with parent-child tree. |
| `Person` | `xlr8_admin_persons` | Core identity: `person_code`, `first_name`, `last_name`, `pan_number`, `aadhaar_number`, `status`. Has many Contacts, Addresses, Banking, UserTypes. |
| `PersonContact` | `xlr8_admin_person_contacts` | `person_code`, `contact_type` (phone/email), `value`, `is_primary`. |
| `PersonAddress` | `xlr8_admin_person_addresses` | `person_code`, `address_type`, `line1`, `line2`, `city`, `state`, `pincode`, `is_primary`. |
| `PersonBankingDetail` | `xlr8_admin_person_banking_details`| `person_code`, `account_type`, `account_nature`, `account_no`, `ifsc`, `bank_name`, `is_primary`. |
| `PersonUserType` | `xlr8_admin_person_user_types`| `person_code`, `user_type` (`Emp`, `Cust`, `Insurer`, `Vendor`), `is_primary`. |
| `UserType` | `xlr8_admin_user_types` | Master catalogue of allowed person user types. |
| `Employee` | `xlr8_admin_employees` | `emp_code`, `person_code`, `designation_code`, `department_code`, `branch_code`, `location_code`, `status`. |
| `EmployeeHistory` | `xlr8_admin_employee_histories` | Snapshot tracking of employee transfers, promotions, and state changes. |
| `EmployeePayroll` | `xlr8_admin_employee_payrolls` | Salary, CTC, allowance structures, PF, ESI references for employees. |
| `EmployeeBranchAssignment` | `xlr8_admin_emp_branch_assignments` | Multi-branch assignments for roving or regional employees. |
| `EmployeeLocationAssignment`| `xlr8_admin_emp_location_assignments`| Multi-location assignments within branches. |
| `EmployeeDepartmentAssignment`| `xlr8_admin_emp_dept_assignments`| Multi-department access assignments. |
| `EmployeeVerticalAssignment`| `xlr8_admin_emp_vertical_assignments`| Multi-vertical operational scoping. |
| `EmployeeVehicleScope` | `xlr8_admin_emp_vehicle_scopes` | Vehicle brand/model/segment scopes assigned to sales consultants. |
---

## 3. Vehicle Master & Accessories (`App\Models\Vehicle`)

| Model | Table | Description / Key Fields |
|---|---|---|
| `Brand` | `xlr8_vehicle_brands` | Vehicle manufacturers (e.g., Mahindra). |
| `Segment` | `xlr8_vehicle_segment` | High-level segment code (`PV`, `CV`, `LMM`, `SCV`, `3W`, `Tractor`). |
| `SubSegment` | `xlr8_vehicle_subsegment`| Sub-segment under segment (e.g. `UV`, `PICKUP`, `LCV`). |
| `VehicleModel` | `xlr8_vehicle_model` | Model master. `code` = stem without colour. `name`, `oem_name`, `segment_code`, `sub_segment_code`. |
| `Variant` | `xlr8_vehicle_variant` | Variant row per color. `code` = full OEM code WITH color suffix. `color_code`, `display_name`, `status` (`INCOMPLETE`, `ACTIVE`, `INACTIVE`, `DISCONTINUED`). |
| `Color` | `xlr8_vehicle_colors` | Color master mappings (`code`, `name`, `hex_code`). |
| `Accessory` | `xlr8_vehicle_accessories` | Accessory catalogue: `part_number`, `name`, `type` (`Accessory`, `Ceramic`, `PPF`, `Maxicare`, `GPS_VLTD`, `RTO_Tape`, `Kazam`), `mrp`, `discount`. |
| `AccessoryScope` | `xlr8_vehicle_accessory_scopes`| Scoping for accessories across `segment_code`, `model_code`, `variant_code`, `permit`. |

---

## 4. Vehicle Pricing Pipeline (`App\Models\Vehicle\Pricing`)

| Model | Table | Description / Key Fields |
|---|---|---|
| `ImportSession` | `xlr8_vehicle_pricing_import_sessions` | Lifecycle of pricing imports (`session_id`, `status`, `stage`, `wef_date`, `discarded_at`, `completed_at`). |
| `Profile` | `xlr8_vehicle_pricing_profiles` | Vehicle profile status per import session; tracks completeness flags. |
| `Pricing` (`pricing.php`)| `xlr8_vehicle_pricing_prices` | Main price list: `variant_code`, `wef_date`, `ex_showroom`, `gst_rate`, `cess_rate`, `is_active`. |
| `PricingHistory` | `xlr8_vehicle_pricing_price_histories` | Historical snapshots of expired price points. |
| `Addon` | `xlr8_vehicle_pricing_addons` | Addon rules (Shield, Extended Warranty, RSA, Dealer Charges, Fastag). |
| `AddonHistory` | `xlr8_vehicle_pricing_addon_histories` | Historical archive of addon pricing changes. |
| `Discount` | `xlr8_vehicle_pricing_discounts` | Discounts (Exchange, Corporate, Scheme, Cash discount rules). |
| `DiscountHistory` | `xlr8_vehicle_pricing_discount_histories`| Historical archive of discount matrices. |
| `DealerCharge` | `xlr8_vehicle_pricing_dealer_charges` | Incidental, logistics, fastag, TRC charges scoped by segment/model/branch. |
| `InsBaseRule` | `xlr8_vehicle_pricing_ins_base_rules` | Base insurance OD/TP pricing calculations. |
| `InsAddonRate` | `xlr8_vehicle_pricing_ins_addon_rates` | Insurance addon percentage multipliers (Nil Dep, Return to Invoice, Engine Protect, Key Protect). |
| `InsDefault` | `xlr8_vehicle_pricing_ins_defaults` | Default standard insurance configuration for quotes. |
| `RtoRule` | `xlr8_vehicle_pricing_rto_rules` | RTO calculation matrix (`tax_basis`, `tax_slab`, `cess`, `registration_fee`, `smart_card`, `postal_fee`, `hypothecation_fee`). |
| `Csd` | `xlr8_vehicle_pricing_csd_prices` | CSD (Canteen Stores Department) defense pricing matrices. |
| `TcsConfig` | `xlr8_vehicle_pricing_tcs_configs` | TCS calculation thresholds and tax rates. |
| `Hold` | `xlr8_vehicle_pricing_holds` | Commercial holds placed on specific variants/models blocking quotation and sales. |
| `Snapshot` | `xlr8_vehicle_pricing_snapshots` | Pre-calculated published on-road pricing snapshots in JSON format. |
| `ChangeFlag` | `xlr8_vehicle_pricing_change_flags` | Change markers flagging updated prices between sessions. |
| `Affected` | `xlr8_vehicle_pricing_affected_records`| Records identified as impacted by upstream pricing/spec modifications. |
| `Draft` | `xlr8_vehicle_pricing_drafts` | Temporary staging tables during calculation pass. |
| `SheetHeader` | `xlr8_vehicle_pricing_sheet_headers` | Header alias mapping registry linking Excel labels to canonical `field_code`. |

| `UserDivisionAssignment` | `xlr8_admin_user_division_assignments`| Division-level access assignments for users. |
---

## 5. IAM & RBAC Models (`App\Models\IAM`)

| Model | Table | Description / Key Fields |
|---|---|---|
| `Role` | `xlr8_iam_roles` | Spatie role extension supporting temporal parameters and status flags. |
| `Permission` | `xlr8_iam_permissions` | Spatie permission extension (`name`, `guard_name`, `module_id`, `process_id`). |
| `UserRoleAssignment` | `xlr8_iam_user_role_assignments`| User role assignment with `valid_from`, `valid_to`, `is_active`. |
| `Module` | `xlr8_iam_modules` | Functional modules (e.g. Pricing, CRM, Booking, HR, Spares). |
| `Process` | `xlr8_iam_processes` | Sub-processes within modules for fine-grained action permissions. |
| `OtpToken` | `xlr8_iam_otp_tokens` | Active and consumed OTP tokens (`identifier`, `token`, `expires_at`, `verified_at`). |
| `OtpAttemptLog` | `xlr8_iam_otp_attempt_logs` | Audit of OTP generation and verification attempts for rate limiting. |
| `AccountLock` | `xlr8_iam_account_locks` | Temporary and permanent lockouts triggered by security thresholds. |
| `DeviceSession` | `xlr8_iam_device_sessions` | Registered user devices (`device_uuid`, `device_name`, `platform`, `last_login_at`). |
| `UserDeviceToken` | `xlr8_iam_user_device_tokens` | FCM push notification registration tokens per user device. |

---

## 6. CRM & Quotation Models (`App\Models\CRM`)

| Model | Table | Description / Key Fields |
|---|---|---|
| `Lead` | `xlr8_crm_leads` | Customer leads (`lead_code`, `customer_name`, `phone`, `email`, `lead_source_id`, `status`). |
| `LeadSource` | `xlr8_crm_lead_sources` | Master catalogue of lead generation channels. |
| `Campaign` | `xlr8_crm_campaigns` | Marketing campaigns (`code`, `name`, `start_date`, `end_date`, `budget`). |
| `Enquiry` | `xlr8_crm_enquiries` | Specific vehicle enquiry linked to a lead, consultant, and vehicle model/variant. |
| `Quotation` | `xlr8_crm_quotations` | Full commercial quote (`quote_no`, `customer_id`, `variant_code`, `on_road_price`, `json_breakup`, `status`). |
| `QuoteAction` | `xlr8_crm_quote_actions` | Action history on quotations (discount requested, manager approval, customer accepted). |

| `UserReporting` | `xlr8_admin_user_reportings` | Manager-subordinate reporting relationships (`manager_id`, `reportee_id`, `effective_from`, `effective_to`). |
---

## 7. Booking, Delivery & Operational Modules (`App\Models\Module\*`)

### Booking & Vehicles (`App\Models\Module\Booking`)
- `Booking`: Customer booking orders (`booking_no`, `enquiry_id`, `customer_id`, `variant_code`, `status_kw`).
- `Bookingamount`: Payment transactions, receipts, and booking token advance logs.
- `Stock`: Physical vehicle yard stock, chassis allocation, and availability tracking.
- `X_Vh_Order`: OEM factory vehicle supply orders.
- `X_Vh_Stock`: Vehicle stock inventory master (`vin_no`, `engine_no`, `chassis_no`, `aging_days`).
- `Xessories`: Selected accessories attached to a customer booking.
- `XlDelivery`: Vehicle delivery and handover checklist and sign-offs.
- `XlFeeCollection`: Registration fees, handling charges, and statutory payments collected.
- `Xl_DSA_Master`: Direct Selling Agent master registry.
- `Xl_Refunds`: Booking cancellation refund requests and transaction audits.

### Finance, Insurance & RTO (`App\Models\Module\Finance`, `Module\Insurance`, `Module\Rto`)
- `XFinance`: Retail vehicle loan application, financier, and disbursement tracking.
- `XFinanceTa`: Trade advance and inventory floorplan financing.
- `XlFinancier`: Bank and NBFC partner master catalogue.
- `XlInsurance`: Policy issuance details, cover note numbers, and premium records.
- `XlInsurer`: Insurance provider master registry (e.g. ICICI Lombard, Bajaj Allianz).
- `XlRto`: Vehicle registration status, RTO tax receipt, and high security registration plate (HSRP) logs.
- `XlRtoRules`: Local RTO tax calculation parameters and exemptions.
- `XExchange`: Used car exchange evaluation, valuation price, and bonus deductions.

### Spares Management (`App\Models\Module\Spare`)
- `XlSpareMaster`: Spare parts catalog (`part_number`, `name`, `category`, `mrp`, `ndp`, `hsn_code`).
- `XlSpareStock`: Inventory and bin locations across workshop warehouses.
- `XlSpareRequest`: Workshop technician requisition orders for repair jobs.
- `XlSpareRequestDetail`: Line-item breakdown of requested spare parts.
- `XlSpareOrder`: Purchase orders placed to OEM for spare parts replenishment.
- `XlSpareTransit`: In-transit tracking of ordered parts from OEM warehouse.
- `XlSpareBilledRo`: Spares billed against Repair Orders (RO).
- `XlSpareConsumed`: Parts consumed in service operations.
- `XlSpareClosure`: Daily/monthly workshop spare parts reconciliations.

---

## 8. Utilities, Documents, Settings & Core (`App\Models\Utilities\*`, `App\Models\Core`)

### KeyValue & Settings (`App\Models\Utilities\KeyValue`, `Utilities\Settings`, `Utilities\Synonym`)
- `KeywordMaster`: Keyword group identifiers (`FUEL`, `PERMIT`, `BODY_TYPE`, `VEHICLE_STATUS`).
- `Keyvalue`: Individual key-value rows linked to `KeywordMaster`.
- `SystemSetting`: Key-value application settings with typed validation rules.
- `SystemSettingAudit`: Full audit trail of configuration changes made to `SystemSetting`.
- `Synonym`: Entity normalization dictionary (`entity_type`, `canonical_name`, `synonym_name`).

### Documents & Notifications (`App\Models\Utilities\Docs`, `Utilities\Noty`, `Utilities\CommHistory`)
- `Document`: Spatie media-backed document metadata, Vision AI tags, and approval states.
- `DocGroup`: Grouping collections for related documents (e.g. Loan Dossier, Booking Kit).
- `DocAccess`: Granular user/role permissions for restricted documents.
- `Notification`: In-app and push notification persistence.
- `NotificationsMaster`: Notification templates and push triggers.
- `Alert`: System alerts, broadcasts, and flash notices.
- `Message`: Direct messaging and internal notifications.
- `CommMaster` / `CommThread` / `Chat`: Multi-party communication threads and history.

### Core System Models (`App\Models\Core`)
- `ApprovalHierarchy`: Multi-tier approval routing levels and power thresholds.
- `GraphNode` / `GraphEdge`: Workflow state machine graph definitions.
- `ReportingHierarchy`: Org tree reporting structure definitions.
- `Garage`: Dealership service garage / workshop locations.
- `ImportLog` / `ExportLog`: System-wide tracking of Excel / CSV batch jobs.

| `UserScope` | `xlr8_admin_user_scopes` | Arbitrary entity scoping (`scope_type`, `scope_code`) for users. |
| `PinCodes` | `xlr8_admin_pincodes` | Pincode master with post office, taluk, district, state, and lat/long mapping. |
