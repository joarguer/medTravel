-- ============================================================
-- TABLA: medtravel_services_catalog
-- PROPÓSITO: Catálogo de servicios que MedTravel ofrece
-- (Vuelos, Hoteles, Transporte, Comidas, Soporte)
-- ============================================================

CREATE TABLE IF NOT EXISTS `medtravel_services_catalog` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- CLASIFICACIÓN
  `service_type` ENUM('flight','accommodation','transport','meals','support','other') NOT NULL COMMENT 'Tipo de servicio',
  `service_name` VARCHAR(255) NOT NULL COMMENT 'Nombre del servicio',
  `service_code` VARCHAR(50) NULL COMMENT 'Código interno (ej: FLT-BOG-MIA)',
  `description` TEXT NULL COMMENT 'Descripción detallada',
  `short_description` VARCHAR(255) NULL COMMENT 'Descripción corta',
  
  -- PROVEEDOR/PARTNER
  `provider_name` VARCHAR(255) NULL COMMENT 'Nombre del proveedor (aerolínea, hotel, etc)',
  `provider_contact` VARCHAR(255) NULL COMMENT 'Contacto del proveedor',
  `provider_email` VARCHAR(255) NULL COMMENT 'Email del proveedor',
  `provider_phone` VARCHAR(50) NULL COMMENT 'Teléfono del proveedor',
  `provider_notes` TEXT NULL COMMENT 'Notas sobre el proveedor',
  
  -- COSTOS Y PRECIOS
  `cost_price` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Costo que paga MedTravel al proveedor',
  `sale_price` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Precio de venta al cliente',
  `currency` VARCHAR(3) DEFAULT 'USD' COMMENT 'Moneda (USD, COP, EUR)',
  `commission_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Comisión calculada automáticamente',
  `commission_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Porcentaje de comisión',
  
  -- DETALLES ESPECÍFICOS POR TIPO (JSON)
  `service_details` JSON NULL COMMENT 'Detalles específicos según tipo de servicio',
  -- Ejemplos de service_details:
  -- FLIGHT: {"route": "BOG-MIA", "airline": "Avianca", "class": "economy", "baggage_included": true}
  -- ACCOMMODATION: {"hotel_name": "Hotel XYZ", "city": "Armenia", "stars": 4, "room_type": "double", "nights_min": 1}
  -- TRANSPORT: {"vehicle_type": "sedan", "capacity": 4, "routes": ["Airport-Hotel", "Hotel-Clinic"]}
  -- MEALS: {"meal_plan": "breakfast_only", "dietary_options": ["vegetarian", "gluten_free"]}
  -- SUPPORT: {"coverage_hours": "24/7", "languages": ["en", "es"], "response_time": "immediate"}
  
  -- DISPONIBILIDAD
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `availability_status` ENUM('available','limited','unavailable','seasonal') DEFAULT 'available',
  `stock_quantity` INT(11) NULL COMMENT 'Cantidad disponible (NULL = ilimitado)',
  `booking_lead_time` INT(11) DEFAULT 0 COMMENT 'Días de anticipación requeridos',
  
  -- VISUALIZACIÓN
  `icon_class` VARCHAR(100) NULL COMMENT 'Clase de ícono (Font Awesome)',
  `image_url` VARCHAR(255) NULL COMMENT 'URL de imagen representativa',
  `display_order` INT(11) DEFAULT 0 COMMENT 'Orden de visualización',
  `featured` TINYINT(1) DEFAULT 0 COMMENT '1=Destacado, 0=Normal',
  
  -- METADATA
  `tags` VARCHAR(255) NULL COMMENT 'Etiquetas separadas por comas',
  `internal_notes` TEXT NULL COMMENT 'Notas internas para staff',
  
  -- AUDITORÍA
  `created_by` INT(11) NULL COMMENT 'ID del usuario que creó',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_service_type` (`service_type`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_availability` (`availability_status`),
  INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGER: Calcular comisión automáticamente
-- ============================================================
DELIMITER //

CREATE TRIGGER `calculate_commission_before_insert` 
BEFORE INSERT ON `medtravel_services_catalog`
FOR EACH ROW
BEGIN
    IF NEW.sale_price > 0 AND NEW.cost_price >= 0 THEN
        SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
        IF NEW.sale_price > 0 THEN
            SET NEW.commission_percentage = (NEW.commission_amount / NEW.sale_price) * 100;
        END IF;
    END IF;
END//

CREATE TRIGGER `calculate_commission_before_update` 
BEFORE UPDATE ON `medtravel_services_catalog`
FOR EACH ROW
BEGIN
    IF NEW.sale_price > 0 AND NEW.cost_price >= 0 THEN
        SET NEW.commission_amount = NEW.sale_price - NEW.cost_price;
        IF NEW.sale_price > 0 THEN
            SET NEW.commission_percentage = (NEW.commission_amount / NEW.sale_price) * 100;
        END IF;
    END IF;
END//

DELIMITER ;

-- ============================================================
-- DATOS DE EJEMPLO
-- ============================================================

INSERT INTO `medtravel_services_catalog` 
(`service_type`, `service_name`, `service_code`, `description`, `short_description`, 
 `provider_name`, `cost_price`, `sale_price`, `currency`, `service_details`, 
 `icon_class`, `display_order`, `is_active`) 
VALUES
-- FLIGHTS
('flight', 'Round Trip Flight - Miami to Armenia', 'FLT-MIA-AXM', 
 'Round trip flight from Miami (MIA) to Armenia (AXM) via Bogotá. Economy class with 1 checked bag included.', 
 'Miami - Armenia round trip',
 'Avianca', 650.00, 850.00, 'USD', 
 '{"route": "MIA-BOG-AXM", "airline": "Avianca", "class": "economy", "baggage_included": true, "stops": 1}',
 'fa fa-plane', 1, 1),

('flight', 'Round Trip Flight - Los Angeles to Bogotá', 'FLT-LAX-BOG', 
 'Direct round trip flight from Los Angeles (LAX) to Bogotá (BOG). Economy class.', 
 'Los Angeles - Bogotá round trip',
 'Avianca', 500.00, 700.00, 'USD', 
 '{"route": "LAX-BOG", "airline": "Avianca", "class": "economy", "baggage_included": true, "stops": 0}',
 'fa fa-plane', 2, 1),

-- ACCOMMODATIONS
('accommodation', 'Hotel Campestre - Standard Room', 'HTL-CAMP-STD', 
 'Comfortable standard room in hotel near medical facilities. Includes breakfast and WiFi.', 
 'Standard room with breakfast',
 'Hotel Campestre Quindío', 45.00, 75.00, 'USD', 
 '{"hotel_name": "Hotel Campestre", "city": "Armenia", "stars": 3, "room_type": "standard", "breakfast_included": true, "wifi": true}',
 'fa fa-hotel', 10, 1),

('accommodation', 'Hotel Boutique - Premium Suite', 'HTL-BOUT-PREM', 
 'Premium suite with recovery-friendly amenities. Room service, medical bed, 24h support.', 
 'Premium suite for recovery',
 'Hotel Boutique Armenia', 80.00, 140.00, 'USD', 
 '{"hotel_name": "Hotel Boutique", "city": "Armenia", "stars": 4, "room_type": "suite", "breakfast_included": true, "medical_bed": true, "room_service": true}',
 'fa fa-hotel', 11, 1),

-- TRANSPORT
('transport', 'Airport Transfer - Private Sedan', 'TRS-APT-SED', 
 'Private sedan transfer from Armenia airport to hotel/clinic. Professional bilingual driver.', 
 'Airport pickup - private sedan',
 'Transport Colombia SAS', 25.00, 45.00, 'USD', 
 '{"vehicle_type": "sedan", "capacity": 3, "routes": ["AXM Airport - Any location"], "bilingual_driver": true}',
 'fa fa-car', 20, 1),

('transport', 'Daily Transport Package - 7 days', 'TRS-DAILY-7D', 
 'Full week of daily transport: hotel-clinic-hotel. Van with medical assistance support.', 
 '7-day transport package',
 'MedCare Transport', 150.00, 250.00, 'USD', 
 '{"vehicle_type": "van", "capacity": 6, "days": 7, "routes": ["Hotel-Clinic daily"], "medical_support": true}',
 'fa fa-car', 21, 1),

-- MEALS
('meals', 'Meal Plan - Breakfast Only (7 days)', 'MEAL-BRK-7D', 
 'Continental breakfast for 7 days. Vegetarian and gluten-free options available.', 
 '7 days breakfast included',
 'Hotel Restaurant', 35.00, 60.00, 'USD', 
 '{"meal_plan": "breakfast_only", "days": 7, "dietary_options": ["standard", "vegetarian", "gluten_free"]}',
 'fa fa-cutlery', 30, 1),

('meals', 'Full Meal Plan (7 days)', 'MEAL-FULL-7D', 
 'Full board: breakfast, lunch and dinner for 7 days. Post-surgery diet adapted.', 
 'All meals - 7 days',
 'NutriRecovery Catering', 120.00, 200.00, 'USD', 
 '{"meal_plan": "full_board", "days": 7, "post_surgery_adapted": true, "dietary_options": ["standard", "vegetarian", "low_sodium", "liquid"]}',
 'fa fa-cutlery', 31, 1),

-- SUPPORT
('support', 'Basic Support Package', 'SUP-BASIC', 
 'Bilingual support during business hours (8am-6pm). Email and phone assistance.', 
 'Business hours support',
 'MedTravel Staff', 0.00, 100.00, 'USD', 
 '{"coverage_hours": "8am-6pm", "languages": ["en", "es"], "channels": ["email", "phone"], "response_time": "2 hours"}',
 'fa fa-headphones', 40, 1),

('support', 'Premium 24/7 Support', 'SUP-PREMIUM', 
 'Full 24/7 support with dedicated coordinator. WhatsApp, phone, emergency assistance.', 
 '24/7 dedicated support',
 'MedTravel Staff', 50.00, 250.00, 'USD', 
 '{"coverage_hours": "24/7", "languages": ["en", "es"], "channels": ["whatsapp", "phone", "email"], "dedicated_coordinator": true, "emergency_assistance": true, "response_time": "immediate"}',
 'fa fa-headphones', 41, 1);

-- ============================================================
-- VERIFICACIÓN
-- ============================================================
SELECT 'Tabla medtravel_services_catalog creada exitosamente' AS status;
SELECT COUNT(*) AS total_services FROM medtravel_services_catalog;
