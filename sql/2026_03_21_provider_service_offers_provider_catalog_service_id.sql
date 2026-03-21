SET @has_provider_service_offers_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_service_offers'
);

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_service_offers' AND COLUMN_NAME = 'provider_catalog_service_id'
);
SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_service_offers` ADD COLUMN `provider_catalog_service_id` INT NULL AFTER `provider_id`',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''provider_service_offers table not found; skipping provider_catalog_service_id'' AS msg',
    'SELECT ''provider_service_offers.provider_catalog_service_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_service_offers' AND INDEX_NAME = 'idx_offer_provider_catalog_service_id'
);
SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_service_offers` ADD INDEX `idx_offer_provider_catalog_service_id` (`provider_catalog_service_id`)',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''provider_service_offers table not found; skipping idx_offer_provider_catalog_service_id'' AS msg',
    'SELECT ''idx_offer_provider_catalog_service_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;