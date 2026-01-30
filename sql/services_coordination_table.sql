-- Tabla para gestionar los servicios de coordinación de MedTravel en services.php
CREATE TABLE IF NOT EXISTS `coordination_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `icon_class` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `position` enum('left','right') NOT NULL DEFAULT 'left',
  `orden` int(11) NOT NULL DEFAULT '0',
  `activo` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpiar registros existentes antes de insertar
TRUNCATE TABLE `coordination_services`;

-- Insertar los 6 servicios actuales
INSERT INTO `coordination_services` (`icon_class`, `title`, `description`, `position`, `orden`, `activo`) VALUES
('fa fa-heartbeat', 'Medical Coordination', 'We connect you with certified medical providers in Colombia. We coordinate your appointments, provide specialized translation during consultations, personalized support and post-procedure follow-up to ensure your peace of mind and successful recovery.', 'left', 1, '1'),
('fa fa-plane', 'Flight Management', 'We search and coordinate the best flight options from the United States to Colombia. We adapt itineraries to your medical dates, budget and preferences, ensuring comfortable connections and schedules that facilitate your recovery.', 'left', 2, '1'),
('fa fa-hotel', 'Accommodation', 'We book hotels and lodging options adapted to your budget and recovery needs. We select locations close to clinics, with quiet environments and services that promote your comfort during treatment.', 'left', 3, '1'),
('fa fa-car', 'Local Transportation', 'We organize all your transfers: from the airport to your hotel, medical clinics, points of interest and return. We guarantee punctuality, comfort and safety throughout your stay in Colombia.', 'right', 4, '1'),
('fa fa-cutlery', 'Meals', 'We coordinate meal options that meet post-operative medical restrictions and special diets. We select restaurants and food services that guarantee adequate nutrition during your recovery.', 'right', 5, '1'),
('fa fa-headphones', '24/7 Support', 'Bilingual assistance available 24 hours a day, 7 days a week. Immediate emergency management, resolution of unforeseen events and constant support throughout your medical tourism experience in Colombia.', 'right', 6, '1');

-- Tabla para el header de services.php
CREATE TABLE IF NOT EXISTS `services_page_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT 'Our Services',
  `subtitle` varchar(255) NOT NULL DEFAULT 'Comprehensive Services',
  `main_title` varchar(255) NOT NULL DEFAULT 'Complete Coordination & Management',
  `description` text,
  `header_image` varchar(255) DEFAULT 'img/carousel-1.jpg',
  `activo` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpiar registros existentes antes de insertar
TRUNCATE TABLE `services_page_header`;

-- Insertar configuración por defecto
INSERT INTO `services_page_header` (`title`, `subtitle`, `main_title`, `description`, `header_image`, `activo`) VALUES
('Our Services', 'Comprehensive Services', 'Complete Coordination & Management', 'At MedTravel we connect patients from the United States with certified medical providers in Colombia, offering complete coordination service from planning to post-procedure follow-up.', 'img/carousel-1.jpg', '1');
