-- Redes institucionales del prestador medico (providers)
-- MySQL 5.7 compatible, idempotente

SET @dbname = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'providers'
        AND COLUMN_NAME = 'instagram_url'
    ),
    'SELECT 1',
    'ALTER TABLE `providers` ADD COLUMN `instagram_url` VARCHAR(255) NULL AFTER `website`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'providers'
        AND COLUMN_NAME = 'facebook_url'
    ),
    'SELECT 1',
    'ALTER TABLE `providers` ADD COLUMN `facebook_url` VARCHAR(255) NULL AFTER `instagram_url`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'providers'
        AND COLUMN_NAME = 'linkedin_url'
    ),
    'SELECT 1',
    'ALTER TABLE `providers` ADD COLUMN `linkedin_url` VARCHAR(255) NULL AFTER `facebook_url`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'providers'
        AND COLUMN_NAME = 'youtube_url'
    ),
    'SELECT 1',
    'ALTER TABLE `providers` ADD COLUMN `youtube_url` VARCHAR(255) NULL AFTER `linkedin_url`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'providers'
        AND COLUMN_NAME = 'whatsapp_url'
    ),
    'SELECT 1',
    'ALTER TABLE `providers` ADD COLUMN `whatsapp_url` VARCHAR(255) NULL AFTER `youtube_url`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
