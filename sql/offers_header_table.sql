-- Tabla para gestionar el header de offers.php (Medical Services)
CREATE TABLE IF NOT EXISTS `services_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT 'Our Medical Services',
  `subtitle_1` varchar(255) NOT NULL DEFAULT 'MEDICAL SERVICES',
  `subtitle_2` text NOT NULL,
  `bg_image` varchar(255) DEFAULT 'img/carousel-1.jpg',
  `activo` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpiar registros existentes
TRUNCATE TABLE `services_header`;

-- Insertar configuración por defecto
INSERT INTO `services_header` (`title`, `subtitle_1`, `subtitle_2`, `bg_image`, `activo`) VALUES
('Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', 'img/carousel-1.jpg', '0');
