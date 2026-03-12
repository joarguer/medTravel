-- =============================================================
-- MIGRATION: provider staff access + item assignment base
-- Date      : 2026-03-12
-- Idempotent: yes
-- Notes     : additive only, safe for legacy schemas
-- =============================================================

SET @db := DATABASE();

-- =============================================================
-- provider_medical_staff: access/linking fields for admin login
-- =============================================================
SET @t := 'provider_medical_staff';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'linked_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `linked_user_id` INT NULL COMMENT ''Logical FK -> usuarios.id'' AFTER `clinic_name`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'can_access_admin'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `can_access_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `linked_user_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_linked_user'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_linked_user` (`linked_user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================
-- booking_request_items: future assignment to provider medical staff
-- =============================================================
SET @t := 'booking_request_items';
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'assigned_staff_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `assigned_staff_id` INT NULL AFTER `service_provider_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'assigned_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `assigned_at` DATETIME NULL AFTER `assigned_staff_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'assigned_by_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_request_items` ADD COLUMN `assigned_by_user_id` INT NULL AFTER `assigned_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_assigned_staff_id'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_assigned_staff_id` (`assigned_staff_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_bri_provider_staff'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_request_items` ADD INDEX `idx_bri_provider_staff` (`provider_id`, `assigned_staff_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_staff_access_and_assignment_ready' AS status;
