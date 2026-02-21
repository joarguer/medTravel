-- =======================================================
-- MIGRACION: testimonials
-- Fecha: 2026-02-21
-- Idempotente: si
-- =======================================================

SET @db := DATABASE();
SET @t := 'testimonials';

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @t
);

SET @sql := IF(
  @table_exists = 0,
  "CREATE TABLE `testimonials` (\n"
  "  `id` INT NOT NULL AUTO_INCREMENT,\n"
  "  `client_user_id` INT NOT NULL,\n"
  "  `client_name` VARCHAR(120) NOT NULL,\n"
  "  `client_location` VARCHAR(120) DEFAULT NULL,\n"
  "  `rating` TINYINT NOT NULL DEFAULT 5,\n"
  "  `comment` TEXT NOT NULL,\n"
  "  `avatar_path` VARCHAR(255) DEFAULT NULL,\n"
  "  `status` ENUM('pending','approved','rejected','archived') NOT NULL DEFAULT 'pending',\n"
  "  `created_at` DATETIME NOT NULL,\n"
  "  `updated_at` DATETIME DEFAULT NULL,\n"
  "  `approved_at` DATETIME DEFAULT NULL,\n"
  "  `approved_by` INT DEFAULT NULL,\n"
  "  PRIMARY KEY (`id`),\n"
  "  KEY `idx_testimonials_status` (`status`),\n"
  "  KEY `idx_testimonials_client_user` (`client_user_id`),\n"
  "  KEY `idx_testimonials_approved_at` (`approved_at`)\n"
  ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'testimonials_ready' AS status;
