# vehicle tables (generated — do not edit)

## `xlr8_vehicle_accessories` · ~1 rows · model: App\Models\Vehicle\Accessory
id bigint unsigned PK, part_no varchar(25), type varchar(30), display_name varchar(150)?, item varchar(150), ndp decimal(12,2)?, mrp decimal(12,2)?, set_qty int unsigned, discount decimal(8,4)?, details varchar(250)?, bundle tinyint(1), status tinyint, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (created_by), (deleted_by), (status,part_no), (type,status), (updated_by), UNIQUE (part_no)

## `xlr8_vehicle_accessory_scopes` · ~0 rows · model: App\Models\Vehicle\AccessoryScope
id bigint unsigned PK, part_no varchar(25), segment_code varchar(25)?, model_code varchar(25)?, variant_code varchar(50)?, permit varchar(30)?, status tinyint, created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (created_by), (deleted_by), (segment_code,model_code,variant_code,permit), (part_no,status), (updated_by), UNIQUE (part_no,segment_code,model_code,variant_code,permit)

## `xlr8_vehicle_color` · ~2548 rows · model: App\Models\Vehicle\Color
id bigint unsigned PK, segment_code varchar(5)?, sub_segment_code varchar(20)?, model_code varchar(30), variant_code varchar(20), code varchar(5), name varchar(255)?, hex_code varchar(255)?, image varchar(255)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (deleted_by), (is_active), (model_code), UNIQUE (model_code,variant_code,code), (segment_code), (sub_segment_code), (updated_by)

## `xlr8_vehicle_model` · ~50 rows · model: App\Models\Vehicle\VehicleModel
id bigint unsigned PK, segment_code varchar(10), sub_segment_code varchar(20)?, code varchar(40), name varchar(255), oem_name varchar(255)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code), (segment_code), UNIQUE (code), (created_by), (deleted_by), (is_active), (oem_name), (segment_code), (sub_segment_code), (updated_by)

## `xlr8_vehicle_pricing` · ~2 rows · model: App\Models\Vehicle\Pricing\Pricing
id bigint unsigned PK, import_session_id bigint unsigned?, model_code varchar(40), channel varchar(20), wef_date date, expired_on date?, is_active tinyint(1), ex_showroom_price decimal(14,2), assessable_value_with_freight decimal(14,2), gst_percent decimal(5,2), gst_amount decimal(14,2), mm_invoice_amount decimal(14,2), dealer_margin decimal(14,2), curr_oem_scheme decimal(14,2), curr_dealer_cont decimal(14,2), curr_cash_discount decimal(14,2), curr_acc_discount decimal(14,2), curr_shield_discount decimal(14,2), old_oem_scheme decimal(14,2), old_dealer_cont decimal(14,2), old_cash_discount decimal(14,2), old_acc_discount decimal(14,2), old_shield_discount decimal(14,2), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (import_session_id), (model_code,is_active), UNIQUE (model_code,channel,wef_date)

## `xlr8_vehicle_segment` · ~6 rows · model: App\Models\Vehicle\Segment
id bigint unsigned PK, code varchar(10), name varchar(255), is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (code), (created_by), (deleted_by), (is_active), (updated_by)

## `xlr8_vehicle_subsegment` · ~8 rows · model: App\Models\Vehicle\SubSegment
id bigint unsigned PK, segment_code varchar(10), code varchar(20), name varchar(255), is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (deleted_by), (is_active), UNIQUE (segment_code,code), (segment_code), (updated_by)

## `xlr8_vehicle_variant` · ~2474 rows · model: App\Models\Vehicle\Variant
id bigint unsigned PK, segment_code varchar(5), sub_segment_code varchar(20)?, model_code varchar(30), code varchar(40), oem_name varchar(255), custom_name varchar(255)?, display_name varchar(255)?, color varchar(255)?, color_code varchar(10)?, permit_id bigint unsigned?, taxi_price varchar(10), fuel_type_id bigint unsigned?, seating_capacity int unsigned?, wheels tinyint unsigned, gvw int unsigned?, gst_percent decimal(5,2)?, cc_capacity varchar(255)?, motor varchar(50)?, transmission varchar(255)?, drivetrain varchar(255)?, body_type_id bigint unsigned?, body_make_id bigint unsigned?, is_csd tinyint(1), csd_index varchar(255)?, shield_pack varchar(25)?, status_id bigint unsigned?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code), (model_code), (body_make_id), (body_type_id), (segment_code), (created_by), (deleted_by), (fuel_type_id), (is_active), (model_code), (model_code), (permit_id), (segment_code), (status_id), (sub_segment_code), (updated_by)
