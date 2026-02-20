-- =======================================================
-- MIGRACION: client_documents request/item scoping
-- Fecha: 2026-02-20
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();

-- booking_request_id column
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_documents' AND COLUMN_NAME = 'booking_request_id'
);
SET @sql := IF(
  @col_exists = 0,
  "ALTER TABLE `client_documents` ADD COLUMN `booking_request_id` INT(11) NULL AFTER `client_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- item_id column
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_documents' AND COLUMN_NAME = 'item_id'
);
SET @sql := IF(
  @col_exists = 0,
  "ALTER TABLE `client_documents` ADD COLUMN `item_id` INT(11) NULL AFTER `booking_request_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- index booking_request_id
SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_documents' AND INDEX_NAME = 'idx_client_documents_request'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `client_documents` ADD INDEX `idx_client_documents_request` (`booking_request_id`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- index item_id
SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_documents' AND INDEX_NAME = 'idx_client_documents_item'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `client_documents` ADD INDEX `idx_client_documents_item` (`item_id`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'client_documents_scope_ready' AS status;
