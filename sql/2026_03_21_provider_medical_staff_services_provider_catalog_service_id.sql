-- =============================================================
-- MIGRATION: provider_medical_staff_services canonical service ref
-- Date      : 2026-03-21
-- Idempotent: yes
-- Scope     : additive only, safe for legacy schemas
-- =============================================================

SET @db := DATABASE();
SET @t := 'provider_medical_staff_services';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'provider_catalog_service_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff_services` ADD COLUMN `provider_catalog_service_id` INT NULL AFTER `service_id`',
  IF(@table_exists = 0,
    'SELECT ''provider_medical_staff_services table not found; skipping provider_catalog_service_id column'' AS msg',
    'SELECT ''provider_catalog_service_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pmss_provider_catalog_service_id'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff_services` ADD INDEX `idx_pmss_provider_catalog_service_id` (`provider_catalog_service_id`)',
  IF(@table_exists = 0,
    'SELECT ''provider_medical_staff_services table not found; skipping idx_pmss_provider_catalog_service_id'' AS msg',
    'SELECT ''idx_pmss_provider_catalog_service_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pmss_staff_provider_catalog_service'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff_services` ADD INDEX `idx_pmss_staff_provider_catalog_service` (`provider_medical_staff_id`, `provider_catalog_service_id`)',
  IF(@table_exists = 0,
    'SELECT ''provider_medical_staff_services table not found; skipping idx_pmss_staff_provider_catalog_service'' AS msg',
    'SELECT ''idx_pmss_staff_provider_catalog_service already exists'' AS msg'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_medical_staff_services_provider_catalog_service_id_ready' AS status;
