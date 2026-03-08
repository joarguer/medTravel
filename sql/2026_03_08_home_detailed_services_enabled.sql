SELECT COUNT(*) INTO @c
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'home_hero_settings'
  AND COLUMN_NAME = 'detailed_services_enabled';

SET @s = IF(
  @c = 0,
  'ALTER TABLE `home_hero_settings` ADD COLUMN `detailed_services_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `cta_url`',
  'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `home_hero_settings`
SET `detailed_services_enabled` = 1
WHERE `detailed_services_enabled` IS NULL;
