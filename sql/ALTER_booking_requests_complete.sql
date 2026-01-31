-- Completar la estructura de booking_requests con los campos faltantes

-- Agregar campo selected_offers (si da error, ya existe)
ALTER TABLE `booking_requests` 
ADD COLUMN `selected_offers` TEXT DEFAULT NULL COMMENT 'JSON array de IDs de provider_service_offers seleccionadas'
AFTER `special_request`;

-- Agregar campo status (si da error, ya existe)
ALTER TABLE `booking_requests` 
ADD COLUMN `status` VARCHAR(50) DEFAULT 'pending' COMMENT 'Estado: pending, contacted, confirmed, cancelled'
AFTER `additional_notes`;

-- Verificar estructura final
DESCRIBE booking_requests;
