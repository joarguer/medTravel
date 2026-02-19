-- =======================================================
-- MIGRACION: Inbox persistente (mensajes + lecturas)
-- Fecha: 2026-02-19
-- Idempotente: si
-- Compatible con MySQL sin CREATE TABLE IF NOT EXISTS en todos los entornos
-- =======================================================

SET @db := DATABASE();

-- -------------------------------------------------------
-- Tabla: inbox_messages
-- -------------------------------------------------------
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_messages'
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `inbox_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `thread_id` VARCHAR(64) NOT NULL,
    `thread_type` ENUM('CARE','ITEM') NOT NULL,
    `request_id` INT(11) NULL,
    `item_id` INT(11) NULL,
    `sender_role` ENUM('CLIENT','PROVIDER','ADMIN','PATIENTCARE') NOT NULL,
    `sender_user_id` INT(11) NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_messages' AND INDEX_NAME = 'idx_inbox_messages_thread_created'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `inbox_messages` ADD INDEX `idx_inbox_messages_thread_created` (`thread_id`, `created_at`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_messages' AND INDEX_NAME = 'idx_inbox_messages_request'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `inbox_messages` ADD INDEX `idx_inbox_messages_request` (`request_id`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_messages' AND INDEX_NAME = 'idx_inbox_messages_item'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `inbox_messages` ADD INDEX `idx_inbox_messages_item` (`item_id`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- Tabla: inbox_thread_reads
-- -------------------------------------------------------
SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_thread_reads'
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `inbox_thread_reads` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `thread_id` VARCHAR(64) NOT NULL,
    `reader_role` ENUM('CLIENT','PROVIDER','ADMIN','PATIENTCARE') NOT NULL,
    `reader_user_id` INT(11) NOT NULL,
    `last_read_message_id` INT(11) NULL,
    `last_read_at` DATETIME NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'inbox_thread_reads' AND INDEX_NAME = 'uniq_thread_reader'
);
SET @sql := IF(
  @idx_exists = 0,
  "ALTER TABLE `inbox_thread_reads` ADD UNIQUE KEY `uniq_thread_reader` (`thread_id`, `reader_role`, `reader_user_id`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'inbox_messages_reads_ready' AS status;
