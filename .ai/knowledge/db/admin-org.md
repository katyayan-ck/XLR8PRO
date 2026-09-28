# admin-org tables (generated — do not edit)

## `xlr8_admin_approval_hierarchies` · ~0 rows · model: —
id bigint unsigned PK, approver_id bigint unsigned, level int, topic varchar(255), combo_json longtext?, powers_json longtext?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (approver_id), (created_by), (deleted_by), (topic,level), (updated_by)

## `xlr8_admin_branch` · ~3 rows · model: App\Models\Admin\Branch
id bigint unsigned PK, code varchar(255), branch_code varchar(10)?, name varchar(255), short_name varchar(255)?, description text?, phone varchar(255)?, email varchar(255)?, address text?, city varchar(255)?, state varchar(255)?, pincode varchar(255)?, country varchar(255), latitude decimal(10,8)?, longitude decimal(11,8)?, is_head_office tinyint(1), is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code), UNIQUE (code), (is_active), (is_active), (code), (created_by), (updated_by), (branch_code)

## `xlr8_admin_department` · ~6 rows · model: App\Models\Admin\Department
id bigint unsigned PK, code varchar(255), name varchar(255), description text?, parent_department_code varchar(10)?, branch_code varchar(10)?, head_code varchar(10)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (branch_code), (code), UNIQUE (code), (is_active), (parent_department_code), (is_active), (code), (created_by), (parent_department_code), (updated_by)

## `xlr8_admin_desig_dept_tree` · ~19 rows · model: App\Models\Admin\DesigDeptTree
id bigint unsigned PK, tree_code varchar(20), desig_code varchar(10), dept_code varchar(10), div_code varchar(10)?, reports_to_code varchar(20)?, display_name varchar(100)?, level tinyint, is_active tinyint(1), created_at timestamp?, updated_at timestamp?, deleted_at timestamp?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?
Indexes: (is_active), (created_by), (dept_code,div_code), (desig_code), (level), (reports_to_code), (tree_code), UNIQUE (tree_code)

## `xlr8_admin_designation` · ~76 rows · model: App\Models\Admin\Designation
id bigint unsigned PK, code varchar(255), name varchar(255), guard_name varchar(255), description text?, hierarchy_level int, rank tinyint unsigned, category varchar(30)?, is_top_mgmt tinyint(1), parent_desig_code varchar(50)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code), UNIQUE (code), (is_active), (is_active), (created_by), (hierarchy_level), (parent_desig_code), (rank), (is_top_mgmt)

## `xlr8_admin_division` · ~25 rows · model: App\Models\Admin\Division
id bigint unsigned PK, dept_code varchar(10)?, code varchar(255), name varchar(255), description text?, head_code varchar(50)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code), UNIQUE (code), (is_active), (is_active), (code), (created_by), (dept_code)

## `xlr8_admin_employee` · ~200 rows · model: App\Models\Admin\Employee
id bigint unsigned PK, code varchar(20), person_code varchar(20), desig_code varchar(20)?, designation_code varchar(20)?, primary_branch_code varchar(10)?, primary_dept_code varchar(10)?, primary_div_code varchar(10)?, primary_loc_code varchar(10)?, vertical_code varchar(10)?, segment_code varchar(10)?, sub_segment_code varchar(10)?, reporting_manager_code varchar(20)?, oem_id varchar(50)?, mile_id varchar(30)?, employment_type enum('permanent','probation','apprentice','contract','temporary'), employment_status enum('active','inactive','separated','terminated','absconded'), joining_date date?, confirmation_date date?, separation_date date?, separation_reason varchar(150)?, blood_group varchar(5)?, nationality varchar(50)?, father_name varchar(100)?, mother_name varchar(100)?, passport_no varchar(20)?, no_of_children tinyint unsigned?, marriage_date date?, biometric_id varchar(20)?, pf_eligible tinyint(1), pf_reg_type enum('new','existing')?, pf_number varchar(30)?, uan_number varchar(20)?, pf_joining_date date?, eps_membership tinyint(1), abry_eligible tinyint(1), esi_eligible tinyint(1), esi_number varchar(20)?, pt_establishment_id varchar(30)?, lwf_eligible tinyint(1), salary_payment_mode enum('bank','cash','cheque'), salary_structure_type enum('statutory_limit','above_statutory_limit'), shift_type enum('flexible','fixed'), shift_name varchar(50)?, late_arrival_window smallint unsigned?, early_going_window smallint unsigned?, leave_rule varchar(50)?, week_off varchar(30)?, wo_work_compensation tinyint(1), comp_off_applicable tinyint(1), reporting_emp_code varchar(20)?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (primary_branch_code), (code), (primary_dept_code), (desig_code), (joining_date), (primary_loc_code), (mile_id), (person_code), (employment_status,employment_type), UNIQUE (code), (desig_code), (employment_status), (employment_type), UNIQUE (esi_number), (person_code), (primary_branch_code), (primary_dept_code), (primary_div_code), (reporting_emp_code), UNIQUE (uan_number)

## `xlr8_admin_employee_history` · ~200 rows · model: App\Models\Admin\EmployeeHistory
id bigint unsigned PK, emp_code varchar(20), person_code varchar(20)?, designation_code varchar(20)?, primary_branch_code varchar(20)?, primary_loc_code varchar(20)?, primary_dept_code varchar(20)?, primary_div_code varchar(20)?, vertical_code varchar(20)?, segment_code varchar(20)?, sub_segment_code varchar(20)?, reporting_manager_code varchar(20)?, scopes json?, effective_from date?, effective_to date?, change_reason varchar(255)?, notes text?, created_by bigint unsigned?, created_at timestamp?, updated_at timestamp?
Indexes: (primary_branch_code,primary_loc_code), (designation_code), (emp_code,effective_from), (emp_code)

## `xlr8_admin_location` · ~16 rows · model: App\Models\Admin\Location
id bigint unsigned PK, branch_code varchar(255), code varchar(255), name varchar(255), description text?, phone varchar(255)?, email varchar(255)?, address text?, city varchar(255)?, state varchar(255)?, pincode varchar(255)?, latitude decimal(10,8)?, longitude decimal(11,8)?, is_active tinyint(1), is_sales_location tinyint(1), is_workshop tinyint(1), is_parts_location tinyint(1), is_stock_location tinyint(1), is_office_only tinyint(1), is_mwh tinyint(1), is_lmmws tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (branch_code,is_active), (created_by), (branch_code,is_sales_location,is_workshop,is_parts_location), (updated_by), (branch_code), (code), UNIQUE (code), (is_active)

## `xlr8_admin_person` · ~215 rows · model: App\Models\Admin\Person
id bigint unsigned PK, person_code varchar(20), entity_type enum('individual','legal_entity'), salutation varchar(10)?, first_name varchar(60)?, middle_name varchar(60)?, last_name varchar(60)?, display_name varchar(120)?, gender enum('Male','Female','Other','Prefer not to say')?, dob date?, marital_status enum('Single','Married','Divorced','Widowed')?, spouse_name varchar(100)?, occupation varchar(80)?, aadhaar_no varchar(20)?, pan_no varchar(15)?, tan_no varchar(15)?, gst_no varchar(20)?, extra_data json?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (aadhaar_no), (person_code), (pan_no), UNIQUE (aadhaar_no), (entity_type), (first_name), UNIQUE (gst_no), (last_name), UNIQUE (pan_no), UNIQUE (person_code), UNIQUE (tan_no)

## `xlr8_admin_person_addresses` · ~52 rows · model: App\Models\Admin\PersonAddress
id bigint unsigned PK, person_code varchar(20), address_type enum('Primary','Office','Home','Alternate','Permanent'), address_line_1 varchar(150)?, address_line_2 varchar(150)?, landmark varchar(80)?, city varchar(60)?, taluka varchar(60)?, district varchar(60)?, state varchar(60)?, country varchar(60)?, pincode varchar(10)?, latitude decimal(10,7)?, longitude decimal(10,7)?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (person_code,address_type), (person_code), (pincode)

## `xlr8_admin_person_banking_details` · ~51 rows · model: App\Models\Admin\PersonBankingDetail
id bigint unsigned PK, person_code varchar(20), account_type enum('Primary','Secondary','Joint','Trust'), bank_name varchar(80)?, branch_name varchar(80)?, account_number varchar(30)?, account_holder_name varchar(100)?, ifsc_code varchar(15)?, micr_code varchar(10)?, account_nature enum('Savings','Current','Salary','NRO','NRE')?, is_verified tinyint(1), verified_at timestamp?, verified_by bigint unsigned?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (person_code,account_type), (ifsc_code), (person_code)

## `xlr8_admin_person_contacts` · ~477 rows · model: App\Models\Admin\PersonContact
id bigint unsigned PK, person_code varchar(20), data_type enum('Mobile','Email','Landline','Fax'), contact_type enum('Primary','Alternate','Office','Home','Emergency'), contact_detail varchar(100), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (person_code,data_type,contact_type), (contact_detail), (person_code,data_type), (person_code)

## `xlr8_admin_person_user_types` · ~215 rows · model: —
id bigint unsigned PK, person_code varchar(20), user_id bigint unsigned?, user_type varchar(30), is_primary tinyint(1), is_active tinyint(1), meta json?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (person_code,user_type,deleted_at), (is_primary,is_active), (person_code), (user_id), (user_type)

## `xlr8_admin_user_reporting` · ~0 rows · model: App\Models\Admin\UserReporting
id bigint unsigned PK, user_id bigint unsigned, topic varchar(100), max_levels tinyint unsigned?, extra_data json?, reports_to_user_id bigint unsigned, scope_type varchar(50)?, scope_code varchar(50)?, is_active tinyint(1), from_date date?, to_date date?, notes text?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (user_id,topic,scope_type,scope_code), (reports_to_user_id), (user_id,topic,is_active)

## `xlr8_admin_user_scopes` · ~1465 rows · model: App\Models\Admin\UserScope
id bigint unsigned PK, user_id bigint unsigned, scope_type varchar(50), scope_code varchar(50), is_active tinyint(1), from_date date?, to_date date?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (scope_type,scope_code,is_active), (user_id,is_active), (user_id,scope_type), UNIQUE (user_id,scope_type,scope_code)

## `xlr8_admin_vertical` · ~2 rows · model: App\Models\Admin\Vertical
id bigint unsigned PK, code varchar(255), vert_code varchar(10)?, name varchar(255), description text?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (is_active), (code), (created_by), (vert_code), (code), UNIQUE (code), (is_active)
