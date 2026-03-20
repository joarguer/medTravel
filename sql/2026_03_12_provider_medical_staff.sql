-- =============================================================
-- MIGRATION: provider medical staff (MVP canonico + compatibilidad legacy)
-- Date      : 2026-03-20
-- Idempotent: yes
-- Scope     : internal admin management for providers.id
-- Notes     : crea la estructura canonica del MVP y mantiene columnas legacy
--             ya usadas por runtime (licencia, sede, acceso admin, notas).
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
    `id` INT NOT NULL AUTO_INCREMENT,
    `provider_id` INT NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `role_title` VARCHAR(120) NULL,
    `specialty` VARCHAR(120) NULL,
    `bio_short` TEXT NULL,
    `photo` VARCHAR(255) NULL,
    `phone` VARCHAR(60) NULL,
    `email` VARCHAR(120) NULL,
    `is_primary_doctor` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `professional_license` VARCHAR(120) NULL,
    `clinic_name` VARCHAR(180) NULL,
    `notes` TEXT NULL,
    `linked_user_id` INT NULL COMMENT 'Logical FK -> usuarios.id',
    `can_access_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

-- =============================================================
-- Additive canonical columns for existing tables
-- =============================================================
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'role_title'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `role_title` VARCHAR(120) NULL AFTER `full_name`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'bio_short'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `bio_short` TEXT NULL AFTER `specialty`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'photo'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `photo` VARCHAR(255) NULL AFTER `bio_short`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'is_primary_doctor'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `is_primary_doctor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_primary_doctor`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_active`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================
-- Legacy compatibility columns expected by current runtime
-- =============================================================
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'professional_license'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `professional_license` VARCHAR(120) NULL AFTER `sort_order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'clinic_name'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `clinic_name` VARCHAR(180) NULL AFTER `professional_license`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'notes'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `notes` TEXT NULL AFTER `clinic_name`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'linked_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD COLUMN `linked_user_id` INT NULL COMMENT ''Logical FK -> usuarios.id'' AFTER `notes`',
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

-- =============================================================
-- Backfills / compatibility sync
-- =============================================================
SET @has_active := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'active'
);
SET @has_is_active := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(
  @table_exists = 1 AND @has_active = 1 AND @has_is_active = 1,
  'UPDATE `provider_medical_staff` SET `is_active` = COALESCE(`active`, 1)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_notes := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'notes'
);
SET @has_bio := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'bio_short'
);
SET @sql := IF(
  @table_exists = 1 AND @has_notes = 1 AND @has_bio = 1,
  "UPDATE `provider_medical_staff`
     SET `bio_short` = `notes`
   WHERE (TRIM(COALESCE(`bio_short`, '')) = '')
     AND TRIM(COALESCE(`notes`, '')) <> ''",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sort := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @table_exists = 1 AND @has_sort = 1,
  'UPDATE `provider_medical_staff` SET `sort_order` = `id` WHERE `sort_order` IS NULL OR `sort_order` = 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================
-- Indexes
-- =============================================================
SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_provider_id'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_provider_id` (`provider_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_provider_is_active'
);
SET @sql := IF(
  @idx_exists = 0 AND @has_is_active = 1,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_provider_is_active` (`provider_id`, `is_active`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_provider_sort_order'
);
SET @sql := IF(
  @idx_exists = 0 AND @has_sort = 1,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_provider_sort_order` (`provider_id`, `sort_order`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_pms_linked_user'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE `provider_medical_staff` ADD INDEX `idx_pms_linked_user` (`linked_user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================
-- Foreign key to providers.id (safe only when no orphan rows exist)
-- =============================================================
SET @providers_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'providers'
);
SET @fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db
    AND CONSTRAINT_NAME = 'fk_provider_medical_staff_provider'
);
SET @orphan_count := 0;
SET @sql := IF(
  @providers_exists = 1,
  'SELECT COUNT(*) INTO @orphan_count
     FROM `provider_medical_staff` pms
     LEFT JOIN `providers` p ON p.id = pms.provider_id
    WHERE pms.provider_id IS NOT NULL
      AND p.id IS NULL',
  'SET @orphan_count := 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @table_exists = 1 AND @providers_exists = 1 AND @fk_exists = 0 AND @orphan_count = 0,
  'ALTER TABLE `provider_medical_staff`
     ADD CONSTRAINT `fk_provider_medical_staff_provider`
     FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`)
     ON DELETE CASCADE
     ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'provider_medical_staff_ready' AS status;
