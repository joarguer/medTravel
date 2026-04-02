-- MIGRACION: formalizar post_treatment_follow_up en booking_request_items
-- Objetivo: agregar metadata opcional para inicio de seguimiento post tratamiento.

SET @db := DATABASE();
SET @t := 'booking_request_items';

SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @has_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_status'
);

SET @has_follow_up_started_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'follow_up_started_at'
);

SET @has_follow_up_started_by := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'follow_up_started_by_user_id'
);

SET @has_updated_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'updated_at'
);

SET @has_provider_response_by := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'provider_response_by'
);

SET @sql := IF(
  @has_table = 0,
  'SELECT "booking_request_items_missing" AS status',
  IF(
    @has_follow_up_started_at = 0,
    "ALTER TABLE `booking_request_items` ADD COLUMN `follow_up_started_at` DATETIME NULL DEFAULT NULL AFTER `treatment_completed_by_user_id`",
    'SELECT "follow_up_started_at_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0,
  'SELECT "booking_request_items_missing" AS status',
  IF(
    @has_follow_up_started_by = 0,
    "ALTER TABLE `booking_request_items` ADD COLUMN `follow_up_started_by_user_id` INT NULL DEFAULT NULL AFTER `follow_up_started_at`",
    'SELECT "follow_up_started_by_user_id_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0 OR @has_status = 0 OR @has_follow_up_started_at = 0,
  'SELECT "post_follow_up_backfill_skipped" AS status',
  IF(
    @has_updated_at = 1,
    "UPDATE booking_request_items
     SET follow_up_started_at = COALESCE(follow_up_started_at, updated_at, NOW())
     WHERE item_status = 'post_treatment_follow_up' AND follow_up_started_at IS NULL",
    "UPDATE booking_request_items
     SET follow_up_started_at = COALESCE(follow_up_started_at, NOW())
     WHERE item_status = 'post_treatment_follow_up' AND follow_up_started_at IS NULL"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0 OR @has_status = 0 OR @has_follow_up_started_by = 0 OR @has_provider_response_by = 0,
  'SELECT "post_follow_up_by_backfill_skipped" AS status',
  "UPDATE booking_request_items
   SET follow_up_started_by_user_id = provider_response_by
   WHERE item_status = 'post_treatment_follow_up'
     AND follow_up_started_by_user_id IS NULL
     AND provider_response_by IS NOT NULL"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'booking_request_items_post_treatment_follow_up_ready' AS status;
