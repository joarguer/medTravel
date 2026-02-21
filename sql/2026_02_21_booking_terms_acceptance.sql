-- =======================================================
-- MIGRACION: Terms acceptance audit fields
-- Fecha: 2026-02-21
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'booking_requests';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'terms_accepted'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`\n   ADD COLUMN `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'terms_accepted_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`\n   ADD COLUMN `terms_accepted_at` DATETIME DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'terms_version'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`\n   ADD COLUMN `terms_version` VARCHAR(20) DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'terms_ip'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`\n   ADD COLUMN `terms_ip` VARCHAR(45) DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'terms_user_agent'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`\n   ADD COLUMN `terms_user_agent` VARCHAR(255) DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'booking_requests_terms_ready' AS status;
