-- MIGRACION: calendar_events appointment_mode (virtual / in_person / travel)
-- Objetivo: persistir modalidad explícita de cita sin romper eventos legacy.

SET @db := DATABASE();
SET @t := 'calendar_events';

SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @has_mode := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t AND COLUMN_NAME = 'appointment_mode'
);

SET @sql := IF(
  @has_table = 0,
  'SELECT "calendar_events_missing" AS status',
  IF(
    @has_mode = 0,
    "ALTER TABLE `calendar_events` ADD COLUMN `appointment_mode` VARCHAR(20) NULL DEFAULT NULL AFTER `status`",
    'SELECT "appointment_mode_already_exists" AS status'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE calendar_events
SET appointment_mode = CASE
  WHEN COALESCE(NULLIF(appointment_mode, ''), '') IN ('virtual', 'in_person', 'travel') THEN appointment_mode
  WHEN COALESCE(NULLIF(google_meet_url, ''), '') <> '' THEN 'virtual'
  WHEN LOWER(COALESCE(integration_mode, '')) = 'calendar_plus_meet' THEN 'virtual'
  WHEN LOWER(CONCAT(COALESCE(title, ''), ' ', COALESCE(description, ''))) REGEXP '(travel|trip|viaje|traslado|flight|vuelo|hotel|airport|aeropuerto)' THEN 'travel'
  ELSE 'in_person'
END
WHERE appointment_mode IS NULL OR appointment_mode = '' OR appointment_mode NOT IN ('virtual', 'in_person', 'travel');

SELECT 'calendar_events_appointment_mode_ready' AS status;
