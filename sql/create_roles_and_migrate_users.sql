-- SQL: create_roles_and_migrate_users.sql
-- Creates a `roles` table and migrates the `usuarios` table to reference `roles.id`.
-- Run on your MySQL/MariaDB server. Back up your DB before running.

START TRANSACTION;

-- 1) Create `roles` table
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Seed default roles matching application constants
-- NOTE: ids chosen to match current code references
-- Use upsert to avoid duplicate-key errors if the script is re-run
INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
  (1,  'principal',    'Principal / Admin',  'Full admin, equivalent to ROLE_ADMIN'),
  (2,  'administrative','Administrative',     'Administrative user (role level 2)'),
  (3,  'client',       'Cliente',            'End customer / client'),
  (4,  'provider',     'Proveedor',          'Service provider account'),
  (12, 'provider_admin','Admin Prestador',   'Admin de prestador (puede crear usuarios de su prestador)'),
  (11, 'accounting',   'Contable',           'Accounting / finance')
ON DUPLICATE KEY UPDATE
  `slug` = VALUES(`slug`),
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);

-- 3) Add `role_id` to `usuarios` if not present. Keep old `rol` column as fallback (non-destructive).
-- MySQL does not support `ADD COLUMN IF NOT EXISTS` in simple ALTER statements.
-- Use INFORMATION_SCHEMA checks and prepared statements so the script can be run safely.

-- Add column if missing
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'role_id'
);

SELECT @has_col;

SET @sql = IF(@has_col = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `role_id` INT NULL AFTER `rol`',
  'SELECT "role_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on role_id if missing
SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND (INDEX_NAME = 'role_id' OR COLUMN_NAME = 'role_id')
);

SET @sql = IF(@has_idx = 0,
  'ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_role_id` (`role_id`)',
  'SELECT "index exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Try to populate `role_id` from existing `rol` values when possible
-- This attempts numeric mapping first; then basic string mapping.
UPDATE `usuarios` SET `role_id` = CASE
  WHEN `rol` REGEXP '^[0-9]+$' THEN CAST(`rol` AS UNSIGNED)
  WHEN LOWER(`rol`) LIKE '%admin%' THEN 1
  WHEN LOWER(`rol`) LIKE '%proveedor%' OR LOWER(`rol`) LIKE '%provider%' THEN 4
  WHEN LOWER(`rol`) LIKE '%cliente%' OR LOWER(`rol`) LIKE '%client%' THEN 3
  WHEN LOWER(`rol`) LIKE '%contable%' OR LOWER(`rol`) LIKE '%account%' THEN 11
  ELSE `role_id`
END
WHERE `role_id` IS NULL OR `role_id` = 0;

-- 5) Optionally add foreign key (commented by default; enable if you want strict FK)
-- ALTER TABLE `usuarios`
--   ADD CONSTRAINT `fk_usuarios_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 6) Permissions tables to allow granular access control per role
-- Creates `permissions` catalog and `role_permissions` junction. Safe to re-run.

-- Handle missing `type` column by choosing a safe placement
SET @has_kind := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'kind'
);
SET @has_type := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'type'
);
SET @sql := IF(@has_kind = 0,
  CONCAT(
    'ALTER TABLE `providers` ADD COLUMN `kind` ENUM("medical","partner") NOT NULL DEFAULT "medical" ',
    IF(@has_type > 0, 'AFTER `type`', 'AFTER `is_active`')
  ),
  'SELECT "kind exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Permissions catalog
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role ↔ Permission mapping
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_role_permissions_role` (`role_id`),
  KEY `idx_role_permissions_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed baseline permissions (extend as needed)
INSERT INTO `permissions` (`id`, `slug`, `name`, `description`) VALUES
  (1, 'users.view', 'Ver usuarios', 'Puede listar y ver usuarios'),
  (2, 'users.create', 'Crear usuarios', 'Puede crear usuarios'),
  (3, 'users.edit', 'Editar usuarios', 'Puede editar usuarios'),
  (4, 'roles.manage', 'Gestionar roles', 'Puede crear/editar roles y permisos'),
  (5, 'providers.view', 'Ver prestadores', 'Puede ver prestadores (cualquier tipo)'),
  (6, 'providers.edit', 'Editar prestadores', 'Puede editar prestadores (cualquier tipo)'),
  (9, 'providers.medical.view', 'Ver prestadores médicos', 'Puede ver prestadores médicos'),
  (10,'providers.medical.edit', 'Editar prestadores médicos', 'Puede editar prestadores médicos'),
  (11,'providers.partner.view', 'Ver partners', 'Puede ver proveedores complementarios'),
  (12,'providers.partner.edit', 'Editar partners', 'Puede editar proveedores complementarios'),
  (7, 'reports.view', 'Ver reportes', 'Puede ver reportes'),
  (8, 'offers.manage', 'Gestionar ofertas', 'Puede crear/editar ofertas')
ON DUPLICATE KEY UPDATE
  `slug` = VALUES(`slug`),
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);

-- Seed default role-permission links (adjust freely)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
  (1, 1),(1, 2),(1, 3),(1, 4),(1, 5),(1, 6),(1, 7),(1, 8), -- Admin: todo
  (1, 9),(1,10),(1,11),(1,12),
  (2, 1),(2, 2),(2, 3),(2, 5),(2, 6),(2, 8),(2,9),(2,10),  -- Administrativo: medical
  (11,7),                                                  -- Contable: reportes
  (12,1),(12,2),(12,3),(12,5),(12,6),(12,8),(12,9),(12,10),(12,11),(12,12), -- Admin prestador: todo en su contexto
  (4, 1),(4, 3),(4, 5),(4, 6),(4, 8),(4,9),(4,10);         -- Proveedor: sin crear usuarios, medical

COMMIT;

-- Usage notes:
-- - The app currently checks `$_SESSION['ppal']` and `$_SESSION['rol']`; after migrating you may want to
--   update login code to populate `$_SESSION['role_id']` and `$_SESSION['rol']` consistently (numeric).
-- - To fully adopt the `roles` table, update your user creation/updating backend (`admin/ajax/crear_usuario.php`) to
--   save the numeric `role_id` into `usuarios.role_id` and optionally into `usuarios.rol` (for backward compatibility).
-- - If you prefer different role ids, update the INSERT seeds accordingly and adapt the PHP constants in
--   `admin/include/roles.php`.

-- End of file
