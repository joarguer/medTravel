-- =================================================================
-- MEDTRAVEL - MEJORAS COMERCIALES - VERSION SEGURA (NO DUPLICA)
-- Este script verifica si cada columna/índice existe antes de crearlo
-- Fecha: 29 de enero de 2026
-- =================================================================

-- IMPORTANTE: Este script puede ejecutarse múltiples veces sin errores
-- porque verifica la existencia de cada elemento antes de crearlo

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =================================================================
-- 1. MONETIZACIÓN EN travel_packages
-- =================================================================

-- medtravel_fee_type
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_type');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_type` ENUM('fixed','percent') DEFAULT 'percent' COMMENT 'Tipo de tarifa MedTravel'",
  'SELECT "medtravel_fee_type already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- medtravel_fee_value
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_value');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor de tarifa: $ fijo o %'",
  'SELECT "medtravel_fee_value already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- medtravel_fee_amount
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_amount');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Monto calculado de tarifa'",
  'SELECT "medtravel_fee_amount already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- provider_commission_value
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'provider_commission_value');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `provider_commission_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Comisión al proveedor'",
  'SELECT "provider_commission_value already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- gross_margin
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'gross_margin');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `gross_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Margen bruto'",
  'SELECT "gross_margin already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- net_margin
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'net_margin');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD COLUMN `net_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Margen neto'",
  'SELECT "net_margin already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices travel_packages
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND INDEX_NAME = 'idx_margins');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD INDEX `idx_margins` (`status`, `net_margin`)",
  'SELECT "idx_margins already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND INDEX_NAME = 'idx_payment_status_dates');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `travel_packages` ADD INDEX `idx_payment_status_dates` (`payment_status`, `start_date`)",
  'SELECT "idx_payment_status_dates already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 2. VERIFICACIÓN DE PROVEEDORES
-- =================================================================

CREATE TABLE IF NOT EXISTS `provider_verification` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  `status` ENUM('pending','in_review','verified','rejected','suspended') DEFAULT 'pending',
  `verification_level` ENUM('basic','standard','premium') DEFAULT 'basic',
  `submitted_at` DATETIME DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `verified_by` INT(11) DEFAULT NULL,
  `expires_at` DATE DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `trust_score` INT(3) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_unique` (`provider_id`),
  KEY `idx_status` (`status`),
  KEY `idx_verified_at` (`verified_at`),
  KEY `idx_trust_score` (`trust_score`),
  CONSTRAINT `fk_verification_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_verification_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  `item_key` VARCHAR(60) NOT NULL,
  `item_label` VARCHAR(120) NOT NULL,
  `item_description` TEXT DEFAULT NULL,
  `item_category` ENUM('legal','medical','facilities','identity','insurance','other') DEFAULT 'other',
  `is_required` TINYINT(1) DEFAULT 1,
  `is_checked` TINYINT(1) DEFAULT 0,
  `checked_at` DATETIME DEFAULT NULL,
  `checked_by` INT(11) DEFAULT NULL,
  `evidence_type` ENUM('document','photo','link','manual_review','none') DEFAULT 'document',
  `evidence_document_id` INT(11) DEFAULT NULL,
  `evidence_url` VARCHAR(500) DEFAULT NULL,
  `evidence_notes` TEXT DEFAULT NULL,
  `valid_from` DATE DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_item_unique` (`provider_id`, `item_key`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_is_checked` (`is_checked`),
  KEY `idx_category` (`item_category`),
  CONSTRAINT `fk_verification_items_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  `document_type` ENUM('medical_license','business_registration','professional_certification','facility_photos','insurance_certificate','identity_document','tax_document','accreditation','other') DEFAULT 'other',
  `document_category` VARCHAR(100) DEFAULT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size` INT(11) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_extension` VARCHAR(10) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `document_date` DATE DEFAULT NULL,
  `expiration_date` DATE DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verified_by` INT(11) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `verification_notes` TEXT DEFAULT NULL,
  `uploaded_by` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_is_verified` (`is_verified`),
  CONSTRAINT `fk_provider_docs_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- 3. UTM TRACKING EN CLIENTES
-- =================================================================

-- utm_source
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'utm_source');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `utm_source` VARCHAR(80) DEFAULT NULL",
  'SELECT "utm_source already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- utm_medium
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'utm_medium');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `utm_medium` VARCHAR(80) DEFAULT NULL",
  'SELECT "utm_medium already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- utm_campaign
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'utm_campaign');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `utm_campaign` VARCHAR(120) DEFAULT NULL",
  'SELECT "utm_campaign already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- utm_content
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'utm_content');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `utm_content` VARCHAR(120) DEFAULT NULL",
  'SELECT "utm_content already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- utm_term
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'utm_term');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `utm_term` VARCHAR(120) DEFAULT NULL",
  'SELECT "utm_term already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- referred_by
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'referred_by');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `referred_by` VARCHAR(120) DEFAULT NULL",
  'SELECT "referred_by already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- landing_page
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'landing_page');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `landing_page` VARCHAR(500) DEFAULT NULL",
  'SELECT "landing_page already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- conversion_page
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'conversion_page');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `conversion_page` VARCHAR(500) DEFAULT NULL",
  'SELECT "conversion_page already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices clientes
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND INDEX_NAME = 'idx_utm_source');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD INDEX `idx_utm_source` (`utm_source`)",
  'SELECT "idx_utm_source already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND INDEX_NAME = 'idx_utm_campaign');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD INDEX `idx_utm_campaign` (`utm_campaign`)",
  'SELECT "idx_utm_campaign already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND INDEX_NAME = 'idx_utm_source_status');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD INDEX `idx_utm_source_status` (`utm_source`, `status`)",
  'SELECT "idx_utm_source_status already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND INDEX_NAME = 'idx_referred_by');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD INDEX `idx_referred_by` (`referred_by`)",
  'SELECT "idx_referred_by already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- client_timezone en clientes
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'client_timezone');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD COLUMN `client_timezone` VARCHAR(60) DEFAULT 'America/New_York'",
  'SELECT "client_timezone already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 4. TIMEZONES EN PROVIDERS
-- =================================================================

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'provider_timezone');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `providers` ADD COLUMN `provider_timezone` VARCHAR(60) DEFAULT 'America/Bogota'",
  'SELECT "provider_timezone already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 5. TIMEZONES EN APPOINTMENTS
-- =================================================================

-- appointment_datetime_utc
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'appointment_datetime_utc');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD COLUMN `appointment_datetime_utc` DATETIME DEFAULT NULL",
  'SELECT "appointment_datetime_utc already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- appointment_end_utc
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'appointment_end_utc');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD COLUMN `appointment_end_utc` DATETIME DEFAULT NULL",
  'SELECT "appointment_end_utc already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- client_timezone en appointments
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'client_timezone');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD COLUMN `client_timezone` VARCHAR(60) DEFAULT NULL",
  'SELECT "client_timezone already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- provider_timezone en appointments
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'provider_timezone');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD COLUMN `provider_timezone` VARCHAR(60) DEFAULT NULL",
  'SELECT "provider_timezone already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices appointments
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'idx_datetime_utc');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD INDEX `idx_datetime_utc` (`appointment_datetime_utc`)",
  'SELECT "idx_datetime_utc already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'idx_provider_date_utc');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `appointments` ADD INDEX `idx_provider_date_utc` (`provider_id`, `appointment_datetime_utc`)",
  'SELECT "idx_provider_date_utc already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 6. STORED PROCEDURE - Checklist de Verificación
-- =================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_create_verification_checklist`$$

CREATE PROCEDURE `sp_create_verification_checklist`(IN p_provider_id INT)
BEGIN
  DECLARE v_exists INT;
  
  SELECT COUNT(*) INTO v_exists 
  FROM provider_verification_items 
  WHERE provider_id = p_provider_id;
  
  IF v_exists = 0 THEN
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'business_registration', 'Registro Empresarial', 'Certificado de cámara de comercio', 'legal', 1, 'document'),
      (p_provider_id, 'tax_id', 'RUT o Tax ID', 'Identificación tributaria vigente', 'legal', 1, 'document'),
      (p_provider_id, 'medical_license', 'Licencia Médica', 'Licencia profesional vigente', 'medical', 1, 'document'),
      (p_provider_id, 'professional_certifications', 'Certificaciones', 'Certificados de especialización', 'medical', 0, 'document'),
      (p_provider_id, 'clinic_accreditation', 'Acreditación Clínica', 'Habilitación secretaría de salud', 'medical', 1, 'document'),
      (p_provider_id, 'facility_photos', 'Fotos Instalaciones', 'Mínimo 5 fotos de áreas', 'facilities', 1, 'photo'),
      (p_provider_id, 'equipment_certification', 'Certificación Equipos', 'Calibración de equipos médicos', 'facilities', 0, 'document'),
      (p_provider_id, 'owner_identity', 'Identidad Responsable', 'Cédula o pasaporte del director', 'identity', 1, 'document'),
      (p_provider_id, 'staff_credentials', 'Credenciales Personal', 'Lista de personal con licencias', 'identity', 0, 'document'),
      (p_provider_id, 'liability_insurance', 'Seguro Responsabilidad', 'Póliza de seguro vigente', 'insurance', 1, 'document'),
      (p_provider_id, 'malpractice_insurance', 'Seguro Mala Praxis', 'Póliza médico profesional', 'insurance', 0, 'document');
  END IF;
END$$

DELIMITER ;

-- =================================================================
-- 7. TRIGGERS - Cálculo Automático de Márgenes
-- =================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_travel_packages_calc_margins_insert`$$

CREATE TRIGGER `trg_travel_packages_calc_margins_insert`
BEFORE INSERT ON `travel_packages`
FOR EACH ROW
BEGIN
  DECLARE v_total_costs DECIMAL(10,2);
  
  SET v_total_costs = 
    IFNULL(NEW.flight_cost, 0) + 
    IFNULL(NEW.hotel_total_cost, 0) + 
    IFNULL(NEW.transport_cost, 0) + 
    IFNULL(NEW.meals_cost, 0) + 
    IFNULL(NEW.medical_service_cost, 0) + 
    IFNULL(NEW.additional_services_cost, 0);
  
  IF NEW.medtravel_fee_type = 'fixed' THEN
    SET NEW.medtravel_fee_amount = IFNULL(NEW.medtravel_fee_value, 0);
  ELSE
    SET NEW.medtravel_fee_amount = (IFNULL(NEW.total_package_cost, 0) * IFNULL(NEW.medtravel_fee_value, 0)) / 100;
  END IF;
  
  SET NEW.gross_margin = IFNULL(NEW.total_package_cost, 0) - v_total_costs;
  SET NEW.net_margin = NEW.gross_margin - IFNULL(NEW.provider_commission_value, 0);
END$$

DROP TRIGGER IF EXISTS `trg_travel_packages_calc_margins_update`$$

CREATE TRIGGER `trg_travel_packages_calc_margins_update`
BEFORE UPDATE ON `travel_packages`
FOR EACH ROW
BEGIN
  DECLARE v_total_costs DECIMAL(10,2);
  
  SET v_total_costs = 
    IFNULL(NEW.flight_cost, 0) + 
    IFNULL(NEW.hotel_total_cost, 0) + 
    IFNULL(NEW.transport_cost, 0) + 
    IFNULL(NEW.meals_cost, 0) + 
    IFNULL(NEW.medical_service_cost, 0) + 
    IFNULL(NEW.additional_services_cost, 0);
  
  IF NEW.medtravel_fee_type = 'fixed' THEN
    SET NEW.medtravel_fee_amount = IFNULL(NEW.medtravel_fee_value, 0);
  ELSE
    SET NEW.medtravel_fee_amount = (IFNULL(NEW.total_package_cost, 0) * IFNULL(NEW.medtravel_fee_value, 0)) / 100;
  END IF;
  
  SET NEW.gross_margin = IFNULL(NEW.total_package_cost, 0) - v_total_costs;
  SET NEW.net_margin = NEW.gross_margin - IFNULL(NEW.provider_commission_value, 0);
END$$

DELIMITER ;

-- =================================================================
-- 8. VISTAS - Reportes
-- =================================================================

-- IMPORTANTE: providers usa "name", "phone", "email" (NO "nombre", "telefono")

CREATE OR REPLACE VIEW `v_package_margins` AS
SELECT 
  p.id,
  p.package_name,
  c.nombre AS client_name,
  c.apellido AS client_lastname,
  p.start_date,
  p.end_date,
  p.total_package_cost,
  p.medtravel_fee_type,
  p.medtravel_fee_value,
  p.medtravel_fee_amount,
  p.provider_commission_value,
  p.gross_margin,
  p.net_margin,
  ROUND((p.gross_margin / NULLIF(p.total_package_cost, 0)) * 100, 2) AS gross_margin_percent,
  ROUND((p.net_margin / NULLIF(p.total_package_cost, 0)) * 100, 2) AS net_margin_percent,
  p.status,
  p.payment_status,
  p.created_at
FROM travel_packages p
INNER JOIN clientes c ON p.client_id = c.id
WHERE p.total_package_cost > 0;

CREATE OR REPLACE VIEW `v_campaign_performance` AS
SELECT 
  utm_source,
  utm_campaign,
  utm_medium,
  COUNT(*) AS total_leads,
  SUM(CASE WHEN status IN ('confirmado','en_viaje','post_tratamiento','finalizado') THEN 1 ELSE 0 END) AS converted,
  ROUND(
    (SUM(CASE WHEN status IN ('confirmado','en_viaje','post_tratamiento','finalizado') THEN 1 ELSE 0 END) / COUNT(*)) * 100,
    2
  ) AS conversion_rate,
  MIN(created_at) AS first_lead,
  MAX(created_at) AS last_lead
FROM clientes
WHERE utm_source IS NOT NULL
GROUP BY utm_source, utm_campaign, utm_medium
ORDER BY total_leads DESC;

CREATE OR REPLACE VIEW `v_verified_providers` AS
SELECT 
  p.id,
  p.name AS provider_name,
  p.email,
  p.phone AS telefono,
  pv.status AS verification_status,
  pv.verification_level,
  pv.trust_score,
  pv.verified_at,
  pv.expires_at,
  COUNT(pvi.id) AS total_items,
  SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) AS checked_items,
  CASE 
    WHEN COUNT(pvi.id) > 0 THEN 
      ROUND((SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) / COUNT(pvi.id)) * 100, 2)
    ELSE 0
  END AS completion_percent
FROM providers p
LEFT JOIN provider_verification pv ON p.id = pv.provider_id
LEFT JOIN provider_verification_items pvi ON p.id = pvi.provider_id
GROUP BY p.id, p.name, p.email, p.phone, pv.status, pv.verification_level, pv.trust_score, pv.verified_at, pv.expires_at;

-- =================================================================
-- 9. DATOS INICIALES
-- =================================================================

-- Actualizar timezones por defecto solo si la columna existe
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'provider_timezone');

SET @sqlstmt := IF(@column_exists > 0, 
  "UPDATE `providers` SET `provider_timezone` = 'America/Bogota' WHERE `provider_timezone` IS NULL OR `provider_timezone` = ''",
  'SELECT "provider_timezone column does not exist yet" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'client_timezone');

SET @sqlstmt := IF(@column_exists > 0, 
  "UPDATE `clientes` SET `client_timezone` = 'America/New_York' WHERE `client_timezone` IS NULL OR `client_timezone` = ''",
  'SELECT "client_timezone column does not exist yet" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 10. ÍNDICES ADICIONALES
-- =================================================================

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND INDEX_NAME = 'idx_utm_status_date');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `clientes` ADD INDEX `idx_utm_status_date` (`utm_source`, `status`, `created_at`)",
  'SELECT "idx_utm_status_date already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_verification' AND INDEX_NAME = 'idx_status_score');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `provider_verification` ADD INDEX `idx_status_score` (`status`, `trust_score`)",
  'SELECT "idx_status_score already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_documents' AND INDEX_NAME = 'idx_verified_expiration');
SET @sqlstmt := IF(@exist = 0, 
  "ALTER TABLE `provider_documents` ADD INDEX `idx_verified_expiration` (`is_verified`, `expiration_date`)",
  'SELECT "idx_verified_expiration already exists" AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

-- =================================================================
-- VALIDACIÓN FINAL
-- =================================================================

SELECT 'Script ejecutado exitosamente. Verificando estructura...' AS mensaje;

-- Mostrar columnas agregadas en travel_packages
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'travel_packages' 
  AND COLUMN_NAME IN ('medtravel_fee_type', 'medtravel_fee_value', 'medtravel_fee_amount', 'provider_commission_value', 'gross_margin', 'net_margin')
ORDER BY ORDINAL_POSITION;

-- Mostrar columnas UTM en clientes
SELECT COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'clientes' 
  AND COLUMN_NAME LIKE 'utm_%' OR COLUMN_NAME IN ('referred_by', 'landing_page', 'conversion_page', 'client_timezone')
ORDER BY ORDINAL_POSITION;

-- Mostrar tablas de verificación creadas
SELECT TABLE_NAME, CREATE_TIME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'provider_%'
ORDER BY TABLE_NAME;

-- =================================================================
-- FIN DEL SCRIPT SEGURO
-- =================================================================
