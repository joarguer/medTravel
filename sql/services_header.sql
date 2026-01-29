-- Tabla para configurar el header de la página de servicios/ofertas
CREATE TABLE IF NOT EXISTS services_header (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL DEFAULT 'Our Medical Services',
  subtitle_1 VARCHAR(255) DEFAULT 'MEDICAL SERVICES',
  subtitle_2 VARCHAR(255) DEFAULT 'Discover quality medical services from verified providers',
  bg_image VARCHAR(255) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar configuración por defecto
INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) VALUES
('Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', 0);
