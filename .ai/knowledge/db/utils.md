# utils tables (generated — do not edit)

## `xlr8_utils_chat` · ~0 rows · model: —
id bigint unsigned PK, type varchar(255), participants longtext, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?

## `xlr8_utils_comm_master` · ~41 rows · model: App\Models\Utilities\CommHistory\CommMaster
id bigint unsigned PK, entityable_type varchar(255), entityable_id bigint unsigned, title varchar(255)?, description text?, status_id bigint unsigned?, action_id bigint unsigned?, extra_data json?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (entityable_type,entityable_id), (action_id), (status_id)

## `xlr8_utils_comm_thread` · ~151 rows · model: App\Models\Utilities\CommHistory\CommThread
id bigint unsigned PK, comm_master_id bigint unsigned, parent_id bigint unsigned?, actor_id bigint unsigned, action_id bigint unsigned?, title varchar(255)?, body text?, extra_data json?, _lft bigint unsigned, _rgt bigint unsigned, depth int unsigned, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (comm_master_id,_lft,_rgt), (action_id), (actor_id), (parent_id)

## `xlr8_utils_docs_access` · ~0 rows · model: —
id bigint unsigned PK, document_id bigint unsigned, user_id bigint unsigned?, access_type varchar(255)?, access_combo longtext?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (deleted_by), (document_id), (updated_by), (user_id)

## `xlr8_utils_docs_document` · ~0 rows · model: —
id bigint unsigned PK, documentable_type varchar(255), documentable_id bigint unsigned, title varchar(255), description text?, category_id bigint unsigned?, expiry_date date?, tags longtext?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (category_id), (created_by), (deleted_by), (documentable_type,documentable_id), (updated_by)

## `xlr8_utils_docs_group` · ~0 rows · model: —
id bigint unsigned PK, user_id bigint unsigned, name varchar(255), description text?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (deleted_by), (updated_by), (user_id)

## `xlr8_utils_docs_group_pivot` · ~0 rows · model: —
id bigint unsigned PK, doc_group_id bigint unsigned, document_id bigint unsigned, created_at timestamp?, updated_at timestamp?
Indexes: (doc_group_id), (document_id)

## `xlr8_utils_enum_columns` · ~98 rows · model: —
id int PK, keyword varchar(50), name varchar(100)?, tbl_name varchar(50)?, col_name varchar(50)?, details varchar(250)?, recursive int, status int, created_at timestamp, created_by int?, updated_at timestamp?, updated_by int?, deleted_at timestamp?, deleted_by int?
Indexes: UNIQUE (keyword)

## `xlr8_utils_keyvalue` · ~5478 rows · model: App\Models\Utilities\KeyValue\Keyvalue
id bigint unsigned PK, keyword_code varchar(50)?, key varchar(255)?, code varchar(150), value text, details text?, parent_id varchar(255)?, level int, path text?, extra_data longtext?, status int, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (keyword_code,code), (created_by), (deleted_by), (parent_id), (status), (updated_by), UNIQUE (keyword_code,code)

## `xlr8_utils_keyword_master` · ~127 rows · model: App\Models\Utilities\KeyValue\KeywordMaster
id bigint unsigned PK, code varchar(50)?, keyword varchar(255), description text?, is_recursive tinyint(1), details text?, extra_data longtext?, status int, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (keyword), (created_by), (deleted_by), (keyword), UNIQUE (keyword), (status), (updated_by), UNIQUE (code)

## `xlr8_utils_noty_alert` · ~0 rows · model: App\Models\Utilities\Noty\Alert
id bigint unsigned PK, user_id bigint unsigned, sender_id bigint unsigned?, severity varchar(255), title varchar(255), description text, reference_type varchar(255)?, reference_id bigint unsigned?, is_read tinyint(1), read_at timestamp?, is_sent_via_fcm tinyint(1), sent_at timestamp?, payload longtext?, metadata longtext?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (created_by), (deleted_by), (is_read), (reference_type,reference_id), (sender_id), (severity), (updated_by), (user_id)

## `xlr8_utils_noty_master` · ~0 rows · model: —
id bigint unsigned PK, user_id bigint unsigned, total_count int unsigned, unread_count int unsigned, created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?
Indexes: (created_by), (unread_count), (updated_by), UNIQUE (user_id)

## `xlr8_utils_noty_message` · ~0 rows · model: App\Models\Utilities\Noty\Message
id bigint unsigned PK, sender_id bigint unsigned, receiver_id bigint unsigned, message_text text, message_type varchar(255), reply_to_id bigint unsigned?, is_read tinyint(1), read_at timestamp?, is_sent_via_fcm tinyint(1), sent_at timestamp?, attachments longtext?, metadata longtext?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (created_by), (deleted_by), (is_read), (receiver_id), (sender_id), (sender_id,receiver_id), (updated_by)

## `xlr8_utils_noty_notification` · ~0 rows · model: App\Models\Utilities\Noty\Notification
id bigint unsigned PK, user_id bigint unsigned, sender_id bigint unsigned?, type varchar(255), title varchar(255), description text, reference_type varchar(255)?, reference_id bigint unsigned?, is_read tinyint(1), read_at timestamp?, is_sent_via_fcm tinyint(1), sent_at timestamp?, priority varchar(255), category varchar(255)?, payload longtext?, metadata longtext?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (created_by), (deleted_by), (is_read), (priority), (reference_type,reference_id), (sender_id), (type), (updated_by), (user_id)

## `xlr8_utils_synonyms` · ~24 rows · model: App\Models\Utilities\Synonym
id bigint unsigned PK, entity_type varchar(64), canonical varchar(128), synonym varchar(128), is_active tinyint(1), created_at timestamp?, created_by bigint unsigned?, updated_at timestamp?, updated_by bigint unsigned?, deleted_at timestamp?, deleted_by bigint unsigned?
Indexes: (entity_type,is_active), (entity_type,canonical), UNIQUE (entity_type,synonym)

## `xlr8_utils_system_setting` · ~11 rows · model: App\Models\Utilities\Settings\SystemSetting
id bigint unsigned PK, key varchar(255), label varchar(255)?, value text?, default_value text?, type varchar(255), input_type varchar(255), validation_rules text?, options longtext?, help_text text?, topic varchar(255)?, group varchar(255)?, sort_order int, description text?, iseditable tinyint(1), is_visible tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, is_deleted tinyint(1), deleted_at timestamp?
Indexes: (created_by), (group), (is_visible), (iseditable), (key), UNIQUE (key), (topic,group), (topic), (updated_by)

## `xlr8_utils_system_setting_audit` · ~0 rows · model: App\Models\Utilities\Settings\SystemSettingAudit
id bigint unsigned PK, setting_id bigint unsigned, user_id bigint unsigned?, action varchar(255), old_value longtext?, new_value longtext?, ip_address varchar(255)?, user_agent varchar(255)?, created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, is_deleted tinyint(1), deleted_at timestamp?
Indexes: (action), (created_by), (setting_id,created_at), (updated_by), (user_id,created_at)

## `xlr8_utils_system_setting_topic` · ~0 rows · model: —
id bigint unsigned PK, code varchar(255), label varchar(255), description text?, sort_order int, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, is_deleted tinyint(1), deleted_at timestamp?
Indexes: (code), UNIQUE (code), (created_by), (is_active), (updated_by)
