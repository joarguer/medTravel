-- =============================================================
-- MIGRATION: provider medical staff services
-- Date      : 2026-03-12
-- Idempotent: yes
-- Notes     : staff clinical capability bound to global service_catalog
-- =============================================================

SET @db := DATABASE();
SET @t := 'provider_medical_staff_services';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `provider_medical_staff_services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_medical_staff_id` INT UNSIGNED NOT NULL COMMENT 'Logical FK -> provider_medical_staff.id',
    `service_id` INT NOT NULL COMMENT 'Logical FK -> service_catalog.id',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'uq_pms_service'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `provider_medical_staff_services` ADD UNIQUE INDEX `uq_pms_service` (`provider_medical_staff_id`, `service_id`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pmss_staff_active'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `provider_medical_staff_services` ADD INDEX `idx_pmss_staff_active` (`provider_medical_staff_id`, `active`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pmss_service'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `provider_medical_staff_services` ADD INDEX `idx_pmss_service` (`service_id`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_medical_staff_services_ready' AS status;
