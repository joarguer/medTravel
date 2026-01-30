-- ===================================================================
-- Tabla para Configuración de Email SMTP
-- Fecha: 29 enero 2026
-- ===================================================================

CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_type` varchar(50) NOT NULL COMMENT 'patientcare, info, noreply, providers',
  `email_address` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `smtp_host` varchar(255) DEFAULT 'mail.medtravel.com.co',
  `smtp_port` int(11) DEFAULT 465,
  `smtp_secure` varchar(10) DEFAULT 'ssl' COMMENT 'ssl o tls',
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` text NOT NULL COMMENT 'Encriptada',
  `reply_to` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text,
  `last_test_date` datetime DEFAULT NULL,
  `last_test_status` varchar(50) DEFAULT NULL COMMENT 'success, failed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_type` (`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Configuración SMTP de cuentas de email';

-- Insertar configuración por defecto
INSERT INTO `email_settings` (`account_type`, `email_address`, `display_name`, `smtp_username`, `smtp_password`, `reply_to`, `description`) VALUES
('patientcare', 'patientcare@medtravel.com.co', 'MedTravel Patient Care', 'patientcare@medtravel.com.co', '', 'patientcare@medtravel.com.co', 'Para cotizaciones y comunicación con pacientes'),
('info', 'info@medtravel.com.co', 'MedTravel Information', 'info@medtravel.com.co', '', 'info@medtravel.com.co', 'Para información general y consultas'),
('noreply', 'noreply@medtravel.com.co', 'MedTravel Notifications', 'noreply@medtravel.com.co', '', 'info@medtravel.com.co', 'Para notificaciones automáticas'),
('providers', 'providers@medtravel.com.co', 'MedTravel Providers Network', 'providers@medtravel.com.co', '', 'providers@medtravel.com.co', 'Para comunicación con proveedores médicos')
ON DUPLICATE KEY UPDATE 
  email_address = VALUES(email_address),
  display_name = VALUES(display_name);
