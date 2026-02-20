-- =======================================================
-- MIGRACION: data_deletion_requests workflow + status
-- Fecha: 2026-02-20
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'data_deletion_requests';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `data_deletion_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `request_id` VARCHAR(64) NOT NULL,
    `request_email` VARCHAR(255) DEFAULT NULL,
    `request_phone` VARCHAR(80) DEFAULT NULL,
    `request_name` VARCHAR(255) DEFAULT NULL,
    `request_message` TEXT DEFAULT NULL,
    `request_ip` VARCHAR(64) DEFAULT NULL,
    `request_user_agent` VARCHAR(512) DEFAULT NULL,
    `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `processed_at` DATETIME DEFAULT NULL,
    `processed_by_user_id` INT(11) DEFAULT NULL,
    `result_summary` TEXT DEFAULT NULL,
    `last_error` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_data_deletion_request_id` (`request_id`),
    KEY `idx_data_deletion_status_created` (`status`, `created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'status'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD COLUMN `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending'",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'processed_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD COLUMN `processed_at` DATETIME DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'processed_by_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD COLUMN `processed_by_user_id` INT(11) DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'result_summary'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD COLUMN `result_summary` TEXT DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'last_error'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD COLUMN `last_error` TEXT DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'uq_data_deletion_request_id'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD UNIQUE KEY `uq_data_deletion_request_id` (`request_id`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_data_deletion_status_created'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  "ALTER TABLE `data_deletion_requests`
   ADD INDEX `idx_data_deletion_status_created` (`status`, `created_at`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'data_deletion_requests_ready' AS status;

