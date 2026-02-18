-- =======================================================
-- MIGRACION: Booking items MVP + soft delete booking_requests
-- Fecha: 2026-02-18
-- Idempotente: si
-- Compatible con MySQL sin ADD COLUMN IF NOT EXISTS
-- =======================================================

SET @db := DATABASE();

-- =======================================================
-- booking_requests: soft delete columns + index
-- =======================================================
SET @t := 'booking_requests';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'is_deleted'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `deleted_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'deleted_by'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `deleted_by` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_booking_requests_is_deleted'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_requests` ADD INDEX `idx_booking_requests_is_deleted` (`is_deleted`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =======================================================
-- booking_request_items: create table
-- =======================================================
SET @t := 'booking_request_items';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  'CREATE TABLE `booking_request_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `booking_request_id` INT NOT NULL,
    `item_type` VARCHAR(32) NOT NULL,
    `offer_id` INT NULL,
    `medtravel_service_id` INT NULL,
    `provider_id` INT NULL,
    `service_provider_id` INT NULL,
    `item_status` VARCHAR(40) NOT NULL DEFAULT ''pending_review'',
    `proposed_price` DECIMAL(12,2) NULL,
    `currency` VARCHAR(5) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_at` DATETIME NULL,
    `deleted_by` INT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_bri_booking_request_id` (`booking_request_id`),
    KEY `idx_bri_provider_id` (`provider_id`),
    KEY `idx_bri_service_provider_id` (`service_provider_id`),
    KEY `idx_bri_is_deleted` (`is_deleted`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =======================================================
-- booking_request_items: drift hardening (add missing columns)
-- =======================================================
SET @t := 'booking_request_items';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'booking_request_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `booking_request_id` INT NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_type'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `item_type` VARCHAR(32) NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'offer_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `offer_id` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'medtravel_service_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `medtravel_service_id` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'provider_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `provider_id` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'service_provider_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `service_provider_id` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_status'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `item_status` VARCHAR(40) NOT NULL DEFAULT ''pending_review''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'proposed_price'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `proposed_price` DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'currency'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `currency` VARCHAR(5) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'notes'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `notes` TEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `updated_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'is_deleted'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `deleted_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'deleted_by'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `deleted_by` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices requeridos
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_booking_request_id'
);
SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_booking_request_id` (`booking_request_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_provider_id'
);
SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_provider_id` (`provider_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_service_provider_id'
);
SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_service_provider_id` (`service_provider_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_is_deleted'
);
SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_is_deleted` (`is_deleted`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'booking_items_mvp_migration_ready' AS status;
