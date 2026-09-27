# other tables (generated — do not edit)

## `audits` · ~440 rows · model: —
id bigint unsigned PK, user_type varchar(255)?, user_id bigint unsigned?, event varchar(255), auditable_type varchar(255), auditable_id bigint unsigned, old_values text?, new_values text?, url text?, ip_address varchar(45)?, user_agent varchar(1023)?, tags varchar(255)?, created_at timestamp?, updated_at timestamp?
Indexes: (auditable_type,auditable_id), (user_id,user_type), (user_id,created_at), (auditable_type,auditable_id,event)

## `bmpl_pincodes` · ~158215 rows · model: App\Models\Admin\PinCodes
id int, status int, created_at timestamp, created_by int, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?, name varchar(150), level enum('STATE','DISTRICT','TEHSIL','POSTOFFICE'), pincode int, parent int

## `cache` · ~9 rows · model: —
key varchar(255) PK, value mediumtext, expiration int

## `cache_locks` · ~0 rows · model: —
key varchar(255) PK, owner varchar(255), expiration int

## `export_logs` · ~0 rows · model: App\Models\Core\ExportLog
id bigint unsigned PK, user_id bigint unsigned?, filename varchar(255), export_type enum('standard_users','rules_users','vehicle_inventory','custom'), total_records int, filters longtext?, file_path varchar(255)?, file_size int?, status enum('pending','processing','success','failed'), duration_seconds int?, started_at timestamp?, completed_at timestamp?, downloaded_at timestamp?, download_count int, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (downloaded_at), (export_type), (status), (user_id)

## `garages` · ~0 rows · model: App\Models\Core\Garage
id bigint unsigned PK, person_id bigint unsigned?, name varchar(255), type varchar(255)?, address varchar(255)?, city varchar(255)?, state varchar(255)?, pincode varchar(255)?, latitude decimal(10,8)?, longitude decimal(11,8)?, contact_person varchar(255)?, mobile varchar(255)?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (is_active), (is_active), (created_by), (person_id)

## `import_logs` · ~10 rows · model: App\Models\Core\ImportLog
id bigint unsigned PK, user_id bigint unsigned?, filename varchar(255), import_type enum('standard_users','rules_users','vehicle_definition','custom'), total_records int, imported_count int, skipped_count int, errors_count int, errors longtext?, warnings longtext?, status enum('pending','processing','success','partial','failed'), duration_seconds int?, started_at timestamp?, completed_at timestamp?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (import_type), (status), (user_id)

## `media` · ~92 rows · model: —
id bigint unsigned PK, model_type varchar(255), model_id bigint unsigned, uuid char(36)?, collection_name varchar(255), name varchar(255), file_name varchar(255), mime_type varchar(255)?, disk varchar(255), conversions_disk varchar(255)?, size bigint unsigned, manipulations longtext, custom_properties longtext, generated_conversions longtext, responsive_images longtext, order_column int unsigned?, created_at timestamp?, updated_at timestamp?
Indexes: (model_type,model_id), (order_column), UNIQUE (uuid)

## `migrations` · ~98 rows · model: —
id int unsigned PK, migration varchar(255), batch int

## `password_reset_tokens` · ~0 rows · model: —
email varchar(255) PK, token varchar(255), created_at timestamp?

## `personal_access_tokens` · ~0 rows · model: —
id bigint unsigned PK, tokenable_type varchar(255), tokenable_id bigint unsigned, name text, token varchar(64), abilities text?, last_used_at timestamp?, expires_at timestamp?, created_at timestamp?, updated_at timestamp?
Indexes: (expires_at), UNIQUE (token), (tokenable_type,tokenable_id)

## `users` · ~201 rows · model: App\Models\User
id bigint unsigned PK, username varchar(60), password varchar(255), user_type enum('Emp','Cust','DSA','Insurer','Associate'), person_code varchar(20)?, employee_code varchar(20)?, user_type_id bigint unsigned?, avatar varchar(200)?, is_active tinyint(1), bypass_data_scoping tinyint(1), last_login_at timestamp?, remember_token varchar(100)?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (employee_code), (person_code), (bypass_data_scoping), (employee_code), (is_active), (person_code), (user_type), UNIQUE (username)

## `variant_colors` · ~5886 rows · model: —
id bigint unsigned PK, variant_id bigint unsigned, color_id bigint unsigned, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?
Indexes: (color_id), (created_by), (variant_id)

## `xlr8_approval_counter` · ~0 rows · model: App\Models\Approval\ApprovalCounter
id bigint unsigned PK, request_id bigint unsigned, ask_revision int unsigned, level_no smallint unsigned, actor_id bigint unsigned, value decimal(15,2), remark varchar(1000)?, is_system tinyint(1), created_at timestamp?
Indexes: (request_id,ask_revision), (request_id)

## `xlr8_approval_event` · ~0 rows · model: App\Models\Approval\ApprovalEvent
id bigint unsigned PK, request_id bigint unsigned, type varchar(20), actor_id bigint unsigned?, ask_revision int unsigned?, level_no smallint unsigned?, value decimal(15,2)?, data json?, created_at timestamp?
Indexes: (request_id), (type,created_at)

## `xlr8_approval_request` · ~0 rows · model: App\Models\Approval\ApprovalRequest
id bigint unsigned PK, topic_id bigint unsigned, topic_code varchar(100), topic_title varchar(150), item_key varchar(60)?, mode varchar(15), source_type varchar(30)?, source_id bigint unsigned?, requester_id bigint unsigned, value_type varchar(12), asked decimal(15,2), ask_revision int unsigned, scope json?, snapshot json, status varchar(12), effective_level smallint unsigned?, effective_value decimal(15,2)?, effective_actor_id bigint unsigned?, current_level smallint unsigned?, auto_accepted tinyint(1), branch_code varchar(20)?, fy varchar(5)?, closed_at timestamp?, closed_by bigint unsigned?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (branch_code), (fy), (item_key), (requester_id), (source_type,source_id), (status), (topic_code), (topic_id)

## `xlr8_approval_rule` · ~0 rows · model: App\Models\Approval\ApprovalRule
id bigint unsigned PK, topic_id bigint unsigned, company_code varchar(50)?, zone_code varchar(50)?, state_code varchar(50)?, branch_code varchar(50)?, desk_code varchar(50)?, segment_code varchar(50)?, model_code varchar(50)?, variant_code varchar(50)?, permit_code varchar(50)?, channel_code varchar(50)?, valid_from date?, valid_to date?, is_active tinyint(1), note varchar(250)?, import_batch varchar(40)?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (topic_id), (topic_id,is_active)

## `xlr8_approval_rule_level` · ~0 rows · model: App\Models\Approval\ApprovalRuleLevel
id bigint unsigned PK, rule_id bigint unsigned, level_no smallint unsigned, designation_code varchar(50)?, user_ids json?, value_type varchar(12), std_value decimal(15,2)?, min_value decimal(15,2)?, max_value decimal(15,2)?, created_at timestamp?, updated_at timestamp?
Indexes: UNIQUE (rule_id,level_no), (rule_id)

## `xlr8_approval_topic` · ~12 rows · model: App\Models\Approval\ApprovalTopic
id bigint unsigned PK, code varchar(100), parent_id bigint unsigned?, title varchar(150), item_key varchar(60)?, mode varchar(15)?, value_type varchar(12)?, is_mandatory tinyint(1), is_active tinyint(1), description text?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (code), UNIQUE (item_key), (parent_id)

## `xlr8_user_branches` · ~279 rows · model: —
user_id bigint unsigned PK, branch_id bigint unsigned PK
