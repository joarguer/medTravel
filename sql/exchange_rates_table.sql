-- ============================================================
-- TABLA: exchange_rates
-- PROPÓSITO: Historial de tasas de cambio para trazabilidad
-- ============================================================

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `from_currency` VARCHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'Moneda origen',
  `to_currency` VARCHAR(3) NOT NULL DEFAULT 'COP' COMMENT 'Moneda destino',
  `rate` DECIMAL(10,2) NOT NULL COMMENT 'Tasa de cambio: 1 USD = X COP',
  `effective_date` DATE NOT NULL COMMENT 'Fecha efectiva de la tasa',
  `source` VARCHAR(100) NULL COMMENT 'Fuente de la tasa (TRM, manual, API)',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Activa, 0=Histórica',
  `created_by` INT(11) NULL COMMENT 'Usuario que registró la tasa',
  `notes` TEXT NULL COMMENT 'Notas adicionales',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_currencies` (`from_currency`, `to_currency`),
  INDEX `idx_effective_date` (`effective_date`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERTAR TASA ACTUAL (TRM Colombia aproximada)
-- ============================================================

INSERT INTO `exchange_rates` 
  (`from_currency`, `to_currency`, `rate`, `effective_date`, `source`, `is_active`, `notes`) 
VALUES 
  ('USD', 'COP', 4150.00, CURDATE(), 'TRM', 1, 'Tasa inicial del sistema');

-- ============================================================
-- VISTA: Obtener tasa de cambio vigente
-- ============================================================

CREATE OR REPLACE VIEW `v_current_exchange_rate` AS
SELECT 
  id,
  from_currency,
  to_currency,
  rate,
  effective_date,
  source,
  notes
FROM exchange_rates
WHERE is_active = 1 
  AND from_currency = 'USD' 
  AND to_currency = 'COP'
ORDER BY effective_date DESC
LIMIT 1;

-- ============================================================
-- STORED PROCEDURE: Obtener tasa vigente
-- ============================================================

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_get_current_rate`//

CREATE PROCEDURE `sp_get_current_rate`(
  IN p_from_currency VARCHAR(3),
  IN p_to_currency VARCHAR(3)
)
BEGIN
  SELECT rate
  FROM exchange_rates
  WHERE from_currency = p_from_currency
    AND to_currency = p_to_currency
    AND is_active = 1
  ORDER BY effective_date DESC
  LIMIT 1;
END//

DELIMITER ;

-- ============================================================
-- VERIFICACIÓN
-- ============================================================

SELECT 'Exchange rates table created successfully' AS status;
SELECT * FROM v_current_exchange_rate;
CALL sp_get_current_rate('USD', 'COP');
