-- Migration: 2026_04_02_agent_assisted_booking
-- Adds traceability columns for agent-assisted bookings and
-- a per-user terms-acceptance record for the client portal gate.

-- ── booking_requests: agent traceability ──────────────────────────────────────

ALTER TABLE `booking_requests`
    ADD COLUMN IF NOT EXISTS `creation_source` VARCHAR(20) NOT NULL DEFAULT 'public_form'
        COMMENT 'public_form | agent_assisted | api'
        AFTER `additional_notes`,
    ADD COLUMN IF NOT EXISTS `created_by_agent` INT DEFAULT NULL
        COMMENT 'FK to usuarios.id — set when an admin agent created this booking on behalf of the client'
        AFTER `creation_source`,
    ADD COLUMN IF NOT EXISTS `agent_channel` VARCHAR(50) DEFAULT NULL
        COMMENT 'whatsapp | widget_chat | phone | other'
        AFTER `created_by_agent`;

-- ── usuarios: per-user terms acceptance ──────────────────────────────────────
-- These columns allow tracking that the individual user explicitly accepted
-- terms (independent of booking-level terms). This is the gate for the client
-- portal on first login when the account was created by an agent.

ALTER TABLE `usuarios`
    ADD COLUMN IF NOT EXISTS `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = user personally accepted terms of service'
        AFTER `cambio_password`,
    ADD COLUMN IF NOT EXISTS `terms_accepted_at` DATETIME DEFAULT NULL
        COMMENT 'Timestamp of personal acceptance'
        AFTER `terms_accepted`,
    ADD COLUMN IF NOT EXISTS `terms_version` VARCHAR(20) DEFAULT NULL
        COMMENT 'Terms version string at time of acceptance, e.g. v1.1'
        AFTER `terms_accepted_at`,
    ADD COLUMN IF NOT EXISTS `terms_ip` VARCHAR(45) DEFAULT NULL
        COMMENT 'Client IP at time of acceptance'
        AFTER `terms_version`,
    ADD COLUMN IF NOT EXISTS `terms_user_agent` VARCHAR(255) DEFAULT NULL
        COMMENT 'Client browser/device at time of acceptance'
        AFTER `terms_ip`;

-- ── Backfill: existing clients who already accepted via the public booking form
-- Any client user with an existing booking where terms_accepted = 1 is considered
-- to have already accepted. We set their personal record so they are NOT forced
-- through the gate on their next login.

UPDATE `usuarios` u
INNER JOIN `booking_requests` br ON br.client_user_id = u.id AND br.terms_accepted = 1
SET
    u.terms_accepted     = 1,
    u.terms_accepted_at  = COALESCE(br.terms_accepted_at, NOW()),
    u.terms_version      = COALESCE(br.terms_version, 'v1.1')
WHERE u.terms_accepted = 0;
