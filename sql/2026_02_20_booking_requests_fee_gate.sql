-- =======================================================
-- MIGRACION: Fee Gate en booking_requests
-- Fecha: 2026-02-20
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
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'fee_status'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`
   ADD COLUMN `fee_status` ENUM('not_required','pending','paid') NOT NULL DEFAULT 'pending'",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'fee_required'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `booking_requests`
   ADD COLUMN `fee_required` TINYINT(1) NOT NULL DEFAULT 0",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'booking_requests_fee_gate_ready' AS status;
