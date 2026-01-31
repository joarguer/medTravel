-- ============================================================
-- TABLA: service_providers
-- PROPÓSITO: Catálogo de proveedores de servicios de MedTravel
-- (Aerolíneas, Hoteles, Empresas de Transporte, Restaurantes, etc)
-- ============================================================

CREATE TABLE IF NOT EXISTS `service_providers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- INFORMACIÓN BÁSICA
  `provider_name` VARCHAR(255) NOT NULL COMMENT 'Nombre del proveedor',
  `provider_type` ENUM('airline','hotel','transport','restaurant','support','other') NULL COMMENT 'Tipo de proveedor',
  `tax_id` VARCHAR(50) NULL COMMENT 'NIT o Tax ID',
  `country` VARCHAR(100) DEFAULT 'Colombia' COMMENT 'País',
  `city` VARCHAR(100) NULL COMMENT 'Ciudad',
  
  -- CONTACTO PRINCIPAL
  `contact_name` VARCHAR(255) NULL COMMENT 'Nombre del contacto principal',
  `contact_position` VARCHAR(100) NULL COMMENT 'Cargo del contacto',
  `contact_email` VARCHAR(255) NULL COMMENT 'Email del contacto',
  `contact_phone` VARCHAR(50) NULL COMMENT 'Teléfono del contacto',
  `contact_mobile` VARCHAR(50) NULL COMMENT 'Celular del contacto',
  
  -- DATOS COMERCIALES
  `website` VARCHAR(255) NULL COMMENT 'Sitio web',
  `payment_terms` VARCHAR(100) NULL COMMENT 'Términos de pago (ej: 30 días)',
  `bank_account` VARCHAR(100) NULL COMMENT 'Cuenta bancaria',
  `preferred_payment_method` ENUM('transfer','check','cash','card') DEFAULT 'transfer',
  
  -- CALIFICACIÓN Y ESTADO
  `rating` DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Calificación 0-5',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `is_preferred` TINYINT(1) DEFAULT 0 COMMENT '1=Proveedor preferido',
  
  -- NOTAS
  `notes` TEXT NULL COMMENT 'Notas internas sobre el proveedor',
  `contract_details` TEXT NULL COMMENT 'Detalles del contrato',
  
  -- AUDITORÍA
  `created_by` INT(11) NULL COMMENT 'Usuario que creó el registro',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_name` (`provider_name`),
  INDEX `idx_type` (`provider_type`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_preferred` (`is_preferred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODIFICAR medtravel_services_catalog PARA USAR FK
-- ============================================================

-- Agregar campo provider_id
SET @dbname = DATABASE();
SET @tablename = 'medtravel_services_catalog';
SET @columnname = 'provider_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` INT(11) NULL COMMENT ''ID del proveedor'' AFTER `short_description`;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar foreign key (solo si no existe)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'medtravel_services_catalog' 
                  AND CONSTRAINT_NAME = 'fk_service_provider');

SET @sql = IF(@fk_exists = 0, 
  'ALTER TABLE `medtravel_services_catalog` ADD CONSTRAINT `fk_service_provider` 
   FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
  'SELECT 1;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- DATOS DE EJEMPLO: Proveedores iniciales
-- ============================================================

INSERT INTO `service_providers` 
  (`provider_name`, `provider_type`, `country`, `city`, `contact_name`, `contact_email`, `contact_phone`, `is_active`, `is_preferred`, `notes`) 
VALUES
  ('Avianca', 'airline', 'Colombia', 'Bogotá', 'Corporativo', 'corporate@avianca.com', '+57 1 5878888', 1, 1, 'Aerolínea principal para vuelos internacionales'),
  ('Hotel Casa Blanca', 'hotel', 'Colombia', 'Armenia', 'María González', 'reservas@hotelcasablanca.com', '+57 6 7450000', 1, 1, 'Hotel 4 estrellas cerca de clínicas'),
  ('TransExpress Armenia', 'transport', 'Colombia', 'Armenia', 'Carlos Pérez', 'info@transexpress.com', '+57 315 8888888', 1, 1, 'Servicio de transporte local confiable'),
  ('Hotel Estelar', 'hotel', 'Colombia', 'Armenia', 'Ana Martínez', 'reservas@hotelestelar.com', '+57 6 7412345', 1, 0, 'Hotel 5 estrellas opción premium'),
  ('RestCafé', 'restaurant', 'Colombia', 'Armenia', 'Luis Torres', 'contacto@restcafe.com', '+57 314 7777777', 1, 0, 'Restaurante con opciones de dieta especial')
ON DUPLICATE KEY UPDATE provider_name=provider_name;

-- ============================================================
-- MIGRAR DATOS EXISTENTES
-- ============================================================

-- Crear proveedores desde servicios existentes que tienen provider_name
INSERT INTO `service_providers` (`provider_name`, `contact_name`, `contact_email`, `contact_phone`, `notes`, `is_active`)
SELECT DISTINCT 
  msc.provider_name,
  msc.provider_contact,
  msc.provider_email,
  msc.provider_phone,
  msc.provider_notes,
  1
FROM `medtravel_services_catalog` msc
WHERE msc.provider_name IS NOT NULL 
  AND msc.provider_name != ''
  AND NOT EXISTS (
    SELECT 1 FROM service_providers sp 
    WHERE sp.provider_name = msc.provider_name
  );

-- Vincular servicios existentes con sus proveedores
UPDATE `medtravel_services_catalog` msc
INNER JOIN `service_providers` sp ON sp.provider_name = msc.provider_name
SET msc.provider_id = sp.id
WHERE msc.provider_id IS NULL;

-- ============================================================
-- VISTA: Servicios con información del proveedor
-- ============================================================

CREATE OR REPLACE VIEW `v_services_with_provider` AS
SELECT 
  msc.*,
  sp.provider_name AS provider_full_name,
  sp.provider_type AS provider_category,
  sp.contact_email AS provider_main_email,
  sp.contact_phone AS provider_main_phone,
  sp.rating AS provider_rating,
  sp.is_preferred AS provider_is_preferred
FROM medtravel_services_catalog msc
LEFT JOIN service_providers sp ON msc.provider_id = sp.id;

-- ============================================================
-- VERIFICACIÓN
-- ============================================================

SELECT '✅ Tabla service_providers creada correctamente' AS status;

SELECT 'Proveedores registrados:' AS '';
SELECT COUNT(*) AS total_proveedores,
       COUNT(CASE WHEN is_preferred = 1 THEN 1 END) AS preferidos,
       COUNT(CASE WHEN is_active = 1 THEN 1 END) AS activos
FROM service_providers;

SELECT 'Servicios vinculados:' AS '';
SELECT COUNT(*) AS total_servicios,
       COUNT(provider_id) AS con_proveedor,
       COUNT(*) - COUNT(provider_id) AS sin_proveedor
FROM medtravel_services_catalog;

SELECT 'Muestra de proveedores:' AS '';
SELECT id, provider_name, provider_type, city, is_preferred, is_active
FROM service_providers
LIMIT 5;
