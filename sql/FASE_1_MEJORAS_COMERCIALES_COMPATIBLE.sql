-- =================================================================
-- MEDTRAVEL - MEJORAS COMERCIALES - VERSION COMPATIBLE MySQL 5.7+
-- Este archivo corrige los errores de sintaxis del archivo original
-- Fecha: 29 de enero de 2026
-- =================================================================

-- IMPORTANTE: 
-- 1. No usar "ADD COLUMN IF NOT EXISTS" (no soportado en MySQL < 8.0.29)
-- 2. La tabla providers usa "name", "phone", "email" (NO "nombre", "telefono")
-- 3. Si ya ejecutaste parcialmente el otro SQL, revisa qué columnas ya existen

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =================================================================
-- 1. MONETIZACIÓN EN travel_packages
-- =================================================================

-- Agregar columnas si NO existen (método compatible)
ALTER TABLE `travel_packages` 
  ADD COLUMN `medtravel_fee_type` ENUM('fixed','percent') DEFAULT 'percent' COMMENT 'Tipo de tarifa MedTravel';

ALTER TABLE `travel_packages` 
  ADD COLUMN `medtravel_fee_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor de tarifa: $ fijo o %';

ALTER TABLE `travel_packages` 
  ADD COLUMN `medtravel_fee_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Monto calculado de tarifa';

ALTER TABLE `travel_packages` 
  ADD COLUMN `provider_commission_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Comisión al proveedor';

ALTER TABLE `travel_packages` 
  ADD COLUMN `gross_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Margen bruto';

ALTER TABLE `travel_packages` 
  ADD COLUMN `net_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Margen neto';

-- Índices
ALTER TABLE `travel_packages` ADD INDEX `idx_margins` (`status`, `net_margin`);
ALTER TABLE `travel_packages` ADD INDEX `idx_payment_status_dates` (`payment_status`, `start_date`);

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

ALTER TABLE `clientes` ADD COLUMN `utm_source` VARCHAR(80) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `utm_medium` VARCHAR(80) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `utm_campaign` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `utm_content` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `utm_term` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `referred_by` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `landing_page` VARCHAR(500) DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN `conversion_page` VARCHAR(500) DEFAULT NULL;

-- Índices
ALTER TABLE `clientes` ADD INDEX `idx_utm_source` (`utm_source`);
ALTER TABLE `clientes` ADD INDEX `idx_utm_campaign` (`utm_campaign`);
ALTER TABLE `clientes` ADD INDEX `idx_utm_source_status` (`utm_source`, `status`);
ALTER TABLE `clientes` ADD INDEX `idx_referred_by` (`referred_by`);

-- =================================================================
-- 4. TIMEZONES
-- =================================================================

ALTER TABLE `clientes` ADD COLUMN `client_timezone` VARCHAR(60) DEFAULT 'America/New_York';
ALTER TABLE `providers` ADD COLUMN `provider_timezone` VARCHAR(60) DEFAULT 'America/Bogota';

ALTER TABLE `appointments` ADD COLUMN `appointment_datetime_utc` DATETIME DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN `appointment_end_utc` DATETIME DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN `client_timezone` VARCHAR(60) DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN `provider_timezone` VARCHAR(60) DEFAULT NULL;

-- Índices
ALTER TABLE `appointments` ADD INDEX `idx_datetime_utc` (`appointment_datetime_utc`);
ALTER TABLE `appointments` ADD INDEX `idx_provider_date_utc` (`provider_id`, `appointment_datetime_utc`);

-- =================================================================
-- 5. STORED PROCEDURE - Checklist de Verificación
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
-- 6. TRIGGERS - Cálculo Automático de Márgenes
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
-- 7. VISTAS - Reportes
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
-- 8. DATOS INICIALES
-- =================================================================

-- Actualizar timezones por defecto
UPDATE `providers` 
SET `provider_timezone` = 'America/Bogota' 
WHERE `provider_timezone` IS NULL OR `provider_timezone` = '';

UPDATE `clientes` 
SET `client_timezone` = 'America/New_York' 
WHERE `client_timezone` IS NULL OR `client_timezone` = '';

-- =================================================================
-- ÍNDICES ADICIONALES
-- =================================================================

ALTER TABLE `clientes` ADD INDEX `idx_utm_status_date` (`utm_source`, `status`, `created_at`);
ALTER TABLE `provider_verification` ADD INDEX `idx_status_score` (`status`, `trust_score`);
ALTER TABLE `provider_documents` ADD INDEX `idx_verified_expiration` (`is_verified`, `expiration_date`);

COMMIT;

-- =================================================================
-- NOTAS DE INSTALACIÓN
-- =================================================================

/*
IMPORTANTE: Si aparece error "Duplicate column name" al ejecutar este script,
significa que ya ejecutaste parcialmente el archivo anterior.

SOLUCIÓN:
1. Ver qué columnas ya existen:
   DESCRIBE travel_packages;
   DESCRIBE clientes;
   DESCRIBE appointments;

2. Comentar (con --) las líneas ALTER TABLE de las columnas que YA EXISTAN

3. Las tablas provider_verification, provider_verification_items y provider_documents
   se crean con IF NOT EXISTS, así que no darán error si ya existen.

4. Los triggers y stored procedures se eliminan y recrean (DROP IF EXISTS)
   así que no hay problema.

5. Las vistas se crean con CREATE OR REPLACE así que se sobrescriben.
*/

-- =================================================================
-- FIN DEL SCRIPT COMPATIBLE
-- =================================================================
