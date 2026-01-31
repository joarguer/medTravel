-- ============================================================
-- ACTUALIZACIÓN: Agregar campos para precios en COP
-- PROPÓSITO: Manejar costos en pesos colombianos y conversión a USD
-- FECHA: 2026-01-31
-- ============================================================

-- Agregar campo para tasa de cambio
ALTER TABLE `medtravel_services_catalog` 
ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT 4150.00 COMMENT 'Tasa de cambio: 1 USD = X COP' 
AFTER `currency`;

-- Agregar campo para costo en pesos colombianos
ALTER TABLE `medtravel_services_catalog` 
ADD COLUMN `cost_price_cop` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Costo en pesos colombianos (COP)' 
AFTER `exchange_rate`;

-- Modificar trigger para calcular cost_price desde cost_price_cop
DROP TRIGGER IF EXISTS `calculate_commission_before_insert`;
DROP TRIGGER IF EXISTS `calculate_commission_before_update`;

DELIMITER //

CREATE TRIGGER `calculate_pricing_before_insert`
BEFORE INSERT ON `medtravel_services_catalog`
FOR EACH ROW
BEGIN
    -- Si hay cost_price_cop y exchange_rate, calcular cost_price en USD
    IF NEW.cost_price_cop > 0 AND NEW.exchange_rate > 0 THEN
        SET NEW.cost_price = NEW.cost_price_cop / NEW.exchange_rate;
    END IF;
    
    -- Calcular comisión
    SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
    
    -- Calcular porcentaje de comisión
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
    -- Si hay cost_price_cop y exchange_rate, calcular cost_price en USD
    IF NEW.cost_price_cop > 0 AND NEW.exchange_rate > 0 THEN
        SET NEW.cost_price = NEW.cost_price_cop / NEW.exchange_rate;
    END IF;
    
    -- Calcular comisión
    SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
    
    -- Calcular porcentaje de comisión
    IF NEW.sale_price > 0 THEN
        SET NEW.commission_percentage = (NEW.commission_amount / NEW.sale_price) * 100;
    ELSE
        SET NEW.commission_percentage = 0;
    END IF;
END//

DELIMITER ;

-- ============================================================
-- ACTUALIZAR SERVICIOS EXISTENTES
-- ============================================================

-- Para servicios existentes, calcular cost_price_cop desde cost_price
-- usando la tasa de cambio por defecto
UPDATE `medtravel_services_catalog`
SET 
    `cost_price_cop` = `cost_price` * `exchange_rate`,
    `exchange_rate` = 4150.00
WHERE `cost_price_cop` = 0 AND `cost_price` > 0;

-- ============================================================
-- VERIFICACIÓN
-- ============================================================

SELECT 'COP pricing fields added successfully' AS status;
DESCRIBE `medtravel_services_catalog`;
SHOW TRIGGERS LIKE 'medtravel_services_catalog';

-- Verificar datos actualizados
SELECT 
    id, 
    service_name, 
    cost_price_cop, 
    exchange_rate, 
    cost_price, 
    sale_price, 
    commission_amount,
    currency
FROM `medtravel_services_catalog`
LIMIT 5;
