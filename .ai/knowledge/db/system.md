# system tables (generated — do not edit)

## `xlr8_system_failed_jobs` · ~4 rows · model: —
id bigint unsigned PK, uuid varchar(255), connection text, queue text, payload longtext, exception longtext, failed_at timestamp
Indexes: UNIQUE (uuid)

## `xlr8_system_job_batches` · ~0 rows · model: —
id varchar(255) PK, name varchar(255), total_jobs int, pending_jobs int, failed_jobs int, failed_job_ids longtext, options mediumtext?, cancelled_at int?, created_at int, finished_at int?

## `xlr8_system_jobs` · ~1 rows · model: —
id bigint unsigned PK, queue varchar(255), payload longtext, attempts tinyint unsigned, reserved_at int unsigned?, available_at int unsigned, created_at int unsigned
Indexes: (queue)

## `xlr8_system_sessions` · ~237 rows · model: —
id varchar(255) PK, user_id bigint unsigned?, ip_address varchar(45)?, user_agent text?, payload longtext, last_activity int
Indexes: (last_activity), (user_id)
