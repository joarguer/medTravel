-- Table to store the editable texts for the booking form widget
CREATE TABLE IF NOT EXISTS `home_booking` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `intro_title` VARCHAR(255) DEFAULT 'Online Booking',
  `intro_paragraph` TEXT,
  `secondary_paragraph` TEXT,
  `background_img` VARCHAR(255) DEFAULT 'img/tour-booking-bg.jpg',
  `cta_text` VARCHAR(255) DEFAULT 'Submit your request',
  `cta_subtext` VARCHAR(255) DEFAULT 'Our coordinating team replies within 24 hours.',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed row
INSERT INTO home_booking () VALUES ();
