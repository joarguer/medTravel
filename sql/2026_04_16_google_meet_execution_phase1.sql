-- =======================================================
-- MIGRACION: Google Meet execution evidence - fase 1 minima
-- Fecha: 2026-04-16
-- Objetivo: persistencia tecnica aditiva para evidencia real
--            de ejecucion Meet sin tocar lifecycle clinico
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();

SET @calendar_table := 'calendar_events';
SET @calendar_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'google_meet_space_code'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `google_meet_space_code` VARCHAR(32) NULL AFTER `google_meet_url`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'google_meet_space_name'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `google_meet_space_name` VARCHAR(255) NULL AFTER `google_meet_space_code`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_execution_status'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_execution_status` VARCHAR(20) NULL AFTER `google_meet_space_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_started_at'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_started_at` DATETIME NULL AFTER `meeting_execution_status`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_ended_at'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_ended_at` DATETIME NULL AFTER `meeting_started_at`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_duration_seconds'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_duration_seconds` INT NULL AFTER `meeting_ended_at`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_detected_source'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_detected_source` VARCHAR(32) NULL AFTER `meeting_duration_seconds`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'conference_record_name'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `conference_record_name` VARCHAR(255) NULL AFTER `meeting_detected_source`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_last_event_type'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_last_event_type` VARCHAR(80) NULL AFTER `conference_record_name`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND COLUMN_NAME = 'meeting_last_detected_at'
);
SET @sql := IF(
  @calendar_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `calendar_events` ADD COLUMN `meeting_last_detected_at` DATETIME NULL AFTER `meeting_last_event_type`",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND INDEX_NAME = 'idx_calendar_events_meet_space_name'
);
SET @sql := IF(
  @calendar_exists = 1 AND @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_meet_space_name` (`google_meet_space_name`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @calendar_table AND INDEX_NAME = 'idx_calendar_events_conference_record'
);
SET @sql := IF(
  @calendar_exists = 1 AND @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_conference_record` (`conference_record_name`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @log_table := 'google_meet_event_log';
SET @log_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @log_table
);
SET @sql := IF(
  @log_exists = 0,
  "CREATE TABLE `google_meet_event_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `calendar_event_id` INT(11) NULL,
    `request_id` INT(11) NULL,
    `item_id` INT(11) NULL,
    `workspace_event_id` VARCHAR(255) NOT NULL,
    `workspace_event_type` VARCHAR(120) NOT NULL,
    `workspace_subscription_name` VARCHAR(255) NULL,
    `conference_record_name` VARCHAR(255) NULL,
    `google_meet_space_name` VARCHAR(255) NULL,
    `payload_json` LONGTEXT NULL,
    `published_at` DATETIME NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_google_meet_event_log_workspace_event` (`workspace_event_id`),
    KEY `idx_google_meet_event_log_calendar_event` (`calendar_event_id`),
    KEY `idx_google_meet_event_log_space_name` (`google_meet_space_name`),
    KEY `idx_google_meet_event_log_conference_record` (`conference_record_name`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @subs_table := 'google_meet_subscriptions';
SET @subs_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @subs_table
);
SET @sql := IF(
  @subs_exists = 0,
  "CREATE TABLE `google_meet_subscriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `calendar_event_id` INT(11) NOT NULL,
    `request_id` INT(11) NULL,
    `item_id` INT(11) NULL,
    `workspace_subscription_name` VARCHAR(255) NOT NULL,
    `target_resource` VARCHAR(255) NOT NULL,
    `topic_name` VARCHAR(255) NULL,
    `state` VARCHAR(32) NULL,
    `expire_time` DATETIME NULL,
    `last_lifecycle_event_type` VARCHAR(80) NULL,
    `last_error` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_google_meet_subscriptions_name` (`workspace_subscription_name`),
    KEY `idx_google_meet_subscriptions_calendar_event` (`calendar_event_id`),
    KEY `idx_google_meet_subscriptions_target_resource` (`target_resource`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'google_meet_execution_phase1_ready' AS status;
