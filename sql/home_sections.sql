-- Tabla para la sección "Cómo Funciona"
CREATE TABLE IF NOT EXISTS `home_como_funciona` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_number` int(11) NOT NULL,
  `icon_class` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `activo` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales para "Cómo Funciona"
INSERT INTO `home_como_funciona` (`step_number`, `icon_class`, `title`, `description`, `activo`) VALUES
(1, 'fa fa-comments', 'Consulta Inicial', 'Nos cuentas tus necesidades médicas y preferencias de viaje', '0'),
(2, 'fa fa-clipboard', 'Coordinación', 'Te conectamos con proveedores certificados y coordinamos citas', '0'),
(3, 'fa fa-plane', 'Logística', 'Organizamos vuelos, alojamiento, transporte y alimentación', '0'),
(4, 'fa fa-handshake', 'Acompañamiento', 'Soporte 24/7 durante tu estadía y seguimiento post-procedimiento', '0');

-- Tabla para la sección "Servicios Detallados"
CREATE TABLE IF NOT EXISTS `home_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `icon_class` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `img` varchar(500) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT '0',
  `badge` varchar(100) DEFAULT NULL,
  `badge_class` varchar(100) DEFAULT NULL,
  `activo` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales para "Servicios Detallados"
INSERT INTO `home_services` (`icon_class`, `title`, `description`, `img`, `orden`, `badge`, `badge_class`, `activo`) VALUES
('fas fa-heartbeat', 'Coordinación Médica', 'Conectamos con proveedores certificados, coordinamos citas y brindamos traducción especializada', 'img/site/placeholder-medical.jpg', 1, NULL, NULL, '0'),
('fas fa-plane-departure', 'Gestión de Vuelos', 'Buscamos las mejores opciones desde USA hacia Colombia adaptadas a tus fechas médicas', 'img/site/placeholder-medical.jpg', 2, NULL, NULL, '0'),
('fas fa-hotel', 'Alojamiento', 'Hoteles cercanos a clínicas, adaptados a tu recuperación y presupuesto', 'img/site/placeholder-medical.jpg', 3, NULL, NULL, '0'),
('fas fa-car', 'Transporte Local', 'Traslados aeropuerto, clínica y hotel con puntualidad y comodidad', 'img/site/placeholder-medical.jpg', 4, NULL, NULL, '0'),
('fas fa-utensils', 'Alimentación', 'Opciones que cumplen restricciones médicas y dietas post-operatorias', 'img/site/placeholder-medical.jpg', 5, NULL, NULL, '0'),
('fas fa-headset', 'Soporte 24/7', 'Asistencia bilingüe permanente y gestión de emergencias', 'img/site/placeholder-medical.jpg', 6, 'Siempre Disponible', 'bg-success', '0');
