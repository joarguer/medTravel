-- =======================================================
-- MIGRACION: password_reset_sent_at para throttling de reenvío
-- Fecha: 2026-02-18
-- Idempotente: si
-- Compatible con MySQL sin ADD COLUMN IF NOT EXISTS
-- =======================================================

SET @db := DATABASE();
SET @t := 'usuarios';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'password_reset_sent_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `password_reset_sent_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_usuarios_password_reset_sent_at'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_password_reset_sent_at` (`password_reset_sent_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'password_reset_sent_at_ready' AS status;
