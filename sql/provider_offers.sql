-- provider_offers.sql
-- Tablas para mapping usuario->prestador y ofertas de servicios

CREATE TABLE IF NOT EXISTS `provider_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_in_provider` VARCHAR(30) NOT NULL DEFAULT 'owner',
  UNIQUE KEY `uq_provider_user` (`provider_id`,`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_provider_users_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_provider_users_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `provider_service_offers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price_from` DECIMAL(12,2) DEFAULT NULL,
  `currency` VARCHAR(5) NOT NULL DEFAULT 'USD',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_service_id` (`service_id`),
  CONSTRAINT `fk_offers_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offers_service` FOREIGN KEY (`service_id`) REFERENCES `service_catalog` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `offer_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `offer_id` INT NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_offer_id` (`offer_id`),
  CONSTRAINT `fk_media_offer` FOREIGN KEY (`offer_id`) REFERENCES `provider_service_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert demo offer si existen provider id=1 y service id=1
SET @has_provider := (SELECT COUNT(*) FROM providers WHERE id = 1);
SET @has_service := (SELECT COUNT(*) FROM service_catalog WHERE id = 1);

INSERT INTO provider_service_offers (provider_id, service_id, title, description, price_from, currency, is_active)
SELECT 1, 1, 'Oferta demo', 'Oferta de ejemplo para pruebas', 100.00, 'USD', 1
WHERE @has_provider > 0 AND @has_service > 0
LIMIT 1;
