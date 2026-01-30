-- =================================================================
-- MEDTRAVEL - MEJORAS COMERCIALES FASE 1
-- Script de migración para optimización de negocio
-- Fecha: 29 de enero de 2026
-- =================================================================

-- IMPORTANTE: Este script extiende las tablas existentes sin romper compatibilidad
-- Todas las columnas nuevas son NULLABLE o tienen DEFAULT
-- Ejecutar DESPUÉS de FASE_1_CRM_AGENDAMIENTO.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =================================================================
-- 1. MONETIZACIÓN EXPLÍCITA EN travel_packages
-- =================================================================

-- Agregar columnas de modelo de negocio y márgenes
-- Verificar si la columna ya existe antes de agregarla (compatible con MySQL 5.7+)
SET @exist_fee_type := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_type');
SET @sqlstmt := IF(@exist_fee_type = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_type` ENUM(''fixed'',''percent'') DEFAULT ''percent'' COMMENT ''Tipo de tarifa MedTravel''', 
  'SELECT ''Column medtravel_fee_type already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_fee_value := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_value');
SET @sqlstmt := IF(@exist_fee_value = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Valor de tarifa: $ fijo o % porcentaje''', 
  'SELECT ''Column medtravel_fee_value already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_fee_amount := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'medtravel_fee_amount');
SET @sqlstmt := IF(@exist_fee_amount = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `medtravel_fee_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Monto calculado de tarifa MedTravel''', 
  'SELECT ''Column medtravel_fee_amount already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_commission := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'provider_commission_value');
SET @sqlstmt := IF(@exist_commission = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `provider_commission_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Comisión pagada al proveedor''', 
  'SELECT ''Column provider_commission_value already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_gross := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'gross_margin');
SET @sqlstmt := IF(@exist_gross = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `gross_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Margen bruto (total - costos)''', 
  'SELECT ''Column gross_margin already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_net := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND COLUMN_NAME = 'net_margin');
SET @sqlstmt := IF(@exist_net = 0, 
  'ALTER TABLE `travel_packages` ADD COLUMN `net_margin` DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Margen neto (después de comisiones)''', 
  'SELECT ''Column net_margin already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Comentario sobre el modelo:
-- total_package_cost = precio final al cliente (incluye ganancia MedTravel)
-- costos_totales = flight_cost + hotel_total_cost + transport_cost + meals_cost + medical_service_cost + additional_services_cost
-- gross_margin = total_package_cost - costos_totales
-- medtravel_fee_amount = si fee_type='fixed' entonces fee_value, sino (total_package_cost * fee_value / 100)
-- net_margin = gross_margin - provider_commission_value

-- Índices para reportes comerciales (verificar si existen primero)
SET @exist_idx1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND INDEX_NAME = 'idx_margins');
SET @sqlstmt := IF(@exist_idx1 = 0, 
  'ALTER TABLE `travel_packages` ADD INDEX `idx_margins` (`status`, `net_margin`)', 
  'SELECT ''Index idx_margins already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_idx2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'travel_packages' AND INDEX_NAME = 'idx_payment_status_dates');
SET @sqlstmt := IF(@exist_idx2 = 0, 
  'ALTER TABLE `travel_packages` ADD INDEX `idx_payment_status_dates` (`payment_status`, `start_date`)', 
  'SELECT ''Index idx_payment_status_dates already exists'' AS msg');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 2. CONFIANZA DOCUMENTADA - Verificación de Proveedores
-- =================================================================

-- Tabla principal de verificación por proveedor
CREATE TABLE IF NOT EXISTS `provider_verification` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  
  -- Estado de verificación
  `status` ENUM('pending','in_review','verified','rejected','suspended') DEFAULT 'pending',
  `verification_level` ENUM('basic','standard','premium') DEFAULT 'basic' COMMENT 'Nivel de verificación alcanzado',
  
  -- Fechas importantes
  `submitted_at` DATETIME DEFAULT NULL COMMENT 'Fecha de envío de documentación',
  `verified_at` DATETIME DEFAULT NULL,
  `verified_by` INT(11) DEFAULT NULL COMMENT 'Admin que verificó',
  `expires_at` DATE DEFAULT NULL COMMENT 'Fecha de expiración de verificación',
  
  -- Notas y observaciones
  `admin_notes` TEXT DEFAULT NULL COMMENT 'Notas internas del equipo',
  `rejection_reason` TEXT DEFAULT NULL,
  
  -- Score de confianza (0-100)
  `trust_score` INT(3) DEFAULT 0 COMMENT 'Puntaje calculado basado en items verificados',
  
  -- Auditoría
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_unique` (`provider_id`),
  KEY `idx_status` (`status`),
  KEY `idx_verified_at` (`verified_at`),
  KEY `idx_trust_score` (`trust_score`),
  
  CONSTRAINT `fk_verification_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estado de verificación de proveedores';

-- Tabla de items del checklist de verificación
CREATE TABLE IF NOT EXISTS `provider_verification_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  
  -- Identificador del item
  `item_key` VARCHAR(60) NOT NULL COMMENT 'Clave única del item (ej: licencia_medica)',
  `item_label` VARCHAR(120) NOT NULL COMMENT 'Etiqueta visible',
  `item_description` TEXT DEFAULT NULL COMMENT 'Descripción detallada del requisito',
  `item_category` ENUM('legal','medical','facilities','identity','insurance','other') DEFAULT 'other',
  
  -- Estado del item
  `is_required` TINYINT(1) DEFAULT 1 COMMENT '¿Es obligatorio para verificación?',
  `is_checked` TINYINT(1) DEFAULT 0,
  `checked_at` DATETIME DEFAULT NULL,
  `checked_by` INT(11) DEFAULT NULL COMMENT 'Admin que verificó este item',
  
  -- Evidencia
  `evidence_type` ENUM('document','photo','link','manual_review','none') DEFAULT 'document',
  `evidence_document_id` INT(11) DEFAULT NULL COMMENT 'FK a tabla de documentos',
  `evidence_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL externa si aplica',
  `evidence_notes` TEXT DEFAULT NULL,
  
  -- Validez temporal
  `valid_from` DATE DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL COMMENT 'Fecha de expiración del item',
  
  -- Auditoría
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_item_unique` (`provider_id`, `item_key`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_is_checked` (`is_checked`),
  KEY `idx_category` (`item_category`),
  
  CONSTRAINT `fk_verification_items_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items del checklist de verificación';

-- Tabla de documentos de proveedores (separada de client_documents)
CREATE TABLE IF NOT EXISTS `provider_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  
  -- Tipo y categoría
  `document_type` ENUM(
    'medical_license',
    'business_registration',
    'professional_certification',
    'facility_photos',
    'insurance_certificate',
    'identity_document',
    'tax_document',
    'accreditation',
    'other'
  ) DEFAULT 'other',
  `document_category` VARCHAR(100) DEFAULT NULL,
  
  -- Información del archivo
  `file_path` VARCHAR(500) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size` INT(11) DEFAULT NULL COMMENT 'Bytes',
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_extension` VARCHAR(10) DEFAULT NULL,
  
  -- Metadata
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `document_date` DATE DEFAULT NULL,
  `expiration_date` DATE DEFAULT NULL,
  
  -- Verificación
  `is_verified` TINYINT(1) DEFAULT 0,
  `verified_by` INT(11) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `verification_notes` TEXT DEFAULT NULL,
  
  -- Auditoría
  `uploaded_by` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_is_verified` (`is_verified`),
  
  CONSTRAINT `fk_provider_docs_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos de verificación de proveedores';

-- =================================================================
-- 3. TRACKING DE CAMPAÑAS - Marketing y Conversión
-- =================================================================

-- Agregar columnas UTM y tracking a clientes
ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `utm_source` VARCHAR(80) DEFAULT NULL COMMENT 'Origen del tráfico (google, facebook, email)',
  ADD COLUMN IF NOT EXISTS `utm_medium` VARCHAR(80) DEFAULT NULL COMMENT 'Medio (cpc, banner, newsletter)',
  ADD COLUMN IF NOT EXISTS `utm_campaign` VARCHAR(120) DEFAULT NULL COMMENT 'Nombre de campaña',
  ADD COLUMN IF NOT EXISTS `utm_content` VARCHAR(120) DEFAULT NULL COMMENT 'Contenido/variante del anuncio',
  ADD COLUMN IF NOT EXISTS `utm_term` VARCHAR(120) DEFAULT NULL COMMENT 'Términos de búsqueda',
  ADD COLUMN IF NOT EXISTS `referred_by` VARCHAR(120) DEFAULT NULL COMMENT 'Referido por (texto libre o ID)',
  ADD COLUMN IF NOT EXISTS `landing_page` VARCHAR(500) DEFAULT NULL COMMENT 'Primera página visitada',
  ADD COLUMN IF NOT EXISTS `conversion_page` VARCHAR(500) DEFAULT NULL COMMENT 'Página donde se convirtió';

-- Índices para análisis de marketing
ALTER TABLE `clientes` ADD INDEX IF NOT EXISTS `idx_utm_source` (`utm_source`);
ALTER TABLE `clientes` ADD INDEX IF NOT EXISTS `idx_utm_campaign` (`utm_campaign`);
ALTER TABLE `clientes` ADD INDEX IF NOT EXISTS `idx_utm_source_status` (`utm_source`, `status`);
ALTER TABLE `clientes` ADD INDEX IF NOT EXISTS `idx_referred_by` (`referred_by`);

-- =================================================================
-- 4. TIMEZONES - Operaciones Sin Errores
-- =================================================================

-- Agregar timezone a clientes (mayormente USA)
ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `client_timezone` VARCHAR(60) DEFAULT 'America/New_York' COMMENT 'Zona horaria del cliente (IANA timezone)';

-- Agregar timezone a proveedores (Colombia)
ALTER TABLE `providers`
  ADD COLUMN IF NOT EXISTS `provider_timezone` VARCHAR(60) DEFAULT 'America/Bogota' COMMENT 'Zona horaria del proveedor (IANA timezone)';

-- Actualizar tabla appointments para almacenar en UTC + timezone
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `appointment_datetime_utc` DATETIME DEFAULT NULL COMMENT 'Hora en UTC (universal)',
  ADD COLUMN IF NOT EXISTS `appointment_end_utc` DATETIME DEFAULT NULL COMMENT 'Fin en UTC',
  ADD COLUMN IF NOT EXISTS `client_timezone` VARCHAR(60) DEFAULT NULL COMMENT 'TZ del cliente al momento de crear',
  ADD COLUMN IF NOT EXISTS `provider_timezone` VARCHAR(60) DEFAULT NULL COMMENT 'TZ del proveedor al momento de crear';

-- Índices para queries por timezone
ALTER TABLE `appointments` ADD INDEX IF NOT EXISTS `idx_datetime_utc` (`appointment_datetime_utc`);
ALTER TABLE `appointments` ADD INDEX IF NOT EXISTS `idx_provider_date_utc` (`provider_id`, `appointment_datetime_utc`);

-- Nota: appointment_datetime existente se mantiene por compatibilidad
-- La lógica del código decidirá si usar datetime (local del servidor) o datetime_utc

-- =================================================================
-- 5. DATOS SEMILLA - Items de Verificación Estándar
-- =================================================================

-- Procedimiento para crear items de verificación estándar para un proveedor
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_create_verification_checklist`(IN p_provider_id INT)
BEGIN
  DECLARE v_exists INT;
  
  -- Verificar si ya existen items para este proveedor
  SELECT COUNT(*) INTO v_exists 
  FROM provider_verification_items 
  WHERE provider_id = p_provider_id;
  
  -- Solo crear si no existen items previos
  IF v_exists = 0 THEN
    
    -- Items de categoría LEGAL
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'business_registration', 'Registro Empresarial', 'Certificado de cámara de comercio o registro de empresa', 'legal', 1, 'document'),
      (p_provider_id, 'tax_id', 'RUT o Tax ID', 'Identificación tributaria vigente', 'legal', 1, 'document');
    
    -- Items de categoría MEDICAL
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'medical_license', 'Licencia Médica', 'Licencia profesional médica vigente (si aplica)', 'medical', 1, 'document'),
      (p_provider_id, 'professional_certifications', 'Certificaciones Profesionales', 'Certificados de especialización o membresías profesionales', 'medical', 0, 'document'),
      (p_provider_id, 'clinic_accreditation', 'Acreditación de Clínica', 'Certificado de habilitación de secretaría de salud', 'medical', 1, 'document');
    
    -- Items de categoría FACILITIES
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'facility_photos', 'Fotos de Instalaciones', 'Mínimo 5 fotos de consultorios, quirófanos, áreas de recuperación', 'facilities', 1, 'photo'),
      (p_provider_id, 'equipment_certification', 'Certificación de Equipos', 'Documentos de calibración/certificación de equipos médicos', 'facilities', 0, 'document');
    
    -- Items de categoría IDENTITY
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'owner_identity', 'Identidad del Responsable', 'Cédula o pasaporte del director/dueño', 'identity', 1, 'document'),
      (p_provider_id, 'staff_credentials', 'Credenciales del Personal', 'Lista de personal médico con sus licencias', 'identity', 0, 'document');
    
    -- Items de categoría INSURANCE
    INSERT INTO provider_verification_items 
      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
    VALUES
      (p_provider_id, 'liability_insurance', 'Seguro de Responsabilidad', 'Póliza de seguro de responsabilidad civil vigente', 'insurance', 1, 'document'),
      (p_provider_id, 'malpractice_insurance', 'Seguro contra Mala Praxis', 'Póliza de seguro médico profesional', 'insurance', 0, 'document');
    
  END IF;
END$$

DELIMITER ;

-- =================================================================
-- 6. TRIGGERS - Automatización de Cálculos
-- =================================================================

-- Trigger para calcular márgenes automáticamente al insertar/actualizar paquetes
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `trg_travel_packages_calc_margins_insert`
BEFORE INSERT ON `travel_packages`
FOR EACH ROW
BEGIN
  DECLARE v_total_costs DECIMAL(10,2);
  
  -- Calcular costos totales
  SET v_total_costs = 
    IFNULL(NEW.flight_cost, 0) + 
    IFNULL(NEW.hotel_total_cost, 0) + 
    IFNULL(NEW.transport_cost, 0) + 
    IFNULL(NEW.meals_cost, 0) + 
    IFNULL(NEW.medical_service_cost, 0) + 
    IFNULL(NEW.additional_services_cost, 0);
  
  -- Calcular fee de MedTravel
  IF NEW.medtravel_fee_type = 'fixed' THEN
    SET NEW.medtravel_fee_amount = IFNULL(NEW.medtravel_fee_value, 0);
  ELSE
    SET NEW.medtravel_fee_amount = (IFNULL(NEW.total_package_cost, 0) * IFNULL(NEW.medtravel_fee_value, 0)) / 100;
  END IF;
  
  -- Calcular margen bruto
  SET NEW.gross_margin = IFNULL(NEW.total_package_cost, 0) - v_total_costs;
  
  -- Calcular margen neto (después de comisión al proveedor)
  SET NEW.net_margin = NEW.gross_margin - IFNULL(NEW.provider_commission_value, 0);
END$$

CREATE TRIGGER IF NOT EXISTS `trg_travel_packages_calc_margins_update`
BEFORE UPDATE ON `travel_packages`
FOR EACH ROW
BEGIN
  DECLARE v_total_costs DECIMAL(10,2);
  
  -- Calcular costos totales
  SET v_total_costs = 
    IFNULL(NEW.flight_cost, 0) + 
    IFNULL(NEW.hotel_total_cost, 0) + 
    IFNULL(NEW.transport_cost, 0) + 
    IFNULL(NEW.meals_cost, 0) + 
    IFNULL(NEW.medical_service_cost, 0) + 
    IFNULL(NEW.additional_services_cost, 0);
  
  -- Calcular fee de MedTravel
  IF NEW.medtravel_fee_type = 'fixed' THEN
    SET NEW.medtravel_fee_amount = IFNULL(NEW.medtravel_fee_value, 0);
  ELSE
    SET NEW.medtravel_fee_amount = (IFNULL(NEW.total_package_cost, 0) * IFNULL(NEW.medtravel_fee_value, 0)) / 100;
  END IF;
  
  -- Calcular margen bruto
  SET NEW.gross_margin = IFNULL(NEW.total_package_cost, 0) - v_total_costs;
  
  -- Calcular margen neto
  SET NEW.net_margin = NEW.gross_margin - IFNULL(NEW.provider_commission_value, 0);
END$$

DELIMITER ;

-- =================================================================
-- 7. VISTAS - Reportes Comerciales
-- =================================================================

-- Vista de márgenes por paquete
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

-- Vista de conversión por campaña
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

-- Vista de proveedores verificados
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
  ROUND((SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) / COUNT(pvi.id)) * 100, 2) AS completion_percent
FROM providers p
LEFT JOIN provider_verification pv ON p.id = pv.provider_id
LEFT JOIN provider_verification_items pvi ON p.id = pvi.provider_id
GROUP BY p.id, p.name, p.email, p.phone, pv.status, pv.verification_level, pv.trust_score, pv.verified_at, pv.expires_at;

-- =================================================================
-- 8. DATOS INICIALES - Configuración de Zonas Horarias
-- =================================================================

-- Actualizar proveedores existentes con timezone por defecto (Colombia)
UPDATE `providers` 
SET `provider_timezone` = 'America/Bogota' 
WHERE `provider_timezone` IS NULL OR `provider_timezone` = '';

-- Actualizar clientes existentes con timezone por defecto (USA East)
UPDATE `clientes` 
SET `client_timezone` = 'America/New_York' 
WHERE `client_timezone` IS NULL OR `client_timezone` = '';

-- =================================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- =================================================================

-- Índice compuesto para reportes de conversión
ALTER TABLE `clientes` ADD INDEX IF NOT EXISTS `idx_utm_status_date` (`utm_source`, `status`, `created_at`);

-- Índice para búsquedas de verificación
ALTER TABLE `provider_verification` ADD INDEX IF NOT EXISTS `idx_status_score` (`status`, `trust_score`);

-- Índice para documentos pendientes de verificación
ALTER TABLE `provider_documents` ADD INDEX IF NOT EXISTS `idx_verified_expiration` (`is_verified`, `expiration_date`);

-- =================================================================
-- COMENTARIOS Y DECISIONES DE DISEÑO
-- =================================================================

/*
DECISIONES CLAVE:

1. MODELO DE NEGOCIO (Monetización):
   - total_package_cost = precio FINAL al cliente (incluye la ganancia de MedTravel)
   - Los costos individuales (flight, hotel, etc.) son lo que MedTravel PAGA
   - gross_margin = lo que MedTravel gana ANTES de pagar comisión al proveedor
   - net_margin = ganancia NETA después de comisión al proveedor
   - medtravel_fee_amount es INFORMATIVO (ya está incluido en el total)
   - Triggers automáticos recalculan al insertar/actualizar

2. VERIFICACIÓN DE PROVEEDORES:
   - Sistema de 3 tablas: verification (status general), items (checklist), documents (evidencia)
   - Stored Procedure crea checklist automático al primer acceso
   - trust_score se calcula como % de items verificados
   - Badges: pending/in_review/verified/rejected/suspended

3. UTM TRACKING:
   - 5 campos UTM estándar + referred_by + landing/conversion pages
   - Permite medir ROI por canal/campaña
   - Vista v_campaign_performance para dashboards

4. TIMEZONES:
   - Almacenamiento en UTC (appointment_datetime_utc)
   - Se guarda TZ del cliente y proveedor al crear appointment
   - UI debe mostrar AMBAS horas con su TZ
   - Google Calendar recibe hora en TZ del proveedor
   - Campo antiguo appointment_datetime se mantiene por compatibilidad

5. COMPATIBILIDAD:
   - Todas las columnas nuevas son NULL o DEFAULT
   - No se eliminan ni renombran campos existentes
   - Triggers no afectan operaciones existentes
   - Views son opcionales (solo para reportes)
*/

-- =================================================================
-- FIN DEL SCRIPT - MEJORAS COMERCIALES
-- =================================================================

COMMIT;
