-- Table to store booking wizard submissions
CREATE TABLE IF NOT EXISTS `booking_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `origin` VARCHAR(150) DEFAULT NULL,
  `booking_datetime` VARCHAR(130) DEFAULT NULL,
  `destination` VARCHAR(255) DEFAULT NULL,
  `persons` VARCHAR(80) DEFAULT NULL,
  `category` VARCHAR(120) DEFAULT NULL,
  `special_request` TEXT,
  `service_categories` TEXT,
  `medical_services` TEXT,
  `budget` DECIMAL(12,2) DEFAULT NULL,
  `timeline` VARCHAR(255) DEFAULT NULL,
  `additional_notes` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
