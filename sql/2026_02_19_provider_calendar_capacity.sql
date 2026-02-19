-- =======================================================
-- MIGRACION: calendar_capacity para proveedores de calendario
-- Fecha: 2026-02-19
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();

-- -------------------------------------------------------
-- providers.calendar_capacity
-- -------------------------------------------------------
SET @t := 'providers';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'calendar_capacity'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `providers` ADD COLUMN `calendar_capacity` INT NOT NULL DEFAULT 1 AFTER `is_active`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- service_providers.calendar_capacity
-- -------------------------------------------------------
SET @t := 'service_providers';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'calendar_capacity'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `service_providers` ADD COLUMN `calendar_capacity` INT NOT NULL DEFAULT 1 AFTER `is_active`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_calendar_capacity_ready' AS status;
