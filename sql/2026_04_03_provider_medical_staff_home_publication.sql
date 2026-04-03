-- =============================================================
-- MIGRATION: provider medical staff home publication consent
-- Date      : 2026-04-03
-- Idempotent: yes
-- Scope     : explicit public-home publication consent for staff
-- Notes     : only staff with explicit authorization should appear
--             in the public "Our Specialists" section on index.php.
-- =============================================================

SET @db := DATABASE();
SET @t := 'provider_medical_staff';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'allow_home_publication'
);

SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `allow_home_publication` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_home_publication'
);

SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_home_publication` (`allow_home_publication`, `provider_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
