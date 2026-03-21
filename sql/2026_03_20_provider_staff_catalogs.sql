-- =============================================================
-- MIGRATION: provider_staff_roles + provider_staff_specialties
-- Date      : 2026-03-20
-- Idempotent: yes
-- Scope     : provider_medical_staff — catálogos de roles y especialidades por proveedor
-- Notes     : Crea dos tablas de catálogo con datos base del sistema (provider_id NULL)
--             ampliables por proveedor (provider_id NOT NULL).
--             Los campos role_title / specialty de provider_medical_staff
--             siguen siendo VARCHAR libres para compatibilidad legacy;
--             el valor guardado es el .name del catálogo (sin FK todavía).
-- =============================================================

SET @db := DATABASE();

-- ═══════════════════════════════════════════════════════════════
-- TABLA: provider_staff_roles
-- ═══════════════════════════════════════════════════════════════
SET @t := 'provider_staff_roles';

SET @table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
    @table_exists = 0,
    "CREATE TABLE `provider_staff_roles` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `provider_id` INT          NULL DEFAULT NULL COMMENT 'NULL = entrada base del sistema disponible para todos',
      `name`        VARCHAR(120) NOT NULL               COMMENT 'Nombre del cargo en inglés, visible al paciente',
      `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
      `sort_order`  INT          NOT NULL DEFAULT 0,
      `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY  `uq_role_provider_name` (`provider_id`, `name`),
      INDEX `idx_role_provider_active`    (`provider_id`, `is_active`),
      INDEX `idx_role_sort`               (`provider_id`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ═══════════════════════════════════════════════════════════════
-- TABLA: provider_staff_specialties
-- ═══════════════════════════════════════════════════════════════
SET @t2 := 'provider_staff_specialties';

SET @table2_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t2
);

SET @sql := IF(
    @table2_exists = 0,
    "CREATE TABLE `provider_staff_specialties` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `provider_id` INT          NULL DEFAULT NULL COMMENT 'NULL = entrada base del sistema disponible para todos',
      `name`        VARCHAR(120) NOT NULL               COMMENT 'Nombre de la especialidad en inglés, visible al paciente',
      `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
      `sort_order`  INT          NOT NULL DEFAULT 0,
      `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY  `uq_specialty_provider_name` (`provider_id`, `name`),
      INDEX `idx_specialty_provider_active`    (`provider_id`, `is_active`),
      INDEX `idx_specialty_sort`               (`provider_id`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ═══════════════════════════════════════════════════════════════
-- SEED: datos base del sistema (provider_id = NULL)
-- Solo inserta si la tabla estaba vacía de entradas base
-- ═══════════════════════════════════════════════════════════════

-- Roles base
INSERT IGNORE INTO `provider_staff_roles` (`provider_id`, `name`, `sort_order`) VALUES
  (NULL, 'Lead Doctor',               1),
  (NULL, 'Specialist',                2),
  (NULL, 'Surgeon',                   3),
  (NULL, 'Dentist',                   4),
  (NULL, 'Orthodontist',              5),
  (NULL, 'Oral Surgeon',              6),
  (NULL, 'Cosmetic Dentist',          7),
  (NULL, 'General Physician',         8),
  (NULL, 'Nurse',                     9),
  (NULL, 'Patient Coordinator',      10),
  (NULL, 'Medical Assistant',        11),
  (NULL, 'Anesthesiologist',         12),
  (NULL, 'Therapist',                13),
  (NULL, 'Administrative Coordinator', 14);

-- Especialidades base
INSERT IGNORE INTO `provider_staff_specialties` (`provider_id`, `name`, `sort_order`) VALUES
  (NULL, 'Dentistry',          1),
  (NULL, 'Cosmetic Dentistry', 2),
  (NULL, 'Orthodontics',       3),
  (NULL, 'Oral Surgery',       4),
  (NULL, 'Plastic Surgery',    5),
  (NULL, 'Bariatric Surgery',  6),
  (NULL, 'Dermatology',        7),
  (NULL, 'Ophthalmology',      8),
  (NULL, 'Fertility',          9),
  (NULL, 'Orthopedics',       10),
  (NULL, 'General Medicine',  11),
  (NULL, 'Aesthetic Medicine', 12),
  (NULL, 'Rehabilitation',    13),
  (NULL, 'Nutrition',         14);


-- ═══════════════════════════════════════════════════════════════
-- MIGRACIÓN LEGACY: agregar valores únicos ya usados en role_title / specialty
-- Solo si la tabla existía y tenía registros (idempotente vía INSERT IGNORE)
-- ═══════════════════════════════════════════════════════════════

-- Roles presentes en staff que no están en el catálogo base (los agrega como entrada de sistema)
INSERT IGNORE INTO `provider_staff_roles` (`provider_id`, `name`, `sort_order`)
SELECT DISTINCT NULL, TRIM(pms.role_title), 99
FROM provider_medical_staff pms
WHERE TRIM(IFNULL(pms.role_title, '')) != ''
  AND NOT EXISTS (
    SELECT 1 FROM provider_staff_roles r
    WHERE r.provider_id IS NULL AND r.name = TRIM(pms.role_title)
  );

-- Especialidades presentes en staff que no están en el catálogo base
INSERT IGNORE INTO `provider_staff_specialties` (`provider_id`, `name`, `sort_order`)
SELECT DISTINCT NULL, TRIM(pms.specialty), 99
FROM provider_medical_staff pms
WHERE TRIM(IFNULL(pms.specialty, '')) != ''
  AND NOT EXISTS (
    SELECT 1 FROM provider_staff_specialties s
    WHERE s.provider_id IS NULL AND s.name = TRIM(pms.specialty)
  );


SELECT 'provider_staff_roles_ready'       AS status_roles;
SELECT 'provider_staff_specialties_ready' AS status_specialties;
