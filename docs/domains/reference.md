# Model reference — class → table → writer → guide

Generated from the code (reflection) on 28-09-2026; regenerate when models change. "Writer" = the entity service
that is the only write path (DEC-050); blank = written by its owning service / controller (see the guide).

| Model (`App\Models\…`) | Table | Writer (`App\Services\…`) | Guide |
|---|---|---|---|
| `Admin\Branch` | `xlr8_admin_branch` | `Org\BranchService` | [org](org.md) |
| `Admin\Department` | `xlr8_admin_department` | `Org\DepartmentService` | [org](org.md) |
| `Admin\DesigDeptTree` | `xlr8_admin_desig_dept_tree` |  | [org](org.md) |
| `Admin\Designation` | `xlr8_admin_designation` | `Org\DesignationService` | [org](org.md) |
| `Admin\Division` | `xlr8_admin_division` | `Org\DivisionService` | [org](org.md) |
| `Admin\Employee` | `xlr8_admin_employee` | `Org\EmployeeService` | [org](org.md) |
| `Admin\EmployeeHistory` | `xlr8_admin_employee_history` |  | [org](org.md) |
| `Admin\Location` | `xlr8_admin_location` | `Org\LocationService` | [org](org.md) |
| `Admin\Person` | `xlr8_admin_person` | `Person\PersonRecordService` | [person](person.md) |
| `Admin\PersonAddress` | `xlr8_admin_person_addresses` | `Person\PersonAddressService` | [person](person.md) |
| `Admin\PersonBankingDetail` | `xlr8_admin_person_banking_details` | `Person\PersonBankingService` | [person](person.md) |
| `Admin\PersonContact` | `xlr8_admin_person_contacts` | `Person\PersonContactService` | [person](person.md) |
| `Admin\PersonUserType` | `xlr8_admin_person_user_types` |  | [person](person.md) |
| `Admin\PinCodes` | `bmpl_pincodes` |  | [org](org.md) |
| `Admin\UserReporting` | `xlr8_admin_user_reporting` |  | [org](org.md) |
| `Admin\UserScope` | `xlr8_admin_user_scopes` | `IAM\UserScopeService` | [iam-auth](iam-auth.md) |
| `Admin\UserType` | `xlr8_iam_user_type` |  | [org](org.md) |
| `Admin\Vertical` | `xlr8_admin_vertical` | `Org\VerticalService` | [org](org.md) |
| `Approval\ApprovalCounter` | `xlr8_approval_counter` |  | [07-approvals](../utilities/07-approvals.md) |
| `Approval\ApprovalEvent` | `xlr8_approval_event` |  | [07-approvals](../utilities/07-approvals.md) |
| `Approval\ApprovalRequest` | `xlr8_approval_request` |  | [07-approvals](../utilities/07-approvals.md) |
| `Approval\ApprovalRule` | `xlr8_approval_rule` | `Platform\Approval\Entities\ApprovalRuleService` | [07-approvals](../utilities/07-approvals.md) |
| `Approval\ApprovalRuleLevel` | `xlr8_approval_rule_level` |  | [07-approvals](../utilities/07-approvals.md) |
| `Approval\ApprovalTopic` | `xlr8_approval_topic` | `Platform\Approval\Entities\ApprovalTopicService` | [07-approvals](../utilities/07-approvals.md) |
| `CRM\Campaign` | `xlr8_crm_campaigns` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\Enquiry` | `xlr8_crm_enquiries` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\Lead` | `xlr8_crm_leads` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\LeadSource` | `xlr8_crm_lead_sources` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\Quotation` | `xlr8_crm_quotations` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\QuoteAction` | `xlr8_crm_quote_actions` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `CRM\TestDrive` | `xlr8_crm_testdrive` |  | [crm-enquiry-quotation](crm-enquiry-quotation.md) |
| `Comms\CommCall` | `xlr8_comm_call` |  | [13-telephony](../utilities/13-telephony.md) |
| `Comms\CommOutbox` | `xlr8_comm_outbox` |  | [10-email](../utilities/10-email.md) |
| `Comms\CommTemplate` | `xlr8_comm_template` |  | [09-templates](../utilities/09-templates.md) |
| `Comms\CommTemplateVersion` | `xlr8_comm_template_version` |  | [09-templates](../utilities/09-templates.md) |
| `Comms\WaMessage` | `xlr8_comm_wa_message` |  | [12-whatsapp](../utilities/12-whatsapp.md) |
| `Comms\WaThread` | `xlr8_comm_wa_thread` |  | [12-whatsapp](../utilities/12-whatsapp.md) |
| `Core\ApprovalHierarchy` | `xlr8_approval_hierarchies` |  | [utils-legacy](utils-legacy.md) |
| `Core\ExportLog` | `export_logs` |  | [utils-legacy](utils-legacy.md) |
| `Core\Garage` | `garages` |  | [utils-legacy](utils-legacy.md) |
| `Core\GraphEdge` | `graph_edges` |  | [utils-legacy](utils-legacy.md) |
| `Core\GraphNode` | `graph_nodes` |  | [utils-legacy](utils-legacy.md) |
| `Core\ImportLog` | `import_logs` |  | [utils-legacy](utils-legacy.md) |
| `IAM\AccountLock` | `xlr8_iam_account_lock` |  | [iam-auth](iam-auth.md) |
| `IAM\DeviceSession` | `xlr8_iam_device_session` |  | [iam-auth](iam-auth.md) |
| `IAM\Module` | `xlr8_iam_module` |  | [iam-auth](iam-auth.md) |
| `IAM\OtpAttemptLog` | `xlr8_iam_otp_attempt_log` |  | [iam-auth](iam-auth.md) |
| `IAM\OtpToken` | `xlr8_iam_otp_token` |  | [iam-auth](iam-auth.md) |
| `IAM\Permission` | `permissions` |  | [iam-auth](iam-auth.md) |
| `IAM\Process` | `xlr8_iam_process` |  | [iam-auth](iam-auth.md) |
| `IAM\Role` | `roles` |  | [iam-auth](iam-auth.md) |
| `IAM\UserDeviceToken` | `xlr8_iam_user_device_token` |  | [iam-auth](iam-auth.md) |
| `IAM\UserPermissionDenial` | `xlr8_iam_user_permission_denials` |  | [iam-auth](iam-auth.md) |
| `IAM\UserRoleAssignment` | `xlr8_iam_user_role_pivot` |  | [iam-auth](iam-auth.md) |
| `Module\Booking\Booking` | `xlr8_booking_master` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\Bookingamount` | `xlr8_booking_amount` |  | [accounts](accounts.md) |
| `Module\Booking\Stock` | `xlr8_booking_stock_master` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XExchange` | `xlr8_booking_exchange` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\Xessories` | `xlr8_booking_accessories` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XlDelivery` | `xlr8_booking_delivered` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XlFinancier` | `xlr8_booking_financier` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XlInsurer` | `xlr8_booking_insurer` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XlRto` | `xlr8_booking_rto` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\XlRtoRules` | `xlr8_booking_rto_rule` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\Xl_DSA_Master` | `xlr8_booking_dsa_master` |  | [sales-booking](sales-booking.md) |
| `Module\Booking\Xl_Refunds` | `xlr8_booking_refund` |  | [sales-booking](sales-booking.md) |
| `Module\Finance\XFinance` | `xlr8_booking_finance` |  | [sales-booking](sales-booking.md) |
| `Module\Finance\XlFinancier` | `xlr8_booking_financier` |  | [sales-booking](sales-booking.md) |
| `Module\Insurance\XlInsurance` | `xlr8_booking_insurance` |  | [sales-booking](sales-booking.md) |
| `Module\Insurance\XlInsurer` | `xlr8_booking_insurer` |  | [sales-booking](sales-booking.md) |
| `Module\Spare\XlSpareClosure` | `xlr8_spare_closure` |  | [spares](spares.md) |
| `Module\Spare\XlSpareConsumed` | `xlr8_spare_consumption` |  | [spares](spares.md) |
| `Module\Spare\XlSpareMaster` | `xlr8_spare_master` |  | [spares](spares.md) |
| `Module\Spare\XlSpareOrder` | `xlr8_spare_order` |  | [spares](spares.md) |
| `Module\Spare\XlSpareRequest` | `xlr8_spare_request` |  | [spares](spares.md) |
| `Module\Spare\XlSpareRequestDetail` | `xlr8_spare_req_details` |  | [spares](spares.md) |
| `Module\Spare\XlSpareStock` | `xlr8_spare_stock` |  | [spares](spares.md) |
| `Utilities\CommHistory\CommMaster` | `xlr8_utils_comm_master` |  | [03-chat](../utilities/03-chat.md) |
| `Utilities\CommHistory\CommThread` | `xlr8_utils_comm_thread` |  | [03-chat](../utilities/03-chat.md) |
| `Utilities\Docs\DocAccess` | `xlr8_utils_docs_access` |  | [04-docs](../utilities/04-docs.md) |
| `Utilities\Docs\DocGroup` | `xlr8_utils_docs_group` |  | [04-docs](../utilities/04-docs.md) |
| `Utilities\Docs\Document` | `xlr8_utils_docs_document` |  | [04-docs](../utilities/04-docs.md) |
| `Utilities\KeyValue\Keyvalue` | `xlr8_utils_keyvalue` | `Utils\KeyvalueService` | [utils-legacy](utils-legacy.md) |
| `Utilities\KeyValue\KeywordMaster` | `xlr8_utils_keyword_master` | `Utils\KeywordMasterService` | [utils-legacy](utils-legacy.md) |
| `Utilities\Noty\Alert` | `xlr8_utils_noty_alert` |  | [02-notify](../utilities/02-notify.md) |
| `Utilities\Noty\Message` | `xlr8_utils_noty_message` |  | [02-notify](../utilities/02-notify.md) |
| `Utilities\Noty\Notification` | `xlr8_utils_noty_notification` |  | [02-notify](../utilities/02-notify.md) |
| `Utilities\Noty\NotificationDispatch` | `xlr8_utils_noty_dispatch` |  | [02-notify](../utilities/02-notify.md) |
| `Utilities\Noty\NotificationsMaster` | `xlr8_utils_noty_master` |  | [02-notify](../utilities/02-notify.md) |
| `Utilities\Settings\SystemSetting` | `xlr8_utils_system_setting` |  | [utils-legacy](utils-legacy.md) |
| `Utilities\Settings\SystemSettingAudit` | `xlr8_utils_system_setting_audit` |  | [utils-legacy](utils-legacy.md) |
| `Utilities\Synonym` | `xlr8_utils_synonyms` |  | [utils-legacy](utils-legacy.md) |
| `Utilities\Task\Task` | `xlr8_utils_task` |  | [05-tasks](../utilities/05-tasks.md) |
| `Utilities\Task\TaskPerson` | `xlr8_utils_task_person` |  | [05-tasks](../utilities/05-tasks.md) |
| `Utilities\Ticket\Ticket` | `xlr8_utils_ticket` |  | [06-tickets](../utilities/06-tickets.md) |
| `Utilities\Ticket\TicketPerson` | `xlr8_utils_ticket_person` |  | [06-tickets](../utilities/06-tickets.md) |
| `Vehicle\Accessory` | `xlr8_vehicle_accessories` |  | [vehicle](vehicle.md) |
| `Vehicle\AccessoryScope` | `xlr8_vehicle_accessory_scopes` |  | [vehicle](vehicle.md) |
| `Vehicle\Brand` | `xlr8_vehicle_brand` |  | [vehicle](vehicle.md) |
| `Vehicle\Color` | `xlr8_vehicle_color` |  | [vehicle](vehicle.md) |
| `Vehicle\Pricing\Addon` | `xlr8_vehicle_pricing_addons` | `Vehicle\Pricing\Addons\AddonService` | [pricing](pricing.md) |
| `Vehicle\Pricing\AddonHistory` | `xlr8_vehicle_pricing_addon_history` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Affected` | `xlr8_vehicle_pricing_affected` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\ChangeFlag` | `xlr8_vehicle_pricing_change_flags` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Csd` | `xlr8_vehicle_pricing_csd` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\DealerCharge` | `xlr8_vehicle_pricing_dealer_charges` | `Vehicle\Pricing\Addons\DealerChargeService` | [pricing](pricing.md) |
| `Vehicle\Pricing\Discount` | `xlr8_vehicle_pricing_discounts` | `Vehicle\Pricing\Addons\DiscountService` | [pricing](pricing.md) |
| `Vehicle\Pricing\DiscountHistory` | `xlr8_vehicle_pricing_discount_history` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Draft` | `xlr8_vehicle_pricing_draft` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Hold` | `xlr8_vehicle_pricing_holds` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\ImportSession` | `xlr8_vehicle_pricing_import_sessions` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\InsAddonRate` | `xlr8_vehicle_pricing_ins_addon_rates` | `Vehicle\Pricing\Rules\InsAddonRateService` | [pricing](pricing.md) |
| `Vehicle\Pricing\InsBaseRule` | `xlr8_vehicle_pricing_ins_base_rules` | `Vehicle\Pricing\Rules\InsBaseRuleService` | [pricing](pricing.md) |
| `Vehicle\Pricing\InsDefault` | `xlr8_vehicle_pricing_ins_defaults` | `Vehicle\Pricing\Rules\InsDefaultService` | [pricing](pricing.md) |
| `Vehicle\Pricing\InsIdvSlot` | `xlr8_vehicle_pricing_ins_idv_slots` | `Vehicle\Pricing\Rules\InsIdvSlotService` | [pricing](pricing.md) |
| `Vehicle\Pricing\Pricing` | `xlr8_vehicle_pricing` | `Vehicle\Pricing\Prices\PriceService` | [pricing](pricing.md) |
| `Vehicle\Pricing\PricingHistory` | `xlr8_vehicle_pricing_history` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Profile` | `xlr8_vehicle_pricing_profile` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\RtoRule` | `xlr8_vehicle_pricing_rto_rules` | `Vehicle\Pricing\Rules\RtoRuleService` | [pricing](pricing.md) |
| `Vehicle\Pricing\SheetHeader` | `xlr8_vehicle_pricing_sheet_headers` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\Snapshot` | `xlr8_vehicle_pricing_snapshots` |  | [pricing](pricing.md) |
| `Vehicle\Pricing\TcsConfig` | `xlr8_vehicle_pricing_tcs_config` | `Vehicle\Pricing\Rules\TcsConfigService` | [pricing](pricing.md) |
| `Vehicle\Segment` | `xlr8_vehicle_segment` | `Vehicle\SegmentService` | [vehicle](vehicle.md) |
| `Vehicle\SubSegment` | `xlr8_vehicle_subsegment` | `Vehicle\SubSegmentService` | [vehicle](vehicle.md) |
| `Vehicle\Variant` | `xlr8_vehicle_variant` | `Vehicle\VariantService` | [vehicle](vehicle.md) |
| `Vehicle\VehicleModel` | `xlr8_vehicle_model` | `Vehicle\VehicleModelService` | [vehicle](vehicle.md) |
| `User` | `users` | `IAM\UserService` | [core](core.md) |
