SET @has_provider_catalog_services_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services'
);
SET @has_provider_service_offers_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_service_offers'
);
SET @has_service_catalog_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog'
);

SET @has_pcs_category := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'category_id'
);
SET @has_sc_category := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'category_id'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @has_service_catalog_table = 1 AND @has_pcs_category = 1 AND @has_sc_category = 1,
  'UPDATE provider_catalog_services pcs INNER JOIN service_catalog sc ON sc.id = pcs.service_id SET pcs.category_id = sc.category_id WHERE pcs.category_id IS NULL OR pcs.category_id <> sc.category_id',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping category_id backfill'' AS msg',
    IF(@has_service_catalog_table = 0,
      'SELECT ''service_catalog table not found; skipping category_id backfill'' AS msg',
      'SELECT ''category_id backfill skipped'' AS msg'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_pcs_is_active := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'is_active'
);
SET @has_sc_is_active := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'is_active'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @has_service_catalog_table = 1 AND @has_pcs_is_active = 1 AND @has_sc_is_active = 1,
  'UPDATE provider_catalog_services pcs INNER JOIN service_catalog sc ON sc.id = pcs.service_id SET pcs.is_active = sc.is_active WHERE pcs.is_active <> sc.is_active OR pcs.is_active IS NULL',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping is_active backfill'' AS msg',
    IF(@has_service_catalog_table = 0,
      'SELECT ''service_catalog table not found; skipping is_active backfill'' AS msg',
      'SELECT ''is_active backfill skipped'' AS msg'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_pcs_sort_order := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'sort_order'
);
SET @has_sc_sort_order := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'sort_order'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @has_service_catalog_table = 1 AND @has_pcs_sort_order = 1 AND @has_sc_sort_order = 1,
  'UPDATE provider_catalog_services pcs INNER JOIN service_catalog sc ON sc.id = pcs.service_id SET pcs.sort_order = sc.sort_order WHERE pcs.sort_order <> sc.sort_order OR pcs.sort_order IS NULL',
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping sort_order backfill'' AS msg',
    IF(@has_service_catalog_table = 0,
      'SELECT ''service_catalog table not found; skipping sort_order backfill'' AS msg',
      'SELECT ''sort_order backfill skipped'' AS msg'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_pcs_created_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'created_at'
);
SET @sqlstmt := IF(
  @has_provider_catalog_services_table = 1 AND @has_pcs_created_at = 1,
  "UPDATE provider_catalog_services SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'",
  IF(@has_provider_catalog_services_table = 0,
    'SELECT ''provider_catalog_services table not found; skipping created_at backfill'' AS msg',
    'SELECT ''created_at backfill skipped'' AS msg'
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_offer_provider_catalog_service_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_service_offers' AND COLUMN_NAME = 'provider_catalog_service_id'
);
SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'DROP TEMPORARY TABLE IF EXISTS tmp_offer_provider_service_match',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''provider_service_offers table not found; skipping offer backfill staging'' AS msg',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''provider_catalog_services table not found; skipping offer backfill staging'' AS msg',
      'SELECT ''provider_catalog_service_id backfill skipped'' AS msg'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'CREATE TEMPORARY TABLE tmp_offer_provider_service_match AS SELECT o.id AS offer_id, o.provider_id, o.service_id, COUNT(pcs.id) AS candidate_count, MIN(pcs.id) AS resolved_provider_catalog_service_id FROM provider_service_offers o LEFT JOIN provider_catalog_services pcs ON pcs.provider_id = o.provider_id AND pcs.service_id = o.service_id WHERE o.provider_catalog_service_id IS NULL GROUP BY o.id, o.provider_id, o.service_id',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''provider_service_offers table not found; skipping offer match analysis'' AS report_name',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''provider_catalog_services table not found; skipping offer match analysis'' AS report_name',
      'SELECT ''offer match analysis skipped'' AS report_name'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'UPDATE provider_service_offers o INNER JOIN tmp_offer_provider_service_match m ON m.offer_id = o.id AND m.candidate_count = 1 SET o.provider_catalog_service_id = m.resolved_provider_catalog_service_id WHERE o.provider_catalog_service_id IS NULL',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''provider_service_offers table not found; skipping provider_catalog_service_id backfill'' AS msg',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''provider_catalog_services table not found; skipping provider_catalog_service_id backfill'' AS msg',
      'SELECT ''provider_catalog_service_id backfill skipped'' AS msg'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'SELECT ''offers_unique_match_resolved'' AS report_name, m.offer_id AS id, m.provider_id, m.service_id, m.resolved_provider_catalog_service_id AS provider_catalog_service_id FROM tmp_offer_provider_service_match m WHERE m.candidate_count = 1 ORDER BY m.provider_id, m.offer_id',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''offers_unique_match_resolved_report_skipped_missing_provider_service_offers'' AS report_name',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''offers_unique_match_resolved_report_skipped_missing_provider_catalog_services'' AS report_name',
      'SELECT ''offers_unique_match_resolved_report_skipped'' AS report_name'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'SELECT ''offers_without_match'' AS report_name, m.offer_id AS id, m.provider_id, m.service_id FROM tmp_offer_provider_service_match m WHERE m.candidate_count = 0 ORDER BY m.provider_id, m.offer_id',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''offers_without_match_report_skipped_missing_provider_service_offers'' AS report_name',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''offers_without_match_report_skipped_missing_provider_catalog_services'' AS report_name',
      'SELECT ''offers_without_match_report_skipped'' AS report_name'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'SELECT ''offers_ambiguous_match'' AS report_name, m.offer_id AS id, m.provider_id, m.service_id, m.candidate_count FROM tmp_offer_provider_service_match m WHERE m.candidate_count > 1 ORDER BY m.provider_id, m.offer_id',
  IF(@has_provider_service_offers_table = 0,
    'SELECT ''offers_ambiguous_match_report_skipped_missing_provider_service_offers'' AS report_name',
    IF(@has_provider_catalog_services_table = 0,
      'SELECT ''offers_ambiguous_match_report_skipped_missing_provider_catalog_services'' AS report_name',
      'SELECT ''offers_ambiguous_match_report_skipped'' AS report_name'
    )
  )
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sqlstmt := IF(
  @has_provider_service_offers_table = 1 AND @has_provider_catalog_services_table = 1 AND @has_offer_provider_catalog_service_id = 1,
  'DROP TEMPORARY TABLE IF EXISTS tmp_offer_provider_service_match',
  'SELECT ''tmp_offer_provider_service_match cleanup skipped'' AS msg'
);
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;