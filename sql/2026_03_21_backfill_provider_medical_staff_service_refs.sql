-- =============================================================
-- MIGRATION: backfill provider_medical_staff_services.provider_catalog_service_id
-- Date      : 2026-03-21
-- Idempotent: yes
-- Scope     : additive only, writes only unique matches
-- =============================================================

SET @db := DATABASE();

SET @has_pmss_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff_services'
);
SET @has_pms_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff'
);
SET @has_pcs_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_catalog_services'
);

SET @has_pmss_staff_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff_services' AND COLUMN_NAME = 'provider_medical_staff_id'
);
SET @has_pmss_service_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff_services' AND COLUMN_NAME = 'service_id'
);
SET @has_pmss_provider_catalog_service_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff_services' AND COLUMN_NAME = 'provider_catalog_service_id'
);
SET @has_pms_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff' AND COLUMN_NAME = 'id'
);
SET @has_pms_provider_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_medical_staff' AND COLUMN_NAME = 'provider_id'
);
SET @has_pcs_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'id'
);
SET @has_pcs_provider_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'provider_id'
);
SET @has_pcs_service_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provider_catalog_services' AND COLUMN_NAME = 'service_id'
);

SET @can_backfill := (
  @has_pmss_table = 1
  AND @has_pms_table = 1
  AND @has_pcs_table = 1
  AND @has_pmss_staff_id = 1
  AND @has_pmss_service_id = 1
  AND @has_pmss_provider_catalog_service_id = 1
  AND @has_pms_id = 1
  AND @has_pms_provider_id = 1
  AND @has_pcs_id = 1
  AND @has_pcs_provider_id = 1
  AND @has_pcs_service_id = 1
);

SET @sql := IF(
  @can_backfill = 1,
  'DROP TEMPORARY TABLE IF EXISTS tmp_staff_provider_service_match',
  'SELECT ''staff provider_catalog_service_id backfill staging skipped'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'CREATE TEMPORARY TABLE tmp_staff_provider_service_match AS
   SELECT
       rel.provider_medical_staff_id,
       pms.provider_id,
       rel.service_id,
       COUNT(pcs.id) AS candidate_count,
       MIN(pcs.id) AS resolved_provider_catalog_service_id
   FROM provider_medical_staff_services rel
   INNER JOIN provider_medical_staff pms
       ON pms.id = rel.provider_medical_staff_id
   LEFT JOIN provider_catalog_services pcs
       ON pcs.provider_id = pms.provider_id
      AND pcs.service_id = rel.service_id
   WHERE rel.provider_catalog_service_id IS NULL
   GROUP BY rel.provider_medical_staff_id, pms.provider_id, rel.service_id',
  IF(@has_pmss_table = 0,
    'SELECT ''provider_medical_staff_services table not found; skipping staff match analysis'' AS report_name',
    IF(@has_pms_table = 0,
      'SELECT ''provider_medical_staff table not found; skipping staff match analysis'' AS report_name',
      IF(@has_pcs_table = 0,
        'SELECT ''provider_catalog_services table not found; skipping staff match analysis'' AS report_name',
        'SELECT ''staff match analysis skipped'' AS report_name'
      )
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'UPDATE provider_medical_staff_services rel
   INNER JOIN provider_medical_staff pms
       ON pms.id = rel.provider_medical_staff_id
   INNER JOIN tmp_staff_provider_service_match m
       ON m.provider_medical_staff_id = rel.provider_medical_staff_id
      AND m.provider_id = pms.provider_id
      AND m.service_id = rel.service_id
      AND m.candidate_count = 1
   SET rel.provider_catalog_service_id = m.resolved_provider_catalog_service_id
   WHERE rel.provider_catalog_service_id IS NULL',
  IF(@has_pmss_table = 0,
    'SELECT ''provider_medical_staff_services table not found; skipping staff provider_catalog_service_id backfill'' AS msg',
    IF(@has_pms_table = 0,
      'SELECT ''provider_medical_staff table not found; skipping staff provider_catalog_service_id backfill'' AS msg',
      IF(@has_pcs_table = 0,
        'SELECT ''provider_catalog_services table not found; skipping staff provider_catalog_service_id backfill'' AS msg',
        'SELECT ''staff provider_catalog_service_id backfill skipped'' AS msg'
      )
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'SELECT
       ''staff_service_unique_match_resolved'' AS report_name,
       m.provider_medical_staff_id,
       m.provider_id,
       m.service_id,
       m.resolved_provider_catalog_service_id AS provider_catalog_service_id
   FROM tmp_staff_provider_service_match m
   WHERE m.candidate_count = 1
   ORDER BY m.provider_id, m.provider_medical_staff_id, m.service_id',
  IF(@has_pmss_table = 0,
    'SELECT ''staff_service_unique_match_resolved_report_skipped_missing_provider_medical_staff_services'' AS report_name',
    IF(@has_pms_table = 0,
      'SELECT ''staff_service_unique_match_resolved_report_skipped_missing_provider_medical_staff'' AS report_name',
      IF(@has_pcs_table = 0,
        'SELECT ''staff_service_unique_match_resolved_report_skipped_missing_provider_catalog_services'' AS report_name',
        'SELECT ''staff_service_unique_match_resolved_report_skipped'' AS report_name'
      )
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'SELECT
       ''staff_service_without_match'' AS report_name,
       m.provider_medical_staff_id,
       m.provider_id,
       m.service_id
   FROM tmp_staff_provider_service_match m
   WHERE m.candidate_count = 0
   ORDER BY m.provider_id, m.provider_medical_staff_id, m.service_id',
  IF(@has_pmss_table = 0,
    'SELECT ''staff_service_without_match_report_skipped_missing_provider_medical_staff_services'' AS report_name',
    IF(@has_pms_table = 0,
      'SELECT ''staff_service_without_match_report_skipped_missing_provider_medical_staff'' AS report_name',
      IF(@has_pcs_table = 0,
        'SELECT ''staff_service_without_match_report_skipped_missing_provider_catalog_services'' AS report_name',
        'SELECT ''staff_service_without_match_report_skipped'' AS report_name'
      )
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'SELECT
       ''staff_service_ambiguous_match'' AS report_name,
       m.provider_medical_staff_id,
       m.provider_id,
       m.service_id,
       m.candidate_count
   FROM tmp_staff_provider_service_match m
   WHERE m.candidate_count > 1
   ORDER BY m.provider_id, m.provider_medical_staff_id, m.service_id',
  IF(@has_pmss_table = 0,
    'SELECT ''staff_service_ambiguous_match_report_skipped_missing_provider_medical_staff_services'' AS report_name',
    IF(@has_pms_table = 0,
      'SELECT ''staff_service_ambiguous_match_report_skipped_missing_provider_medical_staff'' AS report_name',
      IF(@has_pcs_table = 0,
        'SELECT ''staff_service_ambiguous_match_report_skipped_missing_provider_catalog_services'' AS report_name',
        'SELECT ''staff_service_ambiguous_match_report_skipped'' AS report_name'
      )
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @can_backfill = 1,
  'DROP TEMPORARY TABLE IF EXISTS tmp_staff_provider_service_match',
  'SELECT ''tmp_staff_provider_service_match cleanup skipped'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
