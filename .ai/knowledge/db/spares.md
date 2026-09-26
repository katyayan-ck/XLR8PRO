# spares tables (generated — do not edit)

## `xlr8_spare_billedro` · ~3548 rows · model: —
id int PK, ro_no varchar(25), bill_no varchar(25), bill_type int, bill_date date, rgn_no varchar(25), bill_status int, part_id int, qty int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_closure` · ~0 rows · model: App\Models\Module\Spare\XlSpareClosure
id int PK, ro_no varchar(15), srv_brnch_id int, workshop_type_id int, gm int, srvm int, cxm int, srv_adv int, floor_ctrl int, qual_ctrl int, tech1 int, tech2 int?, tech3 int?, remarks varchar(500)?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_consumption` · ~99 rows · model: App\Models\Module\Spare\XlSpareConsumed
id int PK, part_id int, category_id int?, division_id int?, store_id int, party_type_id int?, doc_id varchar(25)?, doc_date date, month varchar(6)?, req_quan varchar(20)?, iss_quan int, return_quan int, price varchar(20)?, mrp varchar(20), status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_master` · ~18719 rows · model: App\Models\Module\Spare\XlSpareMaster
id int PK, part_no varchar(25), category_id int?, division_id int?, name varchar(50), description varchar(250)?, mrp decimal(11,2)?, order_price decimal(11,2)?, sale_price decimal(11,2)?, dsid int, order_qty int, req_qnty int?, allot_qnty int?, iss_qnty int?, return_qnty int?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_order` · ~221 rows · model: App\Models\Module\Spare\XlSpareOrder
id int PK, sap_no int, part_id int, type_id int, bo_qty int, order_quan int, confirm_quan int?, deliver_quan int?, transporter varchar(25)?, store_id int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_req_details` · ~0 rows · model: App\Models\Module\Spare\XlSpareRequestDetail
id int PK, spare_req_id int, part_id int?, part_no varchar(20), part_name varchar(100), req_quan int, price decimal(11,2)?, order_type_id int, availability_id int?, alloted_quan int?, deallot_quan int, issued_quan int?, returned_quan int?, verified_return_quant int?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_request` · ~0 rows · model: App\Models\Module\Spare\XlSpareRequest
id int PK, srv_brnch_id int, srv_vh_cat_id int, workshop_type_id int, model varchar(50), variant varchar(50), regn_no varchar(25)?, cust_mobile varchar(15), cust_name varchar(50), person_id int?, ro_date date, ro_number varchar(25), remark text, billed_ro int?, billed_ro_date date?, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_stock` · ~14 rows · model: App\Models\Module\Spare\XlSpareStock
id int PK, part_id int, store_id int?, bin_id int?, cls_qnty int?, opn_qnty int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?

## `xlr8_spare_transit` · ~246 rows · model: —
id int PK, invoice_no varchar(20), part_id int, quantity int, price decimal(11,2), tax decimal(11,2)?, lr_date date?, store_id int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?
