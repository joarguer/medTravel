CREATE TABLE IF NOT EXISTS `contact_header` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Contact Us',
  `subtitle` TEXT DEFAULT NULL,
  `bg_image` VARCHAR(500) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `contact_header` (`title`, `subtitle`, `bg_image`, `activo`)
SELECT
  'Contact Us',
  'Talk to MedTravel about providers, coordination, and booking support for your medical journey.',
  '',
  0
WHERE NOT EXISTS (
  SELECT 1 FROM `contact_header`
);
