-- MIGRACION: calendar_events modo de integracion opcional para Inbox ITEM MVP

SET @db := DATABASE();
SET @t := 'calendar_events';

SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @has_integration_mode := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'integration_mode'
);

SET @sql := IF(
  @has_table = 0,
  'SELECT "calendar_events_missing" AS status',
  IF(
    @has_integration_mode = 0,
    "ALTER TABLE `calendar_events` ADD COLUMN `integration_mode` VARCHAR(32) NULL DEFAULT 'calendar_plus_meet' AFTER `organizer_admin_user_id`",
    'SELECT "integration_mode_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE calendar_events
SET integration_mode = CASE
  WHEN COALESCE(NULLIF(google_meet_url, ''), '') <> '' THEN 'calendar_plus_meet'
  WHEN COALESCE(NULLIF(google_event_id, ''), '') <> '' THEN 'calendar_only'
  ELSE 'calendar_plus_meet'
END
WHERE integration_mode IS NULL OR integration_mode = '';

SELECT 'calendar_events_integration_mode_ready' AS status;