-- Agregar campo selected_offers para almacenar IDs de ofertas seleccionadas
-- Este campo almacena un JSON array de IDs de provider_service_offers

ALTER TABLE `booking_requests` 
ADD COLUMN `selected_offers` TEXT DEFAULT NULL COMMENT 'JSON array de IDs de provider_service_offers seleccionadas'
AFTER `special_request`;

-- Nota: Los campos service_categories y medical_services se mantienen por compatibilidad
-- pero selected_offers es el nuevo campo principal para el wizard mejorado
