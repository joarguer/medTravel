-- =======================================================
-- MIGRACION: vínculo booking_requests -> usuarios por client_user_id
-- Fecha: 2026-02-19
-- Idempotente: si
-- Compatible con MySQL sin ADD COLUMN IF NOT EXISTS
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
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'client_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `client_user_id` INT(11) NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = @t
    AND INDEX_NAME = 'idx_booking_requests_client_user_id'
);
SET @sql := IF(
  @table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `booking_requests` ADD INDEX `idx_booking_requests_client_user_id` (`client_user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill por email -> usuarios.id (primera coincidencia por email normalizado)
SET @can_backfill := (
  SELECT CASE
    WHEN EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'booking_requests' AND COLUMN_NAME = 'client_user_id'
    )
    AND EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'booking_requests' AND COLUMN_NAME = 'email'
    )
    AND EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios'
    )
    AND EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id'
    )
    AND EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'email'
    )
    THEN 1 ELSE 0 END
);

SET @sql := IF(
  @can_backfill = 1,
  "UPDATE booking_requests br
   INNER JOIN (
     SELECT MIN(id) AS id, LOWER(TRIM(email)) AS email_norm
     FROM usuarios
     WHERE email IS NOT NULL AND TRIM(email) <> ''
     GROUP BY LOWER(TRIM(email))
   ) u ON u.email_norm = LOWER(TRIM(br.email))
   SET br.client_user_id = u.id
   WHERE br.client_user_id IS NULL
     AND br.email IS NOT NULL
     AND TRIM(br.email) <> ''",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'booking_requests_client_user_link_ready' AS status;
