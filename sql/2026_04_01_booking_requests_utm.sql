-- Migration: add UTM attribution columns to booking_requests
-- These columns are optional (NULL allowed); the insert code uses table_has_column_local
-- so existing environments without this migration continue to work.

ALTER TABLE `booking_requests`
    ADD COLUMN IF NOT EXISTS `utm_source`   VARCHAR(255) NULL DEFAULT NULL AFTER `terms_user_agent`,
    ADD COLUMN IF NOT EXISTS `utm_medium`   VARCHAR(255) NULL DEFAULT NULL AFTER `utm_source`,
    ADD COLUMN IF NOT EXISTS `utm_campaign` VARCHAR(255) NULL DEFAULT NULL AFTER `utm_medium`,
    ADD COLUMN IF NOT EXISTS `utm_content`  VARCHAR(255) NULL DEFAULT NULL AFTER `utm_campaign`,
    ADD COLUMN IF NOT EXISTS `utm_term`     VARCHAR(255) NULL DEFAULT NULL AFTER `utm_content`;
