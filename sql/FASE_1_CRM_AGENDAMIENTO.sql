-- =================================================================
-- MEDTRAVEL - FASE 1: CRM Y AGENDAMIENTO
-- Script de instalación para nuevas tablas
-- Fecha: 29 de enero de 2026
-- =================================================================

-- IMPORTANTE: Este script es seguro para ejecutar en producción
-- Usa IF NOT EXISTS y no sobrescribe datos existentes

-- =================================================================
-- 1. TABLA: clientes (CRM de Pacientes)
-- =================================================================
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- Información personal
  `nombre` VARCHAR(200) NOT NULL,
  `apellido` VARCHAR(200) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `telefono` VARCHAR(50) DEFAULT NULL,
  `whatsapp` VARCHAR(50) DEFAULT NULL,
  `fecha_nacimiento` DATE DEFAULT NULL,
  
  -- Ubicación
  `pais` VARCHAR(100) DEFAULT 'USA',
  `estado` VARCHAR(100) DEFAULT NULL COMMENT 'Estado/Provincia',
  `ciudad` VARCHAR(200) DEFAULT NULL,
  `direccion` TEXT DEFAULT NULL,
  `codigo_postal` VARCHAR(20) DEFAULT NULL,
  
  -- Documentación
  `numero_pasaporte` VARCHAR(100) DEFAULT NULL,
  `tipo_documento` ENUM('passport','license','id','other') DEFAULT 'passport',
  
  -- Información médica básica
  `condiciones_medicas` TEXT DEFAULT NULL COMMENT 'Enfermedades preexistentes',
  `alergias` TEXT DEFAULT NULL,
  `medicamentos_actuales` TEXT DEFAULT NULL,
  
  -- Contacto de emergencia
  `contacto_emergencia_nombre` VARCHAR(200) DEFAULT NULL,
  `contacto_emergencia_telefono` VARCHAR(50) DEFAULT NULL,
  `contacto_emergencia_relacion` VARCHAR(100) DEFAULT NULL,
  
  -- Estado del cliente en el proceso
  `status` ENUM('lead','cotizado','confirmado','en_viaje','post_tratamiento','finalizado','inactivo') DEFAULT 'lead',
  `origen_contacto` ENUM('web','whatsapp','telefono','email','referido','redes_sociales','otro') DEFAULT 'web',
  
  -- Idioma preferido
  `idioma_preferido` ENUM('es','en','both') DEFAULT 'en',
  
  -- Notas internas
  `notas` TEXT DEFAULT NULL,
  
  -- Auditoría
  `created_by` INT(11) DEFAULT NULL COMMENT 'Usuario que creó el registro',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_unique` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_pais` (`pais`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de clientes/pacientes del sistema';

-- =================================================================
-- 2. TABLA: appointments (Citas Médicas + Google Calendar)
-- =================================================================
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- Relaciones principales
  `client_id` INT(11) NOT NULL,
  `provider_id` INT(11) NOT NULL,
  `service_id` INT(11) DEFAULT NULL COMMENT 'FK a provider_service_offers',
  
  -- Fecha y hora de la cita
  `appointment_datetime` DATETIME NOT NULL,
  `duration_minutes` INT(11) DEFAULT 60,
  `timezone` VARCHAR(50) DEFAULT 'America/Bogota',
  
  -- Integración con Google Calendar
  `google_event_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID del evento en Google Calendar',
  `google_calendar_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID del calendario del proveedor',
  `sync_status` ENUM('pending','synced','error') DEFAULT 'pending',
  `last_sync_at` DATETIME DEFAULT NULL,
  `sync_error_message` TEXT DEFAULT NULL,
  
  -- Estado de la cita
  `status` ENUM('pending','confirmed','in_progress','completed','cancelled','no_show','rescheduled') DEFAULT 'pending',
  `cancellation_reason` TEXT DEFAULT NULL,
  `cancelled_by` INT(11) DEFAULT NULL COMMENT 'Usuario que canceló',
  `cancelled_at` DATETIME DEFAULT NULL,
  
  -- Tipo y detalles de la cita
  `appointment_type` ENUM('consultation','procedure','follow_up','lab','diagnostic','other') DEFAULT 'consultation',
  `location` VARCHAR(255) DEFAULT NULL COMMENT 'Dirección física de la clínica',
  `virtual_meeting_link` VARCHAR(500) DEFAULT NULL COMMENT 'Link de videollamada si es virtual',
  `notes` TEXT DEFAULT NULL COMMENT 'Notas generales de la cita',
  `preparation_instructions` TEXT DEFAULT NULL COMMENT 'Instrucciones pre-cita para el paciente',
  
  -- Sistema de notificaciones
  `reminder_sent` TINYINT(1) DEFAULT 0,
  `reminder_sent_at` DATETIME DEFAULT NULL,
  `confirmation_sent` TINYINT(1) DEFAULT 0,
  `confirmation_sent_at` DATETIME DEFAULT NULL,
  
  -- Resultados post-cita
  `result_notes` TEXT DEFAULT NULL COMMENT 'Notas del médico post-cita',
  `next_appointment_recommended` TINYINT(1) DEFAULT 0,
  
  -- Auditoría
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_appointment_date` (`appointment_datetime`),
  KEY `idx_status` (`status`),
  KEY `idx_google_event` (`google_event_id`),
  
  CONSTRAINT `fk_appointments_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appointments_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de citas médicas con sincronización Google Calendar';

-- =================================================================
-- 3. TABLA: travel_packages (Paquetes Todo Incluido)
-- =================================================================
CREATE TABLE IF NOT EXISTS `travel_packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- Relaciones
  `client_id` INT(11) NOT NULL,
  `appointment_id` INT(11) DEFAULT NULL COMMENT 'Cita médica principal asociada',
  
  -- Información general del paquete
  `package_name` VARCHAR(255) DEFAULT NULL COMMENT 'Nombre descriptivo del paquete',
  `start_date` DATE NOT NULL COMMENT 'Fecha de inicio del viaje',
  `end_date` DATE NOT NULL COMMENT 'Fecha de fin del viaje',
  `total_days` INT(11) GENERATED ALWAYS AS (DATEDIFF(`end_date`, `start_date`)) STORED,
  
  -- ========== VUELO ==========
  `flight_included` TINYINT(1) DEFAULT 0,
  `flight_airline` VARCHAR(100) DEFAULT NULL,
  `flight_departure_airport` VARCHAR(50) DEFAULT NULL COMMENT 'Código IATA (ej: MIA)',
  `flight_arrival_airport` VARCHAR(50) DEFAULT 'AXM' COMMENT 'Armenia, Quindío',
  `flight_departure_date` DATE DEFAULT NULL,
  `flight_departure_time` TIME DEFAULT NULL,
  `flight_arrival_date` DATE DEFAULT NULL,
  `flight_arrival_time` TIME DEFAULT NULL,
  `flight_return_date` DATE DEFAULT NULL,
  `flight_return_time` TIME DEFAULT NULL,
  `flight_booking_reference` VARCHAR(100) DEFAULT NULL,
  `flight_confirmation_number` VARCHAR(100) DEFAULT NULL,
  `flight_seat_number` VARCHAR(20) DEFAULT NULL,
  `flight_baggage_allowance` VARCHAR(100) DEFAULT NULL,
  `flight_cost` DECIMAL(10,2) DEFAULT 0.00,
  `flight_notes` TEXT DEFAULT NULL,
  
  -- ========== HOTEL ==========
  `hotel_included` TINYINT(1) DEFAULT 0,
  `hotel_name` VARCHAR(200) DEFAULT NULL,
  `hotel_address` TEXT DEFAULT NULL,
  `hotel_city` VARCHAR(100) DEFAULT 'Quindío',
  `hotel_phone` VARCHAR(50) DEFAULT NULL,
  `hotel_email` VARCHAR(200) DEFAULT NULL,
  `hotel_checkin_date` DATE DEFAULT NULL,
  `hotel_checkout_date` DATE DEFAULT NULL,
  `hotel_room_type` VARCHAR(100) DEFAULT NULL COMMENT 'Single, Double, Suite, etc.',
  `hotel_room_number` VARCHAR(20) DEFAULT NULL,
  `hotel_confirmation_number` VARCHAR(100) DEFAULT NULL,
  `hotel_nights` INT(11) DEFAULT NULL,
  `hotel_cost_per_night` DECIMAL(10,2) DEFAULT 0.00,
  `hotel_total_cost` DECIMAL(10,2) DEFAULT 0.00,
  `hotel_notes` TEXT DEFAULT NULL,
  
  -- ========== TRANSPORTE ==========
  `transport_included` TINYINT(1) DEFAULT 0,
  `transport_type` ENUM('taxi','rental_car','private_driver','van','shuttle','uber','other') DEFAULT 'private_driver',
  `transport_company` VARCHAR(200) DEFAULT NULL,
  `transport_driver_name` VARCHAR(200) DEFAULT NULL,
  `transport_driver_phone` VARCHAR(50) DEFAULT NULL,
  `transport_vehicle_info` VARCHAR(200) DEFAULT NULL,
  `transport_pickup_times` JSON DEFAULT NULL COMMENT 'Array de horarios de recogida',
  `transport_routes` TEXT DEFAULT NULL COMMENT 'Aeropuerto-Hotel, Hotel-Clínica, etc.',
  `transport_cost` DECIMAL(10,2) DEFAULT 0.00,
  `transport_notes` TEXT DEFAULT NULL,
  
  -- ========== ALIMENTACIÓN ==========
  `meals_included` TINYINT(1) DEFAULT 0,
  `meals_plan` ENUM('breakfast_only','half_board','full_board','all_inclusive','custom') DEFAULT 'breakfast_only',
  `dietary_restrictions` TEXT DEFAULT NULL COMMENT 'Vegetariano, vegano, sin gluten, etc.',
  `special_diet_requirements` TEXT DEFAULT NULL COMMENT 'Dietas médicas post-operatorias',
  `meals_cost` DECIMAL(10,2) DEFAULT 0.00,
  `meals_notes` TEXT DEFAULT NULL,
  
  -- ========== COSTOS Y PAGOS ==========
  `medical_service_cost` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Costo del procedimiento médico',
  `additional_services_cost` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Otros servicios adicionales',
  `total_package_cost` DECIMAL(10,2) NOT NULL COMMENT 'Costo total del paquete',
  `currency` VARCHAR(3) DEFAULT 'USD',
  
  -- Estado de pagos
  `payment_status` ENUM('pending','deposit_paid','partial_paid','fully_paid','refunded','cancelled') DEFAULT 'pending',
  `deposit_amount` DECIMAL(10,2) DEFAULT 0.00,
  `deposit_paid_date` DATE DEFAULT NULL,
  `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
  `balance_due` DECIMAL(10,2) GENERATED ALWAYS AS (`total_package_cost` - `amount_paid`) STORED,
  `payment_method` VARCHAR(100) DEFAULT NULL COMMENT 'Tarjeta, transferencia, etc.',
  `payment_reference` VARCHAR(200) DEFAULT NULL,
  `payment_notes` TEXT DEFAULT NULL,
  
  -- ========== ESTADO DEL PAQUETE ==========
  `status` ENUM('quoted','confirmed','in_progress','completed','cancelled','refunded') DEFAULT 'quoted',
  `cancellation_policy` TEXT DEFAULT NULL,
  
  -- ========== NOTAS Y DETALLES ==========
  `special_requests` TEXT DEFAULT NULL COMMENT 'Solicitudes especiales del cliente',
  `internal_notes` TEXT DEFAULT NULL COMMENT 'Notas internas del equipo',
  `terms_accepted` TINYINT(1) DEFAULT 0,
  `terms_accepted_date` DATETIME DEFAULT NULL,
  
  -- Auditoría
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_dates` (`start_date`, `end_date`),
  
  CONSTRAINT `fk_packages_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_packages_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paquetes todo incluido de turismo médico';

-- =================================================================
-- 4. TABLA: client_documents (Documentos del Cliente)
-- =================================================================
CREATE TABLE IF NOT EXISTS `client_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  
  -- Tipo y categoría del documento
  `document_type` ENUM('passport','id_card','medical_history','lab_results','prescription','invoice','contract','consent_form','insurance','photos','other') DEFAULT 'other',
  `document_category` VARCHAR(100) DEFAULT NULL COMMENT 'Categorización adicional',
  
  -- Información del archivo
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Ruta relativa del archivo',
  `filename` VARCHAR(255) NOT NULL COMMENT 'Nombre del archivo almacenado',
  `original_filename` VARCHAR(255) NOT NULL COMMENT 'Nombre original del archivo',
  `file_size` INT(11) DEFAULT NULL COMMENT 'Tamaño en bytes',
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_extension` VARCHAR(10) DEFAULT NULL,
  
  -- Metadata del documento
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `document_date` DATE DEFAULT NULL COMMENT 'Fecha del documento (no de subida)',
  `expiration_date` DATE DEFAULT NULL COMMENT 'Para documentos que expiran',
  `is_verified` TINYINT(1) DEFAULT 0 COMMENT 'Documento verificado por admin',
  `verified_by` INT(11) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  
  -- Control de acceso
  `is_confidential` TINYINT(1) DEFAULT 0,
  `shared_with_provider` TINYINT(1) DEFAULT 0,
  
  -- Auditoría
  `uploaded_by` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_uploaded_at` (`uploaded_at`),
  
  CONSTRAINT `fk_documents_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos de clientes';

-- =================================================================
-- 5. TABLA: notifications (Sistema de Notificaciones)
-- =================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- Destinatario
  `recipient_type` ENUM('client','provider','admin','system') NOT NULL,
  `recipient_id` INT(11) NOT NULL COMMENT 'ID del cliente, provider o usuario',
  `recipient_email` VARCHAR(200) DEFAULT NULL,
  `recipient_phone` VARCHAR(50) DEFAULT NULL,
  
  -- Tipo de notificación
  `notification_type` ENUM(
    'appointment_reminder',
    'appointment_confirmation',
    'appointment_cancelled',
    'payment_confirmation',
    'payment_reminder',
    'package_details',
    'travel_itinerary',
    'follow_up',
    'welcome',
    'general'
  ) NOT NULL,
  
  -- Canal de envío
  `channel` ENUM('email','whatsapp','sms','system','push') NOT NULL,
  
  -- Contenido
  `subject` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `html_content` TEXT DEFAULT NULL COMMENT 'Contenido HTML para emails',
  `template_used` VARCHAR(100) DEFAULT NULL,
  
  -- Estado de envío
  `status` ENUM('pending','queued','sent','delivered','failed','read','clicked') DEFAULT 'pending',
  `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
  `scheduled_at` DATETIME DEFAULT NULL COMMENT 'Para envíos programados',
  `sent_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `clicked_at` DATETIME DEFAULT NULL,
  `failed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `retry_count` INT(11) DEFAULT 0,
  
  -- Relacionado con
  `related_type` ENUM('appointment','package','client','payment','document','other') DEFAULT NULL,
  `related_id` INT(11) DEFAULT NULL,
  
  -- Tracking
  `external_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID del proveedor externo (SendGrid, Twilio, etc.)',
  `tracking_data` JSON DEFAULT NULL,
  
  -- Auditoría
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_type`, `recipient_id`),
  KEY `idx_status` (`status`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_channel` (`channel`),
  KEY `idx_scheduled` (`scheduled_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de notificaciones multi-canal';

-- =================================================================
-- 6. TABLA: google_calendar_config (Configuración de Calendarios)
-- =================================================================
CREATE TABLE IF NOT EXISTS `google_calendar_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_id` INT(11) NOT NULL,
  
  -- Credenciales de Google
  `calendar_id` VARCHAR(255) NOT NULL COMMENT 'ID del calendario de Google',
  `access_token` TEXT DEFAULT NULL COMMENT 'Token de acceso OAuth',
  `refresh_token` TEXT DEFAULT NULL COMMENT 'Token de refresco',
  `token_expires_at` DATETIME DEFAULT NULL,
  
  -- Configuración del calendario
  `calendar_name` VARCHAR(255) DEFAULT NULL,
  `calendar_timezone` VARCHAR(50) DEFAULT 'America/Bogota',
  `sync_enabled` TINYINT(1) DEFAULT 1,
  `sync_direction` ENUM('one_way','two_way') DEFAULT 'two_way',
  `auto_accept_events` TINYINT(1) DEFAULT 0,
  
  -- Horarios de disponibilidad
  `working_hours` JSON DEFAULT NULL COMMENT 'Horarios laborales por día',
  `blocked_dates` JSON DEFAULT NULL COMMENT 'Fechas bloqueadas',
  
  -- Última sincronización
  `last_sync_at` DATETIME DEFAULT NULL,
  `last_sync_status` ENUM('success','error') DEFAULT NULL,
  `last_sync_error` TEXT DEFAULT NULL,
  
  -- Webhook de Google Calendar
  `webhook_id` VARCHAR(255) DEFAULT NULL,
  `webhook_expiration` DATETIME DEFAULT NULL,
  
  -- Auditoría
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_unique` (`provider_id`),
  KEY `idx_sync_enabled` (`sync_enabled`),
  
  CONSTRAINT `fk_calendar_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración de calendarios de Google por proveedor';

-- =================================================================
-- DATOS DE EJEMPLO (Opcional - Solo para desarrollo/testing)
-- =================================================================

-- Cliente de ejemplo
INSERT IGNORE INTO `clientes` (`id`, `nombre`, `apellido`, `email`, `telefono`, `whatsapp`, `pais`, `estado`, `ciudad`, `status`, `origen_contacto`, `idioma_preferido`, `created_by`) 
VALUES 
(1, 'John', 'Doe', 'john.doe@example.com', '+1-561-123-4567', '+1-561-123-4567', 'USA', 'Florida', 'Miami', 'lead', 'web', 'en', 1);

-- =================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- =================================================================

-- Índice compuesto para búsquedas frecuentes de citas
ALTER TABLE `appointments` ADD INDEX `idx_provider_date_status` (`provider_id`, `appointment_datetime`, `status`);

-- Índice para búsquedas de paquetes por fechas
ALTER TABLE `travel_packages` ADD INDEX `idx_dates_status` (`start_date`, `end_date`, `status`);

-- =================================================================
-- COMENTARIOS FINALES
-- =================================================================

-- Este script crea las tablas necesarias para la FASE 1 del proyecto MedTravel
-- Incluye:
--   1. CRM de clientes
--   2. Sistema de citas con Google Calendar
--   3. Gestión de paquetes todo incluido
--   4. Almacenamiento de documentos
--   5. Sistema de notificaciones multi-canal
--   6. Configuración de calendarios de Google

-- Para ejecutar:
--   1. Hacer backup de la base de datos actual
--   2. Ejecutar este script completo en phpMyAdmin o consola MySQL
--   3. Verificar que todas las tablas se crearon correctamente
--   4. Proceder con la implementación del frontend y backend

-- =================================================================
-- FIN DEL SCRIPT
-- =================================================================
