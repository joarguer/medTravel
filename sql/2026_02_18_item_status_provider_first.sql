-- =======================================================
-- MIGRACION: Item status provider-first (booking_request_items)
-- Fecha: 2026-02-18
-- Idempotente: si
-- Compatible con MySQL sin IF NOT EXISTS en ALTER
-- =======================================================

SET @db := DATABASE();
SET @t := 'booking_request_items';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

-- Crear columna item_status si no existe
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_status'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `item_status` VARCHAR(32) NOT NULL DEFAULT ''pending_provider''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Asegurar tipo/default canónico
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_status'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 1,
  'ALTER TABLE `booking_request_items` MODIFY COLUMN `item_status` VARCHAR(32) NOT NULL DEFAULT ''pending_provider''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrar estados legacy / nulos a pending_provider
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'item_status'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 1,
  'UPDATE `booking_request_items`
     SET `item_status` = ''pending_provider''
   WHERE `item_status` IS NULL
      OR TRIM(`item_status`) = ''''
      OR `item_status` IN (''pending_admin'', ''pending_review'')',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice por estado
SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = @t
    AND INDEX_NAME = 'idx_booking_request_items_status'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_booking_request_items_status` (`item_status`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'item_status_provider_first_ready' AS status;
