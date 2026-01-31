-- ============================================================
-- INTEGRACIÓN: Travel Packages + MedTravel Services Catalog
-- PROPÓSITO: Permitir armar paquetes usando servicios del catálogo
-- ============================================================

-- Tabla pivot para relacionar paquetes con servicios
CREATE TABLE IF NOT EXISTS `package_services` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `package_id` INT(11) NOT NULL COMMENT 'ID del travel_package',
  `service_id` INT(11) NOT NULL COMMENT 'ID del medtravel_services_catalog',
  `quantity` INT(11) DEFAULT 1 COMMENT 'Cantidad (ej: 2 personas, 5 noches)',
  `unit_price` DECIMAL(10,2) NOT NULL COMMENT 'Precio unitario en el momento de agregar',
  `total_price` DECIMAL(10,2) NOT NULL COMMENT 'Precio total = quantity * unit_price',
  `notes` TEXT NULL COMMENT 'Notas específicas para este servicio en el paquete',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_package` (`package_id`),
  INDEX `idx_service` (`service_id`),
  FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `medtravel_services_catalog`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ALTER TABLE: travel_packages - Agregar campos de integración
-- ============================================================

-- Flag para indicar si el paquete usa servicios del catálogo
ALTER TABLE `travel_packages` 
ADD COLUMN `use_catalog_services` TINYINT(1) DEFAULT 0 COMMENT '1=Usa servicios del catálogo, 0=Manual' 
AFTER `total_package_cost`;

-- Total calculado desde servicios del catálogo
ALTER TABLE `travel_packages` 
ADD COLUMN `catalog_services_total` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total de servicios desde catálogo'
AFTER `use_catalog_services`;

-- ============================================================
-- TRIGGER: Recalcular total del paquete cuando se agregan/eliminan servicios
-- ============================================================

DELIMITER //

CREATE TRIGGER `update_package_total_after_service_insert`
AFTER INSERT ON `package_services`
FOR EACH ROW
BEGIN
    DECLARE catalog_total DECIMAL(10,2);
    
    -- Calcular total de servicios del catálogo
    SELECT COALESCE(SUM(total_price), 0) INTO catalog_total
    FROM package_services
    WHERE package_id = NEW.package_id;
    
    -- Actualizar travel_packages
    UPDATE travel_packages
    SET catalog_services_total = catalog_total,
        total_package_cost = catalog_total + 
            COALESCE(medical_service_cost, 0) + 
            COALESCE(additional_services_cost, 0)
    WHERE id = NEW.package_id;
END//

CREATE TRIGGER `update_package_total_after_service_delete`
AFTER DELETE ON `package_services`
FOR EACH ROW
BEGIN
    DECLARE catalog_total DECIMAL(10,2);
    
    -- Calcular total de servicios del catálogo
    SELECT COALESCE(SUM(total_price), 0) INTO catalog_total
    FROM package_services
    WHERE package_id = OLD.package_id;
    
    -- Actualizar travel_packages
    UPDATE travel_packages
    SET catalog_services_total = catalog_total,
        total_package_cost = catalog_total + 
            COALESCE(medical_service_cost, 0) + 
            COALESCE(additional_services_cost, 0)
    WHERE id = OLD.package_id;
END//

CREATE TRIGGER `update_package_total_after_service_update`
AFTER UPDATE ON `package_services`
FOR EACH ROW
BEGIN
    DECLARE catalog_total DECIMAL(10,2);
    
    -- Calcular total de servicios del catálogo
    SELECT COALESCE(SUM(total_price), 0) INTO catalog_total
    FROM package_services
    WHERE package_id = NEW.package_id;
    
    -- Actualizar travel_packages
    UPDATE travel_packages
    SET catalog_services_total = catalog_total,
        total_package_cost = catalog_total + 
            COALESCE(medical_service_cost, 0) + 
            COALESCE(additional_services_cost, 0)
    WHERE id = NEW.package_id;
END//

DELIMITER ;

-- ============================================================
-- VISTA: Resumen de servicios por paquete
-- ============================================================

CREATE OR REPLACE VIEW `v_package_services_summary` AS
SELECT 
    ps.package_id,
    tp.package_name,
    tp.client_id,
    COUNT(ps.id) AS total_services,
    SUM(CASE WHEN msc.service_type = 'flight' THEN 1 ELSE 0 END) AS flights_count,
    SUM(CASE WHEN msc.service_type = 'accommodation' THEN 1 ELSE 0 END) AS accommodations_count,
    SUM(CASE WHEN msc.service_type = 'transport' THEN 1 ELSE 0 END) AS transport_count,
    SUM(CASE WHEN msc.service_type = 'meals' THEN 1 ELSE 0 END) AS meals_count,
    SUM(CASE WHEN msc.service_type = 'support' THEN 1 ELSE 0 END) AS support_count,
    SUM(ps.total_price) AS total_cost,
    tp.currency
FROM package_services ps
INNER JOIN travel_packages tp ON ps.package_id = tp.id
INNER JOIN medtravel_services_catalog msc ON ps.service_id = msc.id
GROUP BY ps.package_id;

-- ============================================================
-- DATOS DE EJEMPLO (OPCIONAL)
-- ============================================================

-- Ejemplo: Si existe un paquete con ID 1 y servicios en el catálogo
-- Descomentar las siguientes líneas si quieres datos de prueba:

/*
-- Asumiendo que existe travel_packages.id = 1 y servicios del catálogo
INSERT INTO package_services (package_id, service_id, quantity, unit_price, total_price, notes) 
VALUES 
(1, 1, 1, 850.00, 850.00, 'Round trip Miami-Armenia for patient'),
(1, 3, 7, 75.00, 525.00, '7 nights standard room'),
(1, 5, 1, 45.00, 45.00, 'Airport pickup on arrival');
*/

-- ============================================================
-- VERIFICACIÓN
-- ============================================================

SELECT 'Integration tables created successfully' AS status;
SELECT COUNT(*) AS package_services_count FROM package_services;
DESCRIBE package_services;
SHOW TRIGGERS LIKE 'package_services';
