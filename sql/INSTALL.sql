-- =======================================================
-- SCRIPT DE INSTALACIÓN COMPLETA - MEDTRAVEL
-- Ejecutar este archivo en MySQL para configurar todo
-- =======================================================

-- 1. Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS bolsacar_medtravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bolsacar_medtravel;

-- =======================================================
-- 2. TABLA: services_header (NUEVA - para configurar UI)
-- =======================================================
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
INSERT IGNORE INTO services_header (id, title, subtitle_1, subtitle_2, activo) VALUES
(1, 'Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', 0);

-- =======================================================
-- 3. TABLA: service_categories (Categorías de servicios)
-- =======================================================
CREATE TABLE IF NOT EXISTS service_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  icon VARCHAR(100) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 4. TABLA: service_catalog (Catálogo de servicios)
-- =======================================================
CREATE TABLE IF NOT EXISTS service_catalog (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  icon VARCHAR(100) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_category_id (category_id),
  CONSTRAINT fk_service_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 5. TABLA: providers (Empresas/Clínicas/Médicos)
-- =======================================================
CREATE TABLE IF NOT EXISTS providers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 6. TABLA: provider_service_offers (Ofertas de servicios)
-- =======================================================
CREATE TABLE IF NOT EXISTS provider_service_offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  service_id INT NOT NULL,
  title VARCHAR(200) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  price_from DECIMAL(12,2) DEFAULT NULL,
  currency VARCHAR(5) NOT NULL DEFAULT 'USD',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_provider_id (provider_id),
  KEY idx_service_id (service_id),
  CONSTRAINT fk_offers_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_service FOREIGN KEY (service_id) REFERENCES service_catalog (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 7. TABLA: offer_media (Imágenes de las ofertas)
-- =======================================================
CREATE TABLE IF NOT EXISTS offer_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  offer_id INT NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_offer_id (offer_id),
  CONSTRAINT fk_media_offer FOREIGN KEY (offer_id) REFERENCES provider_service_offers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 8. ACTUALIZAR TABLA: usuarios (agregar provider_id)
-- =======================================================
-- Verificar si la columna provider_id existe
SET @dbname = DATABASE();
SET @tablename = 'usuarios';
SET @columnname = 'provider_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT NULL AFTER rol, ADD CONSTRAINT fk_usuario_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar si la columna rol existe
SET @columnname = 'rol';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(20) NOT NULL DEFAULT \'prestador\' AFTER password')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =======================================================
-- 9. DATOS DE EJEMPLO (Opcional - comentar si no necesitas)
-- =======================================================

-- Insertar categorías de ejemplo
INSERT IGNORE INTO service_categories (id, name, description, icon, is_active) VALUES
(1, 'Dentistry', 'Dental treatments and procedures', 'fa-tooth', 1),
(2, 'Plastic Surgery', 'Cosmetic and reconstructive surgery', 'fa-user-md', 1),
(3, 'Cardiology', 'Heart and cardiovascular treatments', 'fa-heartbeat', 1),
(4, 'Orthopedics', 'Bone and joint treatments', 'fa-bone', 1);

-- Insertar servicios de ejemplo
INSERT IGNORE INTO service_catalog (id, category_id, name, description, icon, is_active) VALUES
(1, 1, 'Dental Implants', 'High-quality dental implant procedures', 'fa-tooth', 1),
(2, 1, 'Teeth Whitening', 'Professional teeth whitening services', 'fa-smile', 1),
(3, 2, 'Rhinoplasty', 'Nose reshaping surgery', 'fa-head-side-mask', 1),
(4, 2, 'Liposuction', 'Body contouring procedures', 'fa-user', 1);

-- Insertar proveedor de ejemplo
INSERT IGNORE INTO providers (id, name, city, phone, email, is_active) VALUES
(1, 'MedCenter Clinic', 'Bogotá', '+57 1 234 5678', 'info@medcenter.com', 1);

-- Insertar oferta de ejemplo
INSERT IGNORE INTO provider_service_offers (id, provider_id, service_id, title, description, price_from, currency, is_active) VALUES
(1, 1, 1, 'Premium Dental Implants Package', 'Complete dental implant procedure with post-operative care and follow-up consultations.', 1500.00, 'USD', 1);

-- =======================================================
-- VERIFICACIÓN FINAL
-- =======================================================
SELECT 
    'Instalación completada exitosamente' AS status,
    (SELECT COUNT(*) FROM services_header) AS services_header_rows,
    (SELECT COUNT(*) FROM service_categories) AS categories_rows,
    (SELECT COUNT(*) FROM service_catalog) AS services_rows,
    (SELECT COUNT(*) FROM providers) AS providers_rows,
    (SELECT COUNT(*) FROM provider_service_offers) AS offers_rows;
