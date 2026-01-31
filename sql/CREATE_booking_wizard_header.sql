-- Tabla para gestionar el header del wizard de booking
CREATE TABLE IF NOT EXISTS `booking_wizard_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT 'Booking Wizard',
  `subtitle_1` varchar(255) DEFAULT 'Home',
  `subtitle_2` varchar(255) DEFAULT 'Booking Request',
  `bg_image` varchar(500) DEFAULT 'img/carousel-1.jpg',
  `activo` enum('0','1') DEFAULT '0' COMMENT '0=activo, 1=inactivo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar registro por defecto
INSERT INTO `booking_wizard_header` (`id`, `title`, `subtitle_1`, `subtitle_2`, `bg_image`, `activo`) 
VALUES (1, 'Booking Wizard', 'Home', 'Booking Request', 'img/carousel-1.jpg', '0')
ON DUPLICATE KEY UPDATE id=id;
