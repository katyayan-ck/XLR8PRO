# vehicle-pricing tables (generated — do not edit)

## `xlr8_vehicle_pricing_addon_history` · ~0 rows · model: App\Models\Vehicle\Pricing\AddonHistory
id bigint unsigned PK, addon_id bigint unsigned?, model_code varchar(40)?, addon_type varchar(20)?, payload json?, action varchar(30)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?

## `xlr8_vehicle_pricing_addons` · ~0 rows · model: App\Models\Vehicle\Pricing\Addon
id bigint unsigned PK, import_session_id bigint unsigned?, segment varchar(20)?, model_code varchar(40), variant_code varchar(40)?, addon_type varchar(20), scheme_name varchar(100)?, shield_pack varchar(40)?, transmission varchar(30)?, fuel varchar(30)?, permit varchar(30)?, name varchar(100)?, tenure_years tinyint unsigned?, amount decimal(14,2), oem_share decimal(14,2), dealer_share decimal(14,2), default_allocation varchar(5), is_default tinyint(1), wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (model_code,addon_type,is_active), (import_session_id)

## `xlr8_vehicle_pricing_affected` · ~0 rows · model: App\Models\Vehicle\Pricing\Affected
id bigint unsigned PK, import_session_id bigint unsigned, model_code varchar(40), variant_code varchar(40)?, status varchar(20), error_message varchar(500)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (status), UNIQUE (import_session_id,model_code,variant_code)

## `xlr8_vehicle_pricing_change_flags` · ~54 rows · model: App\Models\Vehicle\Pricing\ChangeFlag
id bigint unsigned PK, import_session_id bigint unsigned, segment varchar(20)?, change_type varchar(40), model_code varchar(40)?, variant_code varchar(40)?, field_name varchar(60)?, old_value text?, new_value text?, is_processed tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (model_code), (import_session_id,is_processed), (change_type)

## `xlr8_vehicle_pricing_csd` · ~0 rows · model: App\Models\Vehicle\Pricing\Csd
id bigint unsigned PK, model_code varchar(40), wef_date date, expired_on date?, is_active tinyint(1), ex_showroom_price decimal(14,2), extra_json json?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: UNIQUE (model_code,wef_date)

## `xlr8_vehicle_pricing_dealer_charges` · ~0 rows · model: App\Models\Vehicle\Pricing\DealerCharge
id bigint unsigned PK, import_session_id bigint unsigned?, segment varchar(20), permit varchar(30)?, model_code varchar(40)?, charge_code varchar(40)?, charge_name varchar(80)?, amount decimal(12,2)?, incidental decimal(12,2), fastag decimal(12,2), trc decimal(12,2), rto_tape decimal(12,2), cod decimal(12,2), kazam decimal(12,2), extra_json json?, wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (segment,is_active)

## `xlr8_vehicle_pricing_discount_history` · ~0 rows · model: App\Models\Vehicle\Pricing\DiscountHistory
id bigint unsigned PK, discount_id bigint unsigned?, model_code varchar(40)?, payload json?, action varchar(30)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?

## `xlr8_vehicle_pricing_discounts` · ~0 rows · model: App\Models\Vehicle\Pricing\Discount
id bigint unsigned PK, import_session_id bigint unsigned?, model_code varchar(40), variant_code varchar(40)?, discount_type varchar(40), scheme_name varchar(120)?, category varchar(80)?, discount_category varchar(40)?, name varchar(120), oem_share decimal(14,2), dealer_share decimal(14,2), amount decimal(14,2)?, total_discount decimal(14,2), allocation_type varchar(5), is_conditional tinyint(1), linked_to varchar(60)?, extra_json json?, wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (allocation_type), (model_code,discount_type,is_active)

## `xlr8_vehicle_pricing_draft` · ~0 rows · model: App\Models\Vehicle\Pricing\Draft
id bigint unsigned PK, model_code varchar(40), permit varchar(30), vin_type varchar(20), channel varchar(20), payload json, ex_showroom decimal(14,2)?, on_road_price decimal(14,2)?, import_session_id bigint unsigned?, generated_at timestamp?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: UNIQUE (model_code,permit,vin_type,channel)

## `xlr8_vehicle_pricing_history` · ~0 rows · model: App\Models\Vehicle\Pricing\PricingHistory
id bigint unsigned PK, pricing_id bigint unsigned?, model_code varchar(40), channel varchar(20), wef_date date?, payload json?, action varchar(30)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (model_code)

## `xlr8_vehicle_pricing_holds` · ~0 rows · model: App\Models\Vehicle\Pricing\Hold
id bigint unsigned PK, scope varchar(30), is_held tinyint(1), held_at timestamp?, held_by bigint unsigned?, hold_reason varchar(255)?, reopened_at timestamp?, reopened_by bigint unsigned?, reopen_reason varchar(255)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: UNIQUE (scope)

## `xlr8_vehicle_pricing_import_sessions` · ~1 rows · model: App\Models\Vehicle\Pricing\ImportSession
id bigint unsigned PK, wef_date date?, hold_scopes json?, status varchar(32), current_stage varchar(32)?, selected_sheets json?, selected_segments json?, stats json?, source_filename varchar(191)?, notes text?, cancelled_at timestamp?, cancelled_by bigint unsigned?, remarks text?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (status), (wef_date)

## `xlr8_vehicle_pricing_ins_addon_rates` · ~0 rows · model: App\Models\Vehicle\Pricing\InsAddonRate
id bigint unsigned PK, import_session_id bigint unsigned?, insurance_company varchar(40), permit varchar(30), addon_slug varchar(40), addon_name varchar(80)?, rate_type varchar(20), rate_value decimal(12,4), applies_on varchar(20), wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (insurance_company,permit,is_active)

## `xlr8_vehicle_pricing_ins_base_rules` · ~0 rows · model: App\Models\Vehicle\Pricing\InsBaseRule
id bigint unsigned PK, import_session_id bigint unsigned?, company varchar(40)?, plan varchar(20)?, od_years tinyint unsigned?, tp_years tinyint unsigned?, permit varchar(30), fuel_type varchar(30)?, wheels tinyint unsigned?, seating varchar(30)?, cc_range varchar(30)?, gvw_range varchar(30)?, od_factor decimal(10,6), od_surcharge decimal(8,4), od_discount_rate decimal(8,2), tp_basic decimal(12,2), tp_per_passenger decimal(12,2), tp_legal_driver decimal(12,2), tp_non_fare_passenger decimal(12,2), tp_bi_fuel_kit decimal(12,2), wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (permit,is_active)

## `xlr8_vehicle_pricing_ins_defaults` · ~0 rows · model: App\Models\Vehicle\Pricing\InsDefault
id bigint unsigned PK, import_session_id bigint unsigned?, model_code varchar(40), permit varchar(30), insurance_company varchar(40), priority smallint unsigned, is_default tinyint(1), is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (model_code,permit)

## `xlr8_vehicle_pricing_ins_idv_slots` · ~0 rows · model: App\Models\Vehicle\Pricing\InsIdvSlot
id bigint unsigned PK, base_rule_id bigint unsigned, year_no tinyint unsigned, idv_basis varchar(60)?, idv_pct decimal(6,3)?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (base_rule_id)

## `xlr8_vehicle_pricing_profile` · ~54 rows · model: App\Models\Vehicle\Pricing\Profile
id bigint unsigned PK, import_session_id bigint unsigned?, model_code varchar(40), variant_code varchar(40)?, segment varchar(20)?, permit varchar(30)?, taxi_price tinyint(1), fuel_type varchar(30)?, wheels tinyint unsigned?, seating smallint unsigned?, cc_or_power varchar(30)?, gvw varchar(30)?, body_type varchar(30)?, transmission varchar(30)?, is_vehicle_master_complete tinyint(1), is_pricing_template_complete tinyint(1), is_rule_profile_complete tinyint(1), is_publishable tinyint(1), is_disabled tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (is_publishable), (segment), UNIQUE (model_code)

## `xlr8_vehicle_pricing_rto_rules` · ~0 rows · model: App\Models\Vehicle\Pricing\RtoRule
id bigint unsigned PK, import_session_id bigint unsigned?, code varchar(50)?, permit varchar(30), wheels tinyint unsigned?, reg_type varchar(20)?, body_type varchar(30)?, gvw_range varchar(30)?, seater varchar(20)?, fuel_type varchar(30)?, cc_range varchar(30)?, tax_factor decimal(10,6), tax_basis varchar(120)?, tax_slab varchar(50)?, surcharge decimal(12,2), surcharge_formula varchar(120)?, hypothecation decimal(12,2), green_tax decimal(12,2), registration_fee decimal(12,2), duplicate_tax_card decimal(12,2), fitness decimal(12,2), penalty decimal(12,2), rto_tape decimal(12,2), wef_date date?, expired_on date?, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?, extra_json json?
Indexes: (import_session_id), (permit,is_active)

## `xlr8_vehicle_pricing_sheet_headers` · ~176 rows · model: App\Models\Vehicle\Pricing\SheetHeader
id bigint unsigned PK, sheet_code varchar(64), field_code varchar(64), label varchar(191), aliases json?, data_type varchar(32), is_required tinyint(1), sort_order int, is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (sheet_code,is_active), UNIQUE (sheet_code,field_code)

## `xlr8_vehicle_pricing_snapshots` · ~0 rows · model: App\Models\Vehicle\Pricing\Snapshot
id bigint unsigned PK, import_session_id bigint unsigned?, model_code varchar(40), variant_code varchar(40)?, channel varchar(16), vin_type varchar(8), wef_date date, payload json, is_active tinyint(1), expired_on date?, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (is_active,wef_date), (import_session_id), UNIQUE (model_code,channel,vin_type,wef_date)

## `xlr8_vehicle_pricing_tcs_config` · ~0 rows · model: App\Models\Vehicle\Pricing\TcsConfig
id bigint unsigned PK, limit_amount decimal(14,2), rate_pct decimal(5,2), is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
