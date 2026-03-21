-- =======================================================
-- Canonical provider ownership mapping hardening
-- Fecha     : 2026-03-21
-- Objetivo  : Asegurar que provider_users soporte ownership/admin
--             explícito del provider médico sin romper instalaciones
--             existentes.
-- =======================================================

SET @db := DATABASE();

SET @provider_users_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provider_users'
);

SET @sql := IF(
  @provider_users_exists = 0,
  'CREATE TABLE `provider_users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `provider_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `role_in_provider` VARCHAR(30) NOT NULL DEFAULT ''owner'',
      UNIQUE KEY `uq_provider_user` (`provider_id`,`user_id`),
      KEY `idx_user_id` (`user_id`),
      CONSTRAINT `fk_provider_users_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_provider_users_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT ''provider_users already exists'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_role_in_provider := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provider_users'
    AND COLUMN_NAME = 'role_in_provider'
);

SET @sql := IF(
  @has_role_in_provider = 0,
  'ALTER TABLE `provider_users` ADD COLUMN `role_in_provider` VARCHAR(30) NOT NULL DEFAULT ''owner'' AFTER `user_id`',
  'SELECT ''provider_users.role_in_provider already exists'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `provider_users`
   SET `role_in_provider` = 'owner'
 WHERE `role_in_provider` IS NULL
    OR TRIM(`role_in_provider`) = '';

SET @has_idx_user := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provider_users'
    AND INDEX_NAME = 'idx_user_id'
);

SET @sql := IF(
  @has_idx_user = 0,
  'ALTER TABLE `provider_users` ADD INDEX `idx_user_id` (`user_id`)',
  'SELECT ''provider_users.idx_user_id already exists'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_uq_provider_user := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provider_users'
    AND INDEX_NAME = 'uq_provider_user'
);

SET @provider_user_duplicates := (
  SELECT COUNT(*)
  FROM (
    SELECT provider_id, user_id
    FROM provider_users
    GROUP BY provider_id, user_id
    HAVING COUNT(*) > 1
  ) dup
);

SET @sql := IF(
  @has_uq_provider_user = 0 AND @provider_user_duplicates = 0,
  'ALTER TABLE `provider_users` ADD UNIQUE KEY `uq_provider_user` (`provider_id`,`user_id`)',
  IF(
    @has_uq_provider_user > 0,
    'SELECT ''provider_users.uq_provider_user already exists'' AS msg',
    'SELECT ''provider_users has duplicates; unique(provider_id,user_id) not added'' AS msg'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
