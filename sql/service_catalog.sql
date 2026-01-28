-- SQL: create table service_catalog and example inserts (if categories exist and table empty)
CREATE TABLE IF NOT EXISTS `service_catalog` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `short_description` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_category` (`category_id`),
  CONSTRAINT `fk_service_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert examples only if table empty and categories exist
-- Odontología
INSERT INTO service_catalog (category_id, name, slug, short_description, sort_order, is_active)
SELECT sc.id, 'Limpieza dental', 'limpieza-dental', 'Servicio de limpieza dental profesional', 1, 1
FROM (SELECT id FROM service_categories WHERE slug = 'odontologia' LIMIT 1) AS sc
WHERE NOT EXISTS (SELECT 1 FROM service_catalog);

-- Dermatología
INSERT INTO service_catalog (category_id, name, slug, short_description, sort_order, is_active)
SELECT sc.id, 'Consulta dermatológica', 'consulta-dermatologica', 'Evaluación y tratamiento de piel', 2, 1
FROM (SELECT id FROM service_categories WHERE slug = 'dermatologia' LIMIT 1) AS sc
WHERE NOT EXISTS (SELECT 1 FROM service_catalog);
