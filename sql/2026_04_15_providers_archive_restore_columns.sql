SET @db := DATABASE();
SET @t := 'providers';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'archive_reason'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `providers` ADD COLUMN `archive_reason` TEXT NULL AFTER `deleted_by`',
  'SELECT ''providers.archive_reason already exists or providers table missing'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'restored_at'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `providers` ADD COLUMN `restored_at` DATETIME NULL AFTER `archive_reason`',
  'SELECT ''providers.restored_at already exists or providers table missing'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'restored_by'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `providers` ADD COLUMN `restored_by` INT NULL AFTER `restored_at`',
  'SELECT ''providers.restored_by already exists or providers table missing'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'providers_archive_restore_columns_ready' AS status;