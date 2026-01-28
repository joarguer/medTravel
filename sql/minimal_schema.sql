-- Esquema mínimo para entorno local
CREATE DATABASE IF NOT EXISTS bolsacar_medtravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bolsacar_medtravel;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  nombre VARCHAR(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carrucel (
  id INT AUTO_INCREMENT PRIMARY KEY,
  img VARCHAR(255) DEFAULT NULL,
  over_title VARCHAR(255) DEFAULT NULL,
  title VARCHAR(255) DEFAULT NULL,
  parrafo TEXT DEFAULT NULL,
  btn VARCHAR(100) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 0,
  orden INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS empresas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_comercial VARCHAR(255),
  estado TINYINT(1) DEFAULT 1,
  email VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS about_header (
  id INT AUTO_INCREMENT PRIMARY KEY,
  img VARCHAR(255),
  titulo VARCHAR(255) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS about_us (
  id INT AUTO_INCREMENT PRIMARY KEY,
  img VARCHAR(255),
  bg VARCHAR(255),
  titulo VARCHAR(255) DEFAULT NULL,
  texto TEXT DEFAULT NULL,
  activo TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS specialist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  activo TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS specialist_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  specialist_id INT,
  activo TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asegurar columnas usadas por plantilla en `specialist_list`
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='img';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN img VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='facebook';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN facebook VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='twiter';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN twiter VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='instagram';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN instagram VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='titulo';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN titulo VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='specialist_list' AND COLUMN_NAME='subtitulo';
SET @s = IF(@c=0,'ALTER TABLE specialist_list ADD COLUMN subtitulo VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Insertar ejemplos en `specialist_list` usando imágenes existentes
INSERT IGNORE INTO specialist_list (img, facebook, twiter, instagram, titulo, subtitulo, activo) VALUES
('img/guide-1.jpg', '#', '#', '#', 'Dr. John Doe', 'Cardiologist', 0),
('img/guide-2.jpg', '#', '#', '#', 'Dr. Jane Smith', 'Dentist', 0),
('img/guide-3.jpg', '#', '#', '#', 'Dr. Pablo Ruiz', 'Surgeon', 0);

CREATE TABLE IF NOT EXISTS social_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  url VARCHAR(255),
  activo TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certificado (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT,
  archivo VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario admin por defecto: usuario=admin contraseña=admin123 (MD5)
INSERT IGNORE INTO usuarios (usuario, password, nombre) VALUES ('admin', '0192023a7bbd73250516f069df18b500', 'Administrador');

-- Entradas de ejemplo con recursos existentes en el proyecto
-- Asegurar columnas nuevas en tablas existentes (no destructivo)
-- Añadir columna `over_title` si no existe
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='carrucel' AND COLUMN_NAME='over_title';
SET @s = IF(@c=0,'ALTER TABLE carrucel ADD COLUMN over_title VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Añadir columna `title` si no existe
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='carrucel' AND COLUMN_NAME='title';
SET @s = IF(@c=0,'ALTER TABLE carrucel ADD COLUMN title VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Añadir columna `parrafo` si no existe
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='carrucel' AND COLUMN_NAME='parrafo';
SET @s = IF(@c=0,'ALTER TABLE carrucel ADD COLUMN parrafo TEXT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Añadir columna `btn` si no existe
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='carrucel' AND COLUMN_NAME='btn';
SET @s = IF(@c=0,'ALTER TABLE carrucel ADD COLUMN btn VARCHAR(100) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- about_header: titulo
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_header' AND COLUMN_NAME='titulo';
SET @s = IF(@c=0,'ALTER TABLE about_header ADD COLUMN titulo VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- about_header: title, subtitle_1, subtitle_2 (nombres usados en template)
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_header' AND COLUMN_NAME='title';
SET @s = IF(@c=0,'ALTER TABLE about_header ADD COLUMN title VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_header' AND COLUMN_NAME='subtitle_1';
SET @s = IF(@c=0,'ALTER TABLE about_header ADD COLUMN subtitle_1 VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_header' AND COLUMN_NAME='subtitle_2';
SET @s = IF(@c=0,'ALTER TABLE about_header ADD COLUMN subtitle_2 VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- about_us: titulo y texto
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='titulo';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN titulo VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='texto';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN texto TEXT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- about_us: columnas usadas por `about.php`
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='titulo_small';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN titulo_small VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='titulo_1';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN titulo_1 VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='titulo_2';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN titulo_2 VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='paragrafo';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN paragrafo TEXT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='about_us' AND COLUMN_NAME='list';
SET @s = IF(@c=0,'ALTER TABLE about_us ADD COLUMN `list` TEXT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
INSERT IGNORE INTO carrucel (img, over_title, title, parrafo, btn, activo, orden) VALUES
('img/carousel-1.jpg', 'Warning', 'Welcome to MedTravel', 'Discover our medical tourism packages.', 'Learn More', 0, 1),
('img/carousel-2.jpg', 'Health', 'Top Specialists', 'Find certified specialists and clinics.', 'View Specialists', 0, 2),
('img/carousel-3.jpg', 'Care', 'Quality Services', 'Personalized care for international patients.', 'Contact Us', 0, 3);

INSERT IGNORE INTO empresas (nombre_comercial, estado, email) VALUES ('Empresa Demo', 1, 'demo@example.com');

INSERT IGNORE INTO about_header (img, titulo, activo) VALUES ('img/about-header.jpg', 'About MedTravel', 0);
INSERT IGNORE INTO about_us (img, bg, titulo, texto, activo) VALUES ('img/about-img.jpg', 'img/about-img-bg.png', 'Who We Are', 'MedTravel connects patients with top clinics worldwide.', 0);
-- Insertar fila de `about_us` con los campos exactos usados por la plantilla
INSERT IGNORE INTO about_us (img, bg, titulo_small, titulo_1, titulo_2, paragrafo, `list`, activo) VALUES (
  'img/about-img.jpg',
  'img/about-img-bg.png',
  'Warning',
  'Who We',
  'Are',
  '<p>MedTravel connects patients with top clinics worldwide. We provide personalized care and assistance.</p>',
  '["Personalized treatment","Verified specialists","International support","Affordable packages"]',
  0
);
