-- Migration: add cw_conversation_id traceability to booking_requests
-- Source: conectarbot/chatwoot assisted booking flow
-- 2026-04-20

ALTER TABLE `booking_requests`
  ADD COLUMN IF NOT EXISTS `cw_conversation_id` VARCHAR(64) NULL DEFAULT NULL
    COMMENT 'Chatwoot conversation ID when booking was initiated from ConectarBot'
    AFTER `utm_term`;
