-- ============================================================
-- SCRIPT CONSOLIDADO: SISTEMA DE PRECIOS COP Y TRAZABILIDAD
-- EJECUCIÓN: Ejecutar este script una sola vez
-- FECHA: 2026-01-31
-- ============================================================

-- ============================================================
-- 1. TABLA DE TASAS DE CAMBIO (Trazabilidad)
-- ============================================================

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `from_currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
  `to_currency` VARCHAR(3) NOT NULL DEFAULT 'COP',
  `rate` DECIMAL(10,2) NOT NULL COMMENT '1 USD = X COP',
  `effective_date` DATE NOT NULL,
  `source` VARCHAR(100) NULL COMMENT 'TRM, manual, API',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_currencies` (`from_currency`, `to_currency`),
  INDEX `idx_effective_date` (`effective_date`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tasa inicial
INSERT INTO `exchange_rates` 
  (`from_currency`, `to_currency`, `rate`, `effective_date`, `source`, `is_active`, `notes`) 
VALUES 
  ('USD', 'COP', 4150.00, CURDATE(), 'TRM', 1, 'Tasa inicial del sistema')
ON DUPLICATE KEY UPDATE rate=rate;

-- ============================================================
-- 2. AGREGAR CAMPOS COP A MEDTRAVEL_SERVICES_CATALOG
-- ============================================================

-- Agregar exchange_rate (ignorar si ya existe)
SET @dbname = DATABASE();
SET @tablename = 'medtravel_services_catalog';
SET @columnname = 'exchange_rate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` DECIMAL(10,2) DEFAULT 4150.00 COMMENT ''1 USD = X COP'' AFTER `currency`;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar cost_price_cop (ignorar si ya existe)
SET @columnname = 'cost_price_cop';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` DECIMAL(12,2) DEFAULT 0.00 COMMENT ''Costo en pesos colombianos'' AFTER `exchange_rate`;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================
-- 3. TRIGGERS PARA CÁLCULO AUTOMÁTICO
-- ============================================================

DROP TRIGGER IF EXISTS `calculate_commission_before_insert`;
DROP TRIGGER IF EXISTS `calculate_commission_before_update`;
DROP TRIGGER IF EXISTS `calculate_pricing_before_insert`;
DROP TRIGGER IF EXISTS `calculate_pricing_before_update`;

DELIMITER //

CREATE TRIGGER `calculate_pricing_before_insert`
BEFORE INSERT ON `medtravel_services_catalog`
FOR EACH ROW
BEGIN
    -- Calcular cost_price USD desde COP
    IF NEW.cost_price_cop > 0 AND NEW.exchange_rate > 0 THEN
        SET NEW.cost_price = NEW.cost_price_cop / NEW.exchange_rate;
    END IF;
    
    -- Calcular comisión
    SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
    
    -- Calcular porcentaje
    IF NEW.sale_price > 0 THEN
        SET NEW.commission_percentage = (NEW.commission_amount / NEW.sale_price) * 100;
    ELSE
        SET NEW.commission_percentage = 0;
    END IF;
END//

CREATE TRIGGER `calculate_pricing_before_update`
BEFORE UPDATE ON `medtravel_services_catalog`
FOR EACH ROW
BEGIN
    -- Calcular cost_price USD desde COP
    IF NEW.cost_price_cop > 0 AND NEW.exchange_rate > 0 THEN
        SET NEW.cost_price = NEW.cost_price_cop / NEW.exchange_rate;
    END IF;
    
    -- Calcular comisión
    SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
    
    -- Calcular porcentaje
    IF NEW.sale_price > 0 THEN
        SET NEW.commission_percentage = (NEW.commission_amount / NEW.sale_price) * 100;
    ELSE
        SET NEW.commission_percentage = 0;
    END IF;
END//

DELIMITER ;

-- ============================================================
-- 4. VISTA PARA TASA VIGENTE
-- ============================================================

CREATE OR REPLACE VIEW `v_current_exchange_rate` AS
SELECT id, from_currency, to_currency, rate, effective_date, source, notes
FROM exchange_rates
WHERE is_active = 1 
  AND from_currency = 'USD' 
  AND to_currency = 'COP'
ORDER BY effective_date DESC
LIMIT 1;

-- ============================================================
-- 5. MIGRAR DATOS EXISTENTES
-- ============================================================

-- Calcular cost_price_cop desde cost_price para servicios existentes
UPDATE `medtravel_services_catalog`
SET 
    `cost_price_cop` = `cost_price` * `exchange_rate`
WHERE `cost_price_cop` = 0 AND `cost_price` > 0;

-- ============================================================
-- 6. VERIFICACIÓN
-- ============================================================

SELECT '✅ Sistema de precios COP instalado correctamente' AS status;

SELECT 'Tabla exchange_rates:' AS '';
SELECT COUNT(*) AS total_rates, 
       MAX(effective_date) AS ultima_fecha,
       (SELECT rate FROM v_current_exchange_rate) AS tasa_vigente
FROM exchange_rates;

SELECT 'Campos agregados a medtravel_services_catalog:' AS '';
DESCRIBE `medtravel_services_catalog`;

SELECT 'Triggers creados:' AS '';
SHOW TRIGGERS LIKE 'medtravel_services_catalog';

SELECT 'Servicios con precios:' AS '';
SELECT 
    COUNT(*) AS total_servicios,
    COUNT(CASE WHEN cost_price_cop > 0 THEN 1 END) AS con_precio_cop,
    COUNT(CASE WHEN sale_price > 0 THEN 1 END) AS con_precio_venta
FROM `medtravel_services_catalog`;
