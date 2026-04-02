-- MIGRACION: formalizar treatment_completed en booking_request_items
-- Objetivo: agregar metadata opcional de finalizacion y normalizar estado legacy completed.

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

SET @has_completed_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'treatment_completed_at'
);

SET @has_completed_by := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'treatment_completed_by_user_id'
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
    @has_completed_at = 0,
    "ALTER TABLE `booking_request_items` ADD COLUMN `treatment_completed_at` DATETIME NULL DEFAULT NULL AFTER `provider_response_at`",
    'SELECT "treatment_completed_at_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0,
  'SELECT "booking_request_items_missing" AS status',
  IF(
    @has_completed_by = 0,
    "ALTER TABLE `booking_request_items` ADD COLUMN `treatment_completed_by_user_id` INT NULL DEFAULT NULL AFTER `treatment_completed_at`",
    'SELECT "treatment_completed_by_user_id_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0 OR @has_status = 0,
  'SELECT "item_status_not_available" AS status',
  "UPDATE booking_request_items
   SET item_status = 'treatment_completed'
   WHERE item_status = 'completed'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0 OR @has_status = 0 OR @has_completed_at = 0,
  'SELECT "treatment_completed_backfill_skipped" AS status',
  IF(
    @has_updated_at = 1,
    "UPDATE booking_request_items
     SET treatment_completed_at = COALESCE(treatment_completed_at, updated_at, NOW())
     WHERE item_status = 'treatment_completed' AND treatment_completed_at IS NULL",
    "UPDATE booking_request_items
     SET treatment_completed_at = COALESCE(treatment_completed_at, NOW())
     WHERE item_status = 'treatment_completed' AND treatment_completed_at IS NULL"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_table = 0 OR @has_status = 0 OR @has_completed_by = 0 OR @has_provider_response_by = 0,
  'SELECT "treatment_completed_by_backfill_skipped" AS status',
  "UPDATE booking_request_items
   SET treatment_completed_by_user_id = provider_response_by
   WHERE item_status = 'treatment_completed'
     AND treatment_completed_by_user_id IS NULL
     AND provider_response_by IS NOT NULL"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'booking_request_items_treatment_completed_ready' AS status;
