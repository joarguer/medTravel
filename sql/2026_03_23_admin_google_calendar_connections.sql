-- =======================================================
-- MIGRACION: conexiones Google Calendar / Meet por admin
-- Fecha: 2026-03-23
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'admin_google_calendar_connections';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `admin_google_calendar_connections` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_user_id` INT(11) NOT NULL,
    `google_email` VARCHAR(190) NULL,
    `google_subject` VARCHAR(190) NULL,
    `scope_text` TEXT NULL,
    `token_type` VARCHAR(32) NULL,
    `token_expires_at` DATETIME NULL,
    `access_token_encrypted` TEXT NULL,
    `refresh_token_encrypted` TEXT NULL,
    `connected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `disconnected_at` DATETIME NULL,
    `last_error` TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_admin_google_calendar_connections_admin` (`admin_user_id`),
    KEY `idx_admin_google_calendar_connections_google_email` (`google_email`),
    KEY `idx_admin_google_calendar_connections_subject` (`google_subject`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'uniq_admin_google_calendar_connections_admin'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `admin_google_calendar_connections` ADD UNIQUE KEY `uniq_admin_google_calendar_connections_admin` (`admin_user_id`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_admin_google_calendar_connections_google_email'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `admin_google_calendar_connections` ADD KEY `idx_admin_google_calendar_connections_google_email` (`google_email`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND INDEX_NAME = 'idx_admin_google_calendar_connections_subject'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `admin_google_calendar_connections` ADD KEY `idx_admin_google_calendar_connections_subject` (`google_subject`)",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'admin_google_calendar_connections_ready' AS status;