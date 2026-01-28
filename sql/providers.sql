-- SQL: tables for providers, provider_categories, provider_catalog_services
CREATE TABLE IF NOT EXISTS `providers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` ENUM('medico','clinica') NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `city` VARCHAR(120) NULL,
  `address` VARCHAR(200) NULL,
  `phone` VARCHAR(60) NULL,
  `email` VARCHAR(160) NULL,
  `website` VARCHAR(200) NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `provider_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `provider_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_provider_category` (`provider_id`,`category_id`),
  INDEX (`provider_id`),
  INDEX (`category_id`),
  CONSTRAINT `fk_pc_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `provider_catalog_services` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `provider_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_provider_service` (`provider_id`,`service_id`),
  INDEX (`provider_id`),
  INDEX (`service_id`),
  CONSTRAINT `fk_ps_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_service` FOREIGN KEY (`service_id`) REFERENCES `service_catalog`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert demo provider only if providers table empty
INSERT INTO providers (type, name, slug, description, city, is_verified, is_active)
SELECT 'clinica', 'Clínica Demo', 'clinica-demo', 'Prestador demo', 'Ciudad Demo', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM providers);
