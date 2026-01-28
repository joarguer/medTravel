-- SQL: crea tabla service_categories y datos de ejemplo
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(180) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert ejemplo si la tabla está vacía
INSERT INTO service_categories (name, slug, description, sort_order, is_active)
SELECT 'Odontología', 'odontologia', 'Servicios de odontología', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE slug = 'odontologia');

INSERT INTO service_categories (name, slug, description, sort_order, is_active)
SELECT 'Dermatología', 'dermatologia', 'Servicios de dermatología', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE slug = 'dermatologia');
