CREATE TABLE IF NOT EXISTS `blog_header` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Our Blog',
  `subtitle` TEXT DEFAULT NULL,
  `bg_image` VARCHAR(500) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blog_header` (`title`, `subtitle`, `bg_image`, `activo`)
SELECT
  'Our Blog',
  'Discover experiences and updates from our medical travel community.',
  '',
  0
WHERE NOT EXISTS (
  SELECT 1 FROM `blog_header`
);
