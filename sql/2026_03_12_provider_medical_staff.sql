-- =============================================================
-- MIGRATION: provider medical staff (MVP staff medico interno)
-- Date      : 2026-03-12
-- Idempotent: yes
-- Scope     : internal admin management for providers.id
-- Notes     : logical relation only; no hard FK required for legacy compatibility
-- =============================================================

SET @db := DATABASE();
SET @t := 'provider_medical_staff';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `provider_medical_staff` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT NOT NULL COMMENT 'Logical FK -> providers.id',
    `full_name` VARCHAR(180) NOT NULL,
    `specialty` VARCHAR(180) NOT NULL DEFAULT '',
    `professional_license` VARCHAR(120) NOT NULL DEFAULT '',
    `email` VARCHAR(190) NOT NULL DEFAULT '',
    `phone` VARCHAR(80) NOT NULL DEFAULT '',
    `clinic_name` VARCHAR(180) NOT NULL DEFAULT '',
    `notes` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_provider_active'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_provider_active` (`provider_id`, `active`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_provider_name'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_provider_name` (`provider_id`, `full_name`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_medical_staff_ready' AS status;
