-- =======================================================
-- MIGRATION: Ownership complementario en usuarios
-- Objetivo: agregar usuarios.service_provider_id (scope complementario)
-- Idempotente: SI
-- =======================================================

SET @dbname = DATABASE();

-- 1) Agregar columna service_provider_id (nullable)
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'service_provider_id'
);

SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `service_provider_id` INT NULL AFTER `provider_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Crear índice para la columna
SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = 'usuarios'
    AND INDEX_NAME = 'idx_usuarios_service_provider_id'
);

SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_service_provider_id` (`service_provider_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) FK opcional (solo si existe service_providers y no hay FK creada)
SET @sp_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = 'service_providers'
);

SET @has_fk := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'service_provider_id'
    AND REFERENCED_TABLE_NAME = 'service_providers'
);

SET @sql := IF(
  @sp_exists > 0 AND @has_fk = 0,
  'ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_service_provider` FOREIGN KEY (`service_provider_id`) REFERENCES `service_providers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Verificación rápida
SELECT
  u.id,
  u.usuario,
  u.provider_id AS medical_provider_id,
  u.service_provider_id AS complementary_provider_id
FROM usuarios u
ORDER BY u.id DESC
LIMIT 20;
