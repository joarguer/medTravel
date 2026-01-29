-- =======================================================
-- SCRIPT DE INSTALACIÓN LOCAL - MEDTRAVEL
-- Para ejecutar en ambiente de desarrollo local
-- =======================================================

-- 1. Crear base de datos
DROP DATABASE IF EXISTS bolsacar_medtravel;
CREATE DATABASE bolsacar_medtravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bolsacar_medtravel;

-- =======================================================
-- 2. TABLA: services_header (para configurar UI)
-- =======================================================
CREATE TABLE services_header (
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
INSERT INTO services_header (id, title, subtitle_1, subtitle_2, activo) VALUES
(1, 'Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', 0);

-- =======================================================
-- 3. TABLA: service_categories (Categorías de servicios)
-- =======================================================
CREATE TABLE service_categories (
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
CREATE TABLE service_catalog (
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
CREATE TABLE providers (
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
-- 6. TABLA: usuarios (actualizada con rol y provider_id)
-- =======================================================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  nombre VARCHAR(150) DEFAULT NULL,
  rol VARCHAR(20) NOT NULL DEFAULT 'prestador',
  provider_id INT DEFAULT NULL,
  CONSTRAINT fk_usuario_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- 7. TABLA: provider_service_offers (Ofertas de servicios)
-- =======================================================
CREATE TABLE provider_service_offers (
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
-- 8. TABLA: offer_media (Imágenes de las ofertas)
-- =======================================================
CREATE TABLE offer_media (
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
-- 9. DATOS DE EJEMPLO
-- =======================================================

-- Usuario admin principal
INSERT INTO usuarios (id, usuario, password, nombre, rol, provider_id) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin', NULL);
-- Contraseña: password

-- Categorías de servicios
INSERT INTO service_categories (id, name, description, icon, is_active) VALUES
(1, 'Dentistry', 'Dental treatments and procedures', 'fa-tooth', 1),
(2, 'Plastic Surgery', 'Cosmetic and reconstructive surgery', 'fa-user-md', 1),
(3, 'Cardiology', 'Heart and cardiovascular treatments', 'fa-heartbeat', 1),
(4, 'Orthopedics', 'Bone and joint treatments', 'fa-bone', 1),
(5, 'Dermatology', 'Skin care and treatments', 'fa-hand-sparkles', 1);

-- Servicios
INSERT INTO service_catalog (id, category_id, name, description, icon, is_active) VALUES
(1, 1, 'Dental Implants', 'High-quality dental implant procedures', 'fa-tooth', 1),
(2, 1, 'Teeth Whitening', 'Professional teeth whitening services', 'fa-smile', 1),
(3, 1, 'Orthodontics', 'Braces and teeth alignment', 'fa-teeth', 1),
(4, 2, 'Rhinoplasty', 'Nose reshaping surgery', 'fa-head-side-mask', 1),
(5, 2, 'Liposuction', 'Body contouring procedures', 'fa-user', 1),
(6, 2, 'Breast Augmentation', 'Cosmetic breast enhancement', 'fa-user', 1),
(7, 3, 'Cardiac Surgery', 'Heart surgical procedures', 'fa-heartbeat', 1),
(8, 3, 'Angioplasty', 'Coronary artery procedures', 'fa-heart', 1),
(9, 4, 'Hip Replacement', 'Joint replacement surgery', 'fa-bone', 1),
(10, 4, 'Knee Surgery', 'Orthopedic knee procedures', 'fa-bone', 1);

-- Proveedores de ejemplo
INSERT INTO providers (id, name, city, phone, email, is_active) VALUES
(1, 'MedCenter Clinic', 'Bogotá', '+57 1 234 5678', 'info@medcenter.com', 1),
(2, 'Dental Care Excellence', 'Medellín', '+57 4 567 8901', 'info@dentalcare.com', 1),
(3, 'Aesthetics Plus', 'Cartagena', '+57 5 890 1234', 'contact@aestheticsplus.com', 1);

-- Usuario proveedor de ejemplo
INSERT INTO usuarios (id, usuario, password, nombre, rol, provider_id) VALUES
(2, 'medcenter', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MedCenter Admin', 'prestador', 1);
-- Contraseña: password

-- Ofertas de ejemplo
INSERT INTO provider_service_offers (id, provider_id, service_id, title, description, price_from, currency, is_active) VALUES
(1, 1, 1, 'Premium Dental Implants Package', 'Complete dental implant procedure with post-operative care, follow-up consultations, and dental crown included. All performed by certified specialists.', 1500.00, 'USD', 1),
(2, 1, 7, 'Comprehensive Cardiac Surgery', 'Advanced cardiac surgical procedures with pre and post-operative care, ICU monitoring, and complete recovery support.', 15000.00, 'USD', 1),
(3, 2, 1, 'Dental Implants - Standard Package', 'Quality dental implant with titanium post and porcelain crown. Includes X-rays and consultation.', 1200.00, 'USD', 1),
(4, 2, 2, 'Professional Teeth Whitening', 'In-office professional teeth whitening treatment with long-lasting results. Safe and effective procedure.', 350.00, 'USD', 1),
(5, 3, 4, 'Rhinoplasty - Nose Reshaping', 'Cosmetic rhinoplasty performed by board-certified plastic surgeons. Includes consultation, surgery, and follow-up care.', 4500.00, 'USD', 1),
(6, 3, 5, 'Liposuction - Body Contouring', 'Advanced liposuction techniques for body contouring and fat removal. Multiple areas available.', 3500.00, 'USD', 1);

-- =======================================================
-- VERIFICACIÓN FINAL
-- =======================================================
SELECT 
    'Instalación LOCAL completada exitosamente' AS status,
    (SELECT COUNT(*) FROM services_header) AS services_header_rows,
    (SELECT COUNT(*) FROM service_categories) AS categories_rows,
    (SELECT COUNT(*) FROM service_catalog) AS services_rows,
    (SELECT COUNT(*) FROM providers) AS providers_rows,
    (SELECT COUNT(*) FROM usuarios) AS usuarios_rows,
    (SELECT COUNT(*) FROM provider_service_offers) AS offers_rows;

-- =======================================================
-- INFORMACIÓN DE ACCESO
-- =======================================================
SELECT '============================================' AS '';
SELECT 'USUARIOS CREADOS:' AS '';
SELECT '============================================' AS '';
SELECT 'Admin Principal:' AS '', 'usuario: admin | password: password' AS '';
SELECT 'Proveedor Demo:' AS '', 'usuario: medcenter | password: password' AS '';
SELECT '============================================' AS '';
