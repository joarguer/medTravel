-- =======================================================
-- MIGRACION: calendar_events referencias Google Meet MVP
-- Fecha: 2026-03-23
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'calendar_events';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'organizer_admin_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `organizer_admin_user_id` INT(11) NULL AFTER `client_user_id`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'google_event_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `google_event_id` VARCHAR(255) NULL AFTER `status`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'google_html_link'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `google_html_link` VARCHAR(500) NULL AFTER `google_event_id`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'google_meet_url'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `google_meet_url` VARCHAR(500) NULL AFTER `google_html_link`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'organizer_email'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `organizer_email` VARCHAR(255) NULL AFTER `google_meet_url`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_calendar_events_google_event'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_google_event` (`google_event_id`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'calendar_events_google_meet_refs_ready' AS status;