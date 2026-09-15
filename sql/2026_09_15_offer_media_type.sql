-- =======================================================
-- MIGRACION: Soporte IMAGE/VIDEO en offer_media
-- Fecha: 2026-09-15
-- Idempotente: si
-- Compatible con MySQL sin ADD COLUMN IF NOT EXISTS
-- Filas existentes quedan como media_type = 'IMAGE' via DEFAULT
-- =======================================================

SET @db := DATABASE();
SET @t := 'offer_media';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'media_type'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `offer_media` ADD COLUMN `media_type` ENUM('IMAGE','VIDEO') NOT NULL DEFAULT 'IMAGE' AFTER `path`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'mime_type'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `offer_media` ADD COLUMN `mime_type` VARCHAR(100) NULL AFTER `media_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'poster_path'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `offer_media` ADD COLUMN `poster_path` VARCHAR(255) NULL AFTER `mime_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_offer_media_offer_media_type'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `offer_media` ADD INDEX `idx_offer_media_offer_media_type` (`offer_id`, `media_type`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
