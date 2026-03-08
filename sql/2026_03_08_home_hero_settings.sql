CREATE TABLE IF NOT EXISTS `home_hero_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `media_type` ENUM('carousel','video') NOT NULL DEFAULT 'carousel',
  `video_url` VARCHAR(500) DEFAULT NULL,
  `video_poster` VARCHAR(500) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `cta_text` VARCHAR(100) DEFAULT NULL,
  `cta_url` VARCHAR(500) DEFAULT NULL,
  `detailed_services_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_home_hero_settings_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'is_enabled';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `id`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'media_type';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `media_type` ENUM(''carousel'',''video'') NOT NULL DEFAULT ''carousel'' AFTER `is_enabled`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'video_url';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL AFTER `media_type`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'video_poster';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `video_poster` VARCHAR(500) DEFAULT NULL AFTER `video_url`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'title';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `video_poster`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'subtitle';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `subtitle` VARCHAR(255) DEFAULT NULL AFTER `title`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'cta_text';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `cta_text` VARCHAR(100) DEFAULT NULL AFTER `subtitle`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'cta_url';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `cta_url` VARCHAR(500) DEFAULT NULL AFTER `cta_text`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'detailed_services_enabled';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `detailed_services_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `cta_url`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'updated_at';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `detailed_services_enabled`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'updated_by';
SET @s = IF(@c = 0, 'ALTER TABLE `home_hero_settings` ADD COLUMN `updated_by` INT DEFAULT NULL AFTER `updated_at`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'home_hero_settings'
    AND INDEX_NAME = 'idx_home_hero_settings_updated_by'
);
SET @s = IF(@idx_exists = 0, 'ALTER TABLE `home_hero_settings` ADD INDEX `idx_home_hero_settings_updated_by` (`updated_by`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `home_hero_settings` (
  `is_enabled`,
  `media_type`,
  `video_url`,
  `video_poster`,
  `title`,
  `subtitle`,
  `cta_text`,
  `cta_url`,
  `detailed_services_enabled`,
  `updated_at`,
  `updated_by`
)
SELECT
  1,
  'carousel',
  '',
  '',
  '',
  '',
  '',
  '',
  1,
  NOW(),
  NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `home_hero_settings`
);
