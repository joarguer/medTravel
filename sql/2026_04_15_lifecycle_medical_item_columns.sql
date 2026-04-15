-- Migración: ciclo de vida médico del item (2026-04-15)
-- Idempotente. Patrón: guard por INFORMATION_SCHEMA antes de cada ALTER.
-- Tabla: booking_request_items

SET @db := DATABASE();
SET @t := 'booking_request_items';

-- assessment_done_at: cuando el prestador marca valoración virtual realizada
SET @col := 'assessment_done_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `assessment_done_at` DATETIME NULL AFTER `treatment_completed_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- assessment_done_by_user_id
SET @col := 'assessment_done_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `assessment_done_by_user_id` INT NULL AFTER `assessment_done_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- assessment_notes: notas breves de la valoración
SET @col := 'assessment_notes';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `assessment_notes` TEXT NULL AFTER `assessment_done_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- plan_agreed_at: cuando se registra plan acordado post-valoración
SET @col := 'plan_agreed_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `plan_agreed_at` DATETIME NULL AFTER `assessment_notes`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- plan_agreed_by_user_id
SET @col := 'plan_agreed_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `plan_agreed_by_user_id` INT NULL AFTER `plan_agreed_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- plan_description: descripción del plan clínico acordado
SET @col := 'plan_description';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `plan_description` TEXT NULL AFTER `plan_agreed_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- procedure_scheduled_at: fecha/hora acordada para procedimiento presencial en Colombia
SET @col := 'procedure_scheduled_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `procedure_scheduled_at` DATETIME NULL AFTER `plan_description`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- procedure_scheduled_by_user_id
SET @col := 'procedure_scheduled_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `procedure_scheduled_by_user_id` INT NULL AFTER `procedure_scheduled_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- case_closed_at
SET @col := 'case_closed_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `case_closed_at` DATETIME NULL AFTER `procedure_scheduled_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- case_closed_by_user_id
SET @col := 'case_closed_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `case_closed_by_user_id` INT NULL AFTER `case_closed_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- case_close_reason
SET @col := 'case_close_reason';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `case_close_reason` TEXT NULL AFTER `case_closed_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reversal_reason: motivo cuando un estado retrocede (genérico)
SET @col := 'reversal_reason';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `reversal_reason` TEXT NULL AFTER `case_close_reason`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reversal_by_user_id
SET @col := 'reversal_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `reversal_by_user_id` INT NULL AFTER `reversal_reason`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reversal_at
SET @col := 'reversal_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `reversal_at` DATETIME NULL AFTER `reversal_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────────────────────────
-- Tabla: booking_requests
-- Columna para actualización formal de ventana de fechas post-valoración
-- ──────────────────────────────────────────────────────────────────────────────

SET @t := 'booking_requests';

-- timeline_updated_at: cuando se actualiza formalmente la ventana de fechas
SET @col := 'timeline_updated_at';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `timeline_updated_at` DATETIME NULL AFTER `timeline_to`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- timeline_updated_by_user_id
SET @col := 'timeline_updated_by_user_id';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `timeline_updated_by_user_id` INT NULL AFTER `timeline_updated_at`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- timeline_update_reason
SET @col := 'timeline_update_reason';
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@t AND COLUMN_NAME=@col) = 0,
  CONCAT('ALTER TABLE `', @t, '` ADD COLUMN `timeline_update_reason` TEXT NULL AFTER `timeline_updated_by_user_id`'),
  CONCAT('SELECT ''', @t, '.', @col, ' ya existe'' AS msg')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
