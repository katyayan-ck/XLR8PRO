# Model reference — class → table → writer → guide

Generated from the code (reflection) on 28-09-2026; regenerate when models change. "Writer" = the entity service
that is the only write path (DEC-050); blank = written by its owning service / controller (see the guide).

| Model (`App\Models\…`) | Table | Writer (`App\Services\…`) | Guide |
|---|---|---|---|
| `Admin\Branch` | `xlr8_admin_branch` | `Org\BranchService` | [org](../modules/org.md) |
| `Admin\Department` | `xlr8_admin_department` | `Org\DepartmentService` | [org](../modules/org.md) |
| `Admin\DesigDeptTree` | `xlr8_admin_desig_dept_tree` |  | [org](../modules/org.md) |
| `Admin\Designation` | `xlr8_admin_designation` | `Org\DesignationService` | [org](../modules/org.md) |
| `Admin\Division` | `xlr8_admin_division` | `Org\DivisionService` | [org](../modules/org.md) |
| `Admin\Employee` | `xlr8_admin_employee` | `Org\EmployeeService` | [org](../modules/org.md) |
| `Admin\EmployeeHistory` | `xlr8_admin_employee_history` |  | [org](../modules/org.md) |
| `Admin\Location` | `xlr8_admin_location` | `Org\LocationService` | [org](../modules/org.md) |
| `Admin\Person` | `xlr8_admin_person` | `Person\PersonRecordService` | [person](../modules/person.md) |
| `Admin\PersonAddress` | `xlr8_admin_person_addresses` | `Person\PersonAddressService` | [person](../modules/person.md) |
| `Admin\PersonBankingDetail` | `xlr8_admin_person_banking_details` | `Person\PersonBankingService` | [person](../modules/person.md) |
| `Admin\PersonContact` | `xlr8_admin_person_contacts` | `Person\PersonContactService` | [person](../modules/person.md) |
| `Admin\PersonUserType` | `xlr8_admin_person_user_types` |  | [person](../modules/person.md) |
| `Admin\PinCodes` | `bmpl_pincodes` |  | [org](../modules/org.md) |
| `Admin\UserReporting` | `xlr8_admin_user_reporting` |  | [org](../modules/org.md) |
| `Admin\UserScope` | `xlr8_admin_user_scopes` | `IAM\UserScopeService` | [iam-auth](../modules/iam-auth.md) |
| `Admin\UserType` | `xlr8_iam_user_type` |  | [org](../modules/org.md) |
| `Admin\Vertical` | `xlr8_admin_vertical` | `Org\VerticalService` | [org](../modules/org.md) |
| `Approval\ApprovalCounter` | `xlr8_approval_counter` |  | [07-approvals](../platform/07-approvals.md) |
| `Approval\ApprovalEvent` | `xlr8_approval_event` |  | [07-approvals](../platform/07-approvals.md) |
| `Approval\ApprovalRequest` | `xlr8_approval_request` |  | [07-approvals](../platform/07-approvals.md) |
| `Approval\ApprovalRule` | `xlr8_approval_rule` | `Platform\Approval\Entities\ApprovalRuleService` | [07-approvals](../platform/07-approvals.md) |
| `Approval\ApprovalRuleLevel` | `xlr8_approval_rule_level` |  | [07-approvals](../platform/07-approvals.md) |
| `Approval\ApprovalTopic` | `xlr8_approval_topic` | `Platform\Approval\Entities\ApprovalTopicService` | [07-approvals](../platform/07-approvals.md) |
| `CRM\Campaign` | `xlr8_crm_campaigns` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\Enquiry` | `xlr8_crm_enquiries` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\Lead` | `xlr8_crm_leads` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\LeadSource` | `xlr8_crm_lead_sources` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\Quotation` | `xlr8_crm_quotations` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\QuoteAction` | `xlr8_crm_quote_actions` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `CRM\TestDrive` | `xlr8_crm_testdrive` |  | [crm-enquiry-quotation](../modules/crm-enquiry-quotation.md) |
| `Comms\CommCall` | `xlr8_comm_call` |  | [13-telephony](../platform/13-telephony.md) |
| `Comms\CommOutbox` | `xlr8_comm_outbox` |  | [10-email](../platform/10-email.md) |
| `Comms\CommTemplate` | `xlr8_comm_template` |  | [09-templates](../platform/09-templates.md) |
| `Comms\CommTemplateVersion` | `xlr8_comm_template_version` |  | [09-templates](../platform/09-templates.md) |
| `Comms\WaMessage` | `xlr8_comm_wa_message` |  | [12-whatsapp](../platform/12-whatsapp.md) |
| `Comms\WaThread` | `xlr8_comm_wa_thread` |  | [12-whatsapp](../platform/12-whatsapp.md) |
| `Core\ApprovalHierarchy` | `xlr8_approval_hierarchies` |  | [utils-legacy](legacy-utils.md) |
| `Core\ExportLog` | `export_logs` |  | [utils-legacy](legacy-utils.md) |
| `Core\Garage` | `garages` |  | [utils-legacy](legacy-utils.md) |
| `Core\GraphEdge` | `graph_edges` |  | [utils-legacy](legacy-utils.md) |
| `Core\GraphNode` | `graph_nodes` |  | [utils-legacy](legacy-utils.md) |
| `Core\ImportLog` | `import_logs` |  | [utils-legacy](legacy-utils.md) |
| `IAM\AccountLock` | `xlr8_iam_account_lock` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\DeviceSession` | `xlr8_iam_device_session` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\Module` | `xlr8_iam_module` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\OtpAttemptLog` | `xlr8_iam_otp_attempt_log` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\OtpToken` | `xlr8_iam_otp_token` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\Permission` | `permissions` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\Process` | `xlr8_iam_process` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\Role` | `roles` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\UserDeviceToken` | `xlr8_iam_user_device_token` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\UserPermissionDenial` | `xlr8_iam_user_permission_denials` |  | [iam-auth](../modules/iam-auth.md) |
| `IAM\UserRoleAssignment` | `xlr8_iam_user_role_pivot` |  | [iam-auth](../modules/iam-auth.md) |
| `Module\Booking\Booking` | `xlr8_booking_master` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\Bookingamount` | `xlr8_booking_amount` |  | [accounts](../modules/accounts.md) |
| `Module\Booking\Stock` | `xlr8_booking_stock_master` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XExchange` | `xlr8_booking_exchange` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\Xessories` | `xlr8_booking_accessories` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XlDelivery` | `xlr8_booking_delivered` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XlFinancier` | `xlr8_booking_financier` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XlInsurer` | `xlr8_booking_insurer` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XlRto` | `xlr8_booking_rto` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\XlRtoRules` | `xlr8_booking_rto_rule` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\Xl_DSA_Master` | `xlr8_booking_dsa_master` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Booking\Xl_Refunds` | `xlr8_booking_refund` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Finance\XFinance` | `xlr8_booking_finance` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Finance\XlFinancier` | `xlr8_booking_financier` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Insurance\XlInsurance` | `xlr8_booking_insurance` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Insurance\XlInsurer` | `xlr8_booking_insurer` |  | [sales-booking](../modules/sales-booking.md) |
| `Module\Spare\XlSpareClosure` | `xlr8_spare_closure` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareConsumed` | `xlr8_spare_consumption` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareMaster` | `xlr8_spare_master` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareOrder` | `xlr8_spare_order` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareRequest` | `xlr8_spare_request` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareRequestDetail` | `xlr8_spare_req_details` |  | [spares](../modules/spares.md) |
| `Module\Spare\XlSpareStock` | `xlr8_spare_stock` |  | [spares](../modules/spares.md) |
| `Utilities\CommHistory\CommMaster` | `xlr8_utils_comm_master` |  | [03-chat](../platform/03-chat.md) |
| `Utilities\CommHistory\CommThread` | `xlr8_utils_comm_thread` |  | [03-chat](../platform/03-chat.md) |
| `Utilities\Docs\DocAccess` | `xlr8_utils_docs_access` |  | [04-docs](../platform/04-docs.md) |
| `Utilities\Docs\DocGroup` | `xlr8_utils_docs_group` |  | [04-docs](../platform/04-docs.md) |
| `Utilities\Docs\Document` | `xlr8_utils_docs_document` |  | [04-docs](../platform/04-docs.md) |
| `Utilities\KeyValue\Keyvalue` | `xlr8_utils_keyvalue` | `Utils\KeyvalueService` | [utils-legacy](legacy-utils.md) |
| `Utilities\KeyValue\KeywordMaster` | `xlr8_utils_keyword_master` | `Utils\KeywordMasterService` | [utils-legacy](legacy-utils.md) |
| `Utilities\Noty\Alert` | `xlr8_utils_noty_alert` |  | [02-notify](../platform/02-notify.md) |
| `Utilities\Noty\Message` | `xlr8_utils_noty_message` |  | [02-notify](../platform/02-notify.md) |
| `Utilities\Noty\Notification` | `xlr8_utils_noty_notification` |  | [02-notify](../platform/02-notify.md) |
| `Utilities\Noty\NotificationDispatch` | `xlr8_utils_noty_dispatch` |  | [02-notify](../platform/02-notify.md) |
| `Utilities\Noty\NotificationsMaster` | `xlr8_utils_noty_master` |  | [02-notify](../platform/02-notify.md) |
| `Utilities\Settings\SystemSetting` | `xlr8_utils_system_setting` |  | [utils-legacy](legacy-utils.md) |
| `Utilities\Settings\SystemSettingAudit` | `xlr8_utils_system_setting_audit` |  | [utils-legacy](legacy-utils.md) |
| `Utilities\Synonym` | `xlr8_utils_synonyms` |  | [utils-legacy](legacy-utils.md) |
| `Utilities\Task\Task` | `xlr8_utils_task` |  | [05-tasks](../platform/05-tasks.md) |
| `Utilities\Task\TaskPerson` | `xlr8_utils_task_person` |  | [05-tasks](../platform/05-tasks.md) |
| `Utilities\Ticket\Ticket` | `xlr8_utils_ticket` |  | [06-tickets](../platform/06-tickets.md) |
| `Utilities\Ticket\TicketPerson` | `xlr8_utils_ticket_person` |  | [06-tickets](../platform/06-tickets.md) |
| `Vehicle\Accessory` | `xlr8_vehicle_accessories` |  | [vehicle](../modules/vehicle.md) |
| `Vehicle\AccessoryScope` | `xlr8_vehicle_accessory_scopes` |  | [vehicle](../modules/vehicle.md) |
| `Vehicle\Brand` | `xlr8_vehicle_brand` |  | [vehicle](../modules/vehicle.md) |
| `Vehicle\Color` | `xlr8_vehicle_color` |  | [vehicle](../modules/vehicle.md) |
| `Vehicle\Pricing\Addon` | `xlr8_vehicle_pricing_addons` | `Vehicle\Pricing\Addons\AddonService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\AddonHistory` | `xlr8_vehicle_pricing_addon_history` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Affected` | `xlr8_vehicle_pricing_affected` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\ChangeFlag` | `xlr8_vehicle_pricing_change_flags` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Csd` | `xlr8_vehicle_pricing_csd` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\DealerCharge` | `xlr8_vehicle_pricing_dealer_charges` | `Vehicle\Pricing\Addons\DealerChargeService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Discount` | `xlr8_vehicle_pricing_discounts` | `Vehicle\Pricing\Addons\DiscountService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\DiscountHistory` | `xlr8_vehicle_pricing_discount_history` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Draft` | `xlr8_vehicle_pricing_draft` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Hold` | `xlr8_vehicle_pricing_holds` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\ImportSession` | `xlr8_vehicle_pricing_import_sessions` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\InsAddonRate` | `xlr8_vehicle_pricing_ins_addon_rates` | `Vehicle\Pricing\Rules\InsAddonRateService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\InsBaseRule` | `xlr8_vehicle_pricing_ins_base_rules` | `Vehicle\Pricing\Rules\InsBaseRuleService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\InsDefault` | `xlr8_vehicle_pricing_ins_defaults` | `Vehicle\Pricing\Rules\InsDefaultService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\InsIdvSlot` | `xlr8_vehicle_pricing_ins_idv_slots` | `Vehicle\Pricing\Rules\InsIdvSlotService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Pricing` | `xlr8_vehicle_pricing` | `Vehicle\Pricing\Prices\PriceService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\PricingHistory` | `xlr8_vehicle_pricing_history` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Profile` | `xlr8_vehicle_pricing_profile` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\RtoRule` | `xlr8_vehicle_pricing_rto_rules` | `Vehicle\Pricing\Rules\RtoRuleService` | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\SessionChange` | `xlr8_vehicle_pricing_session_changes` | written only by `Session\PricingChangeRecorder` (append-only log) | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\SheetHeader` | `xlr8_vehicle_pricing_sheet_headers` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\Snapshot` | `xlr8_vehicle_pricing_snapshots` |  | [pricing](../modules/pricing.md) |
| `Vehicle\Pricing\TcsConfig` | `xlr8_vehicle_pricing_tcs_config` | `Vehicle\Pricing\Rules\TcsConfigService` | [pricing](../modules/pricing.md) |
| `Vehicle\Segment` | `xlr8_vehicle_segment` | `Vehicle\SegmentService` | [vehicle](../modules/vehicle.md) |
| `Vehicle\SubSegment` | `xlr8_vehicle_subsegment` | `Vehicle\SubSegmentService` | [vehicle](../modules/vehicle.md) |
| `Vehicle\Variant` | `xlr8_vehicle_variant` | `Vehicle\VariantService` | [vehicle](../modules/vehicle.md) |
| `Vehicle\VehicleModel` | `xlr8_vehicle_model` | `Vehicle\VehicleModelService` | [vehicle](../modules/vehicle.md) |
| `User` | `users` | `IAM\UserService` | [core](core.md) |
