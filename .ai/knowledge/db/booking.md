# booking tables (generated — do not edit)

## `xlr8_booking_accessories` · ~0 rows · model: App\Models\Module\Booking\Xessories
id int PK, segment varchar(25), model varchar(50), variant varchar(100), item varchar(100), part_no varchar(25)?, price int, details varchar(250)?, bundle tinyint(1), status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_amount` · ~49 rows · model: App\Models\Module\Booking\Bookingamount
id int PK, bid bigint?, enq_id bigint?, date date, type int?, type_number varchar(100)?, jv_cat int?, account_of varchar(50)?, mode varchar(100)?, amount varchar(100)?, trans_date date?, trans_no varchar(50)?, instrument_no varchar(50)?, bank varchar(50)?, hypo varchar(50)?, chassis_no varchar(50)?, vh_rgn_no varchar(50)?, otf_no varchar(50)?, inv_no varchar(50)?, location varchar(25)?, name varchar(100)?, care_of_type int?, care_of varchar(100)?, address varchar(500)?, mobile varchar(10)?, alternate_mobile varchar(10)?, used_model varchar(100)?, used_rgn_no varchar(100)?, from_dept varchar(100)?, to_dept varchar(100)?, exist_receipt_no varchar(100)?, party_name varchar(100)?, remarks varchar(150)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_delivered` · ~1 rows · model: App\Models\Module\Booking\XlDelivery
id int PK, bid int, verification int?, remarks text, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_dsa_master` · ~7 rows · model: App\Models\Module\Booking\Xl_DSA_Master
id int PK, name varchar(50), mobile varchar(15), email varchar(60), pincode int?, dlocation varchar(35), state varchar(20), firm_name varchar(30)?, firm_gst varchar(18)?, alt_mobile varchar(12)?, bank_name varchar(30)?, account_number varchar(20)?, ifsc varchar(20)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_exchange` · ~5 rows · model: App\Models\Module\Booking\XExchange
id int PK, bid int, vh_id int, purchase_type varchar(20), verification_status int, case_status int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_fee_collection` · ~0 rows · model: —
id int PK, customer_name varchar(50)?, care_of int?, care_of_name varchar(50)?, mobile_no varchar(10)?, model varchar(50)?, otf_no varchar(25)?, chassis_no varchar(25)?, branch int?, location int?, category_id int?, inv_no varchar(50)?, inv_date date?, permit_id int?, agent_id int?, agent_location int?, mode int?, amount decimal(15,2)?, data_status int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?
Indexes: UNIQUE (chassis_no)

## `xlr8_booking_finance` · ~15 rows · model: App\Models\Module\Finance\XFinance
id int PK, enq_no varchar(100)?, bid int, vh_id varchar(50)?, fin_mode varchar(50)?, financier int?, loan_status varchar(15)?, verification_status int, case_status int, case_lost_reason int?, instrument_type int?, instrument_ref_no varchar(50)?, loan_amount int?, margin int?, file_charge int?, subvention_amount decimal(15,2)?, fin_loan_amount int?, payout_category tinyint(1)?, nopayout_reason int?, expected_payout_pct decimal(8,4)?, gst_included decimal(3,2)?, inv1_no varchar(100)?, inv1_name varchar(100)?, inv1_prov_gst decimal(15,2)?, inv2_no varchar(100)?, inv2_name varchar(100)?, inv2_prov_gst decimal(15,2)?, consideration_no_gst decimal(15,2)?, difference decimal(15,2)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_financier` · ~86 rows · model: App\Models\Module\Booking\XlFinancier, XlFinancier
id int PK, name varchar(150), short_name varchar(50), status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_insurance` · ~1497 rows · model: App\Models\Module\Insurance\XlInsurance
id int PK, bid int?, source int?, insurer int?, insurer_code varchar(20)?, pol_no varchar(50)?, pol_date date?, pol_type int?, pol_tenure int?, policy_type varchar(50)?, insured_name varchar(50)?, mob_no varchar(50)?, rgn_no varchar(50)?, yom int?, ncb int?, vh_class varchar(50)?, pol_effective_date date?, pol_expiry_date date?, product_type varchar(50)?, model varchar(50)?, vh_body_type varchar(50)?, fuel varchar(50)?, vin varchar(50)?, engine_no varchar(50)?, created_date date?, payment_generation varchar(50)?, payment_no varchar(50)?, od_discount int?, total_idv bigint?, addon_prem_a int?, netod_prem_a int?, net_prem int?, imt23 int?, gross_prem int?, prev_pol_no int?, prev_insurance_company varchar(50)?, own_dmg_cover_start date?, own_dmg_cover_end date?, liability_cover_start date?, liability_cover_end date?, cpa_cover_start date?, cpa_cover_end date?, 64vb_status varchar(50)?, bundle_addon varchar(50)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_insurer` · ~30 rows · model: App\Models\Module\Booking\Xlinsurer, Xlinsurer
id int PK, name varchar(150), short_name varchar(50), status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_master` · ~45 rows · model: App\Models\Module\Booking\Booking
id int PK, enq_no varchar(50)?, quotation_id int?, b_type varchar(15), b_cat varchar(10)?, b_mode varchar(15)?, col_type int, col_by varchar(20)?, sap_no varchar(10)?, dms_no varchar(10)?, b_source varchar(25)?, dsa_id varchar(15)?, online_bk_ref_no varchar(15)?, booking_date date?, receipt_no varchar(25)?, receipt_date date?, booking_amount int?, payment_mode varchar(100)?, pan_no varchar(12)?, adhar_no varchar(15)?, gstn varchar(25)?, dms_otf varchar(25)?, order int?, otf_date date?, dms_so varchar(25)?, cpd date?, mapped int, chassis_no varchar(18)?, del_type varchar(10), del_date date?, inv_no varchar(15)?, inv_date date?, dealer_inv_no varchar(25)?, dealer_inv_date date?, cancel_date date?, refund_request_date date?, refund_date date?, refund_rejection_date date?, dealer_status int, pending int, pending_remark text, retail int, payout int?, status varchar(15), created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?, sale_type tinyint unsigned?, final_data json?, votf_no varchar(100)?, consultant varchar(50)?, buyer_type varchar(50)?, accessories text?, segment_code varchar(50)?
Indexes: (consultant), (segment_code)

## `xlr8_booking_refund` · ~13 rows · model: App\Models\Module\Booking\Xl_Refunds
id int PK, entity_type varchar(25), entity_id varchar(15), person_id int?, bank_name varchar(25), branch_name varchar(35), account_type varchar(15), account_number varchar(20), holder_name varchar(50), ifsc_code varchar(15), req_date date, req_by varchar(10), ref_date date?, ref_by varchar(10)?, transaction_details varchar(50)?, mode varchar(25)?, amount varchar(10)?, details varchar(250)?, remark text?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_rto` · ~1204 rows · model: App\Models\Module\Booking\XlRto
id int PK, bid int?, trade_used int?, sale_type int?, permit int?, body_type int?, rgn_type int?, rgn_no_type int?, trc_no varchar(50)?, trc_payment_no varchar(50)?, trc_amount bigint?, trc_trans_date date?, app_no varchar(50)?, tax_amount bigint?, tax_trans_date date?, tax_payment_bank_ref_no varchar(50)?, dms_otf varchar(100)?, chassis_no varchar(20)?, hsrp_location varchar(10)?, order_date date?, hsrp_front_lasercode varchar(50)?, hsrp_rear_lasercode varchar(50)?, prod_status varchar(10)?, recieving_status varchar(10)?, dispatch_date date?, order_delivery_date date?, affixation_date date?, pendat_id int?, purpose varchar(100)?, vh_rgn_no varchar(50)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_rto_rule` · ~76 rows · model: App\Models\Module\Booking\XlRtoRules
id bigint unsigned PK, sale_type varchar(255)?, permit varchar(255)?, body_type varchar(255)?, reg_no_type varchar(255)?, trc_number enum('Yes','No')?, trc_pay enum('Yes','No')?, trc_copy enum('Yes','No')?, app_no enum('Yes','No')?, tax_pay enum('Yes','No')?, veh_reg enum('Yes','No')?, tax_copy enum('Yes','No')?, pending_at varchar(100)?, rgn_no varchar(100)?, rto_status varchar(200)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_rto_value` · ~3291 rows · model: —
id int PK, vehicle_id int, permit varchar(25), inv int, rto_tax varchar(10), tax_amount int, rto_surcharge varchar(10), surcharge_amount int, rto_tax_rebate varchar(10), tax_rebate_amount int, rto_greentax int, rto_hpn int, rto_fitness int, rto_regn_fee int, rto_tcc int, rto_service_charge int, rto_sc_applicable int, net_rto_amount int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_booking_stock_master` · ~895 rows · model: App\Models\Module\Booking\Stock
id int PK, vehicle_oem_code varchar(20)?, model_code varchar(25), chasis_no varchar(25), location_id varchar(20), oem_invoice_no varchar(25)?, oem_invoice_date date?, so_number varchar(25)?, net_price decimal(11,2), v_status varchar(10), age int, stock_type varchar(10), alot_id varchar(15)?, alot_date datetime?, inv_id varchar(15)?, inv_date date?, pdi_ro_id int?, s_count int?, grn_no varchar(25)?, grn_date date?, damage tinyint(1), status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_financer_statement` · ~1568 rows · model: —
id int PK, financier_code varchar(50), trans_date date, trans_description varchar(150), trans_type varchar(5), do_no varchar(150), debit_amount decimal(15,2)?, credit_amount decimal(15,2)?, running_balance bigint?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?
