-- =======================================================
-- MIGRACION: calendar_events (MVP calendario scoped)
-- Fecha: 2026-02-19
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'calendar_events';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `calendar_events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `start_at` DATETIME NOT NULL,
    `end_at` DATETIME NULL,
    `all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `event_type` ENUM('CARE','ITEM') NOT NULL,
    `request_id` INT(11) NULL,
    `item_id` INT(11) NULL,
    `thread_id` VARCHAR(64) NULL,
    `created_by_role` ENUM('CLIENT','PROVIDER','ADMIN','PATIENTCARE') NOT NULL,
    `created_by_user_id` INT(11) NULL,
    `provider_id` INT(11) NULL,
    `client_user_id` INT(11) NULL,
    `status` ENUM('scheduled','proposed','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_calendar_events_request_start'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_request_start` (`request_id`, `start_at`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_calendar_events_item_start'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_item_start` (`item_id`, `start_at`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_calendar_events_client_start'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_client_start` (`client_user_id`, `start_at`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_calendar_events_provider_start'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `calendar_events` ADD INDEX `idx_calendar_events_provider_start` (`provider_id`, `start_at`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'calendar_events_ready' AS status;
