SET @has_provider_catalog_services_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services'
);

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'category_id'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `category_id` INT NULL AFTER `service_id`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping category_id'' AS msg',
    'SELECT ''provider_catalog_services.category_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'nivel_atencion'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `nivel_atencion` VARCHAR(32) NULL AFTER `category_id`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping nivel_atencion'' AS msg',
    'SELECT ''provider_catalog_services.nivel_atencion already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'tipo_asistencial'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `tipo_asistencial` VARCHAR(64) NULL AFTER `nivel_atencion`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping tipo_asistencial'' AS msg',
    'SELECT ''provider_catalog_services.tipo_asistencial already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'is_active'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `tipo_asistencial`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping is_active'' AS msg',
    'SELECT ''provider_catalog_services.is_active already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'sort_order'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 1 AFTER `is_active`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping sort_order'' AS msg',
    'SELECT ''provider_catalog_services.sort_order already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'notes'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `notes` TEXT NULL AFTER `sort_order`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping notes'' AS msg',
    'SELECT ''provider_catalog_services.notes already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'created_at'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `notes`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping created_at'' AS msg',
    'SELECT ''provider_catalog_services.created_at already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_column := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'updated_at'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_column = 0,
  'ALTER TABLE `provider_catalog_services` ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping updated_at'' AS msg',
    'SELECT ''provider_catalog_services.updated_at already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_unique_provider_service := (
  SELECT COUNT(*)
  FROM (
    SELECT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_catalog_services'
      AND NON_UNIQUE = 0
    GROUP BY INDEX_NAME
    HAVING COUNT(*) = 2
       AND SUM(CASE WHEN COLUMN_NAME = 'provider_id' THEN 1 ELSE 0 END) = 1
       AND SUM(CASE WHEN COLUMN_NAME = 'service_id' THEN 1 ELSE 0 END) = 1
  ) uq
);
SET @duplicate_provider_service_pairs := (
  SELECT COUNT(*)
  FROM (
    SELECT provider_id, service_id
    FROM provider_catalog_services
    GROUP BY provider_id, service_id
    HAVING COUNT(*) > 1
  ) dup
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @has_unique_provider_service = 0 AND @duplicate_provider_service_pairs = 0,
  'ALTER TABLE `provider_catalog_services` ADD UNIQUE KEY `u_provider_service` (`provider_id`, `service_id`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping unique(provider_id, service_id)'' AS msg',
    'SELECT ''provider_catalog_services unique(provider_id, service_id) already present or skipped due to duplicates'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND INDEX_NAME = 'idx_pcs_provider_active_sort'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_catalog_services` ADD INDEX `idx_pcs_provider_active_sort` (`provider_id`, `is_active`, `sort_order`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping idx_pcs_provider_active_sort'' AS msg',
    'SELECT ''idx_pcs_provider_active_sort already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND INDEX_NAME = 'idx_pcs_service_active'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_catalog_services` ADD INDEX `idx_pcs_service_active` (`service_id`, `is_active`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping idx_pcs_service_active'' AS msg',
    'SELECT ''idx_pcs_service_active already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND INDEX_NAME = 'idx_pcs_category_id'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_catalog_services` ADD INDEX `idx_pcs_category_id` (`category_id`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping idx_pcs_category_id'' AS msg',
    'SELECT ''idx_pcs_category_id already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND INDEX_NAME = 'idx_pcs_nivel_atencion'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_catalog_services` ADD INDEX `idx_pcs_nivel_atencion` (`nivel_atencion`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping idx_pcs_nivel_atencion'' AS msg',
    'SELECT ''idx_pcs_nivel_atencion already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND INDEX_NAME = 'idx_pcs_tipo_asistencial'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @exists_index = 0,
  'ALTER TABLE `provider_catalog_services` ADD INDEX `idx_pcs_tipo_asistencial` (`tipo_asistencial`)',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping idx_pcs_tipo_asistencial'' AS msg',
    'SELECT ''idx_pcs_tipo_asistencial already exists'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;