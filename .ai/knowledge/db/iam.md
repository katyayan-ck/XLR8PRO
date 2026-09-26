# iam tables (generated — do not edit)

## `xlr8_iam_account_lock` · ~0 rows · model: App\Models\IAM\AccountLock
id bigint unsigned PK, user_id bigint unsigned, locked_until timestamp, reason varchar(255), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (user_id)

## `xlr8_iam_device_session` · ~0 rows · model: App\Models\IAM\DeviceSession
id bigint unsigned PK, user_id bigint unsigned, device_id varchar(255), device_name varchar(255), platform varchar(255), last_active_at timestamp?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: UNIQUE (user_id,device_id), (created_by), (user_id)

## `xlr8_iam_model_has_permissions` · ~0 rows · model: —
permission_id bigint unsigned PK, model_type varchar(255) PK, model_id bigint unsigned PK
Indexes: (model_id,model_type)

## `xlr8_iam_model_has_roles` · ~167 rows · model: —
role_id bigint unsigned PK, model_type varchar(255) PK, model_id bigint unsigned PK
Indexes: (model_id,model_type)

## `xlr8_iam_module` · ~15 rows · model: App\Models\IAM\Module
id bigint unsigned PK, name varchar(255), code varchar(255), description text?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (is_active), (created_by), UNIQUE (code)

## `xlr8_iam_otp_attempt_log` · ~0 rows · model: App\Models\IAM\OtpAttemptLog
id bigint unsigned PK, user_id bigint unsigned?, mobile varchar(255), action varchar(255), ip_address varchar(255), user_agent varchar(255), reason varchar(255)?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (mobile,created_at), (user_id,action), (created_by), (deleted_by), (updated_by)

## `xlr8_iam_otp_token` · ~0 rows · model: App\Models\IAM\OtpToken
id bigint unsigned PK, user_id bigint unsigned, mobile varchar(10), otp_hash text, expires_at timestamp, used_at timestamp?, created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (expires_at), (mobile), (used_at), (user_id)

## `xlr8_iam_permissions` · ~226 rows · model: —
id bigint unsigned PK, name varchar(255), guard_name varchar(255), module_code varchar(255)?, process_code varchar(255)?, created_at timestamp?, updated_at timestamp?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, deleted_at timestamp?
Indexes: (guard_name), UNIQUE (name,guard_name)

## `xlr8_iam_process` · ~37 rows · model: App\Models\IAM\Process
id bigint unsigned PK, module_code varchar(255)?, code varchar(255), name varchar(255), description text?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (code,is_active), UNIQUE (code), (created_by), (deleted_by), (is_active), (module_code), (updated_by)

## `xlr8_iam_role_has_permissions` · ~2188 rows · model: —
permission_id bigint unsigned PK, role_id bigint unsigned PK
Indexes: (role_id)

## `xlr8_iam_user_device_token` · ~0 rows · model: App\Models\IAM\UserDeviceToken
id bigint unsigned PK, user_id bigint unsigned, device_id varchar(255), device_name varchar(255), platform varchar(255), platform_version varchar(255)?, fcm_token text, is_active tinyint(1), token_expires_at timestamp?, last_used_at timestamp?, last_notification_sent_at timestamp?, notification_count int, metadata longtext?, ip_address varchar(255)?, user_agent varchar(255)?, created_by bigint unsigned?, updated_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_at), (created_by), (device_id), UNIQUE (device_id), (is_active), (platform), (updated_by), (user_id)

## `xlr8_iam_user_division_pivot` · ~0 rows · model: —
id bigint unsigned PK, user_id bigint unsigned, division_id bigint unsigned, from_date date, to_date date?, is_current tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (division_id), UNIQUE (user_id,division_id), (user_id)

## `xlr8_iam_user_permission_denials` · ~0 rows · model: App\Models\IAM\UserPermissionDenial
id bigint unsigned PK, user_id bigint unsigned, permission_id bigint unsigned, created_by bigint unsigned?, created_at timestamp?, updated_at timestamp?
Indexes: (permission_id), (user_id), UNIQUE (user_id,permission_id)

## `xlr8_iam_user_role_pivot` · ~0 rows · model: App\Models\IAM\UserRoleAssignment
id bigint unsigned PK, user_id bigint unsigned, role_id bigint unsigned, from_date date, to_date date?, is_current tinyint(1), remarks text?, created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (role_id), (user_id), UNIQUE (user_id,role_id,from_date)

## `xlr8_iam_user_type` · ~3 rows · model: App\Models\Admin\UserType
id bigint unsigned PK, code varchar(255), display_name varchar(255), description text?, is_active tinyint(1), created_by bigint unsigned?, updated_by bigint unsigned?, deleted_by bigint unsigned?, created_at timestamp?, updated_at timestamp?, deleted_at timestamp?
Indexes: (created_by), (code), UNIQUE (code), (is_active)
