-- =============================================================
-- MIGRATION: Commission settings + payment records
-- Date      : 2026-03-03
-- Idempotent: yes (IF NOT EXISTS / SHOW COLUMNS guards)
-- Additive  : no destructive ALTER statements
-- Schema    : provider_id references providers.id (medical provider entity)
-- =============================================================
-- Tables:
--   provider_commission_settings  – per-provider fee & payout config
--   commission_payments           – one row per payment attempt/record
-- External references (read-only / pre-existing tables, not modified):
--   providers             – medical provider entity
--   booking_requests      – parent booking
--   booking_request_items – line item scoping
--   usuarios              – admin / client users (updated_by, client_user_id, created_by)
-- =============================================================

SET @db := DATABASE();

-- ── 1. provider_commission_settings ─────────────────────────────────────────
-- One row per provider record (providers.id).
-- commission_pct  : MedTravel platform fee as a percentage of the service price.
-- fixed_fee_cop   : Optional flat COP fee charged on top of the %.
-- payment_terms   : Free-text schedule, e.g. "30 days after procedure".
-- stripe_account_id:  Stripe Connect account if the provider uses Stripe payouts.
-- is_active       : Soft-disabling a config without deleting it.
CREATE TABLE IF NOT EXISTS `provider_commission_settings` (
  `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `provider_id`         INT UNSIGNED    NOT NULL                  COMMENT 'FK → providers.id',
  `commission_pct`      DECIMAL(5,2)    NOT NULL DEFAULT  10.00   COMMENT 'Platform fee percentage (0-100)',
  `fixed_fee_cop`       DECIMAL(14,2)   NOT NULL DEFAULT   0.00   COMMENT 'Flat fee in COP (0 = none)',
  `currency`            CHAR(3)         NOT NULL DEFAULT 'COP'    COMMENT 'COP | USD | EUR',
  `payment_terms`       VARCHAR(255)    NOT NULL DEFAULT ''       COMMENT 'Human-readable schedule',
  `stripe_account_id`   VARCHAR(64)     NOT NULL DEFAULT ''       COMMENT 'Stripe acct_xxx or empty',
  `notes`               TEXT            NOT NULL,
  `is_active`           TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`          INT UNSIGNED             DEFAULT NULL     COMMENT 'FK → usuarios.id (admin who last edited)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_commission` (`provider_id`),
  CONSTRAINT `fk_pcs_provider`
    FOREIGN KEY (`provider_id`)
    REFERENCES `providers` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. commission_payments ───────────────────────────────────────────────────
-- One row per payment attempt / settlement record.
-- Linked to booking_requests + booking_request_items so it is always scoped.
-- status lifecycle:
--   draft     → created locally, no payment initiated
--   pending   → Checkout Session created, waiting for Stripe webhook
--   paid      → confirmed by Stripe / manual admin override
--   failed    → payment failed or expired
--   refunded  → partial or full refund issued
CREATE TABLE IF NOT EXISTS `commission_payments` (
  `id`                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `request_id`             INT UNSIGNED    NOT NULL                  COMMENT 'FK → booking_requests.id',
  `item_id`                INT UNSIGNED             DEFAULT NULL      COMMENT 'FK → booking_request_items.id (null = whole request)',
  `provider_id`            INT UNSIGNED    NOT NULL                  COMMENT 'FK → providers.id',
  `client_user_id`         INT UNSIGNED             DEFAULT NULL      COMMENT 'FK → usuarios.id (role=CLIENT)',
  `amount`                 DECIMAL(14,2)   NOT NULL                  COMMENT 'Amount charged to client (platform fee)',
  `currency`               CHAR(3)         NOT NULL DEFAULT 'COP',
  `commission_pct_snapshot`DECIMAL(5,2)    NOT NULL DEFAULT   0.00   COMMENT 'Percentage at time of payment',
  `fixed_fee_snapshot`     DECIMAL(14,2)   NOT NULL DEFAULT   0.00   COMMENT 'Fixed fee at time of payment',
  `status`                 ENUM(
                             'draft',
                             'pending',
                             'paid',
                             'failed',
                             'refunded'
                           )               NOT NULL DEFAULT 'draft',
  `stripe_session_id`      VARCHAR(255)    NOT NULL DEFAULT ''       COMMENT 'Stripe Checkout Session id',
  `stripe_payment_intent`  VARCHAR(255)    NOT NULL DEFAULT ''       COMMENT 'Stripe PaymentIntent id',
  `stripe_charge_id`       VARCHAR(255)    NOT NULL DEFAULT ''       COMMENT 'Stripe Charge id (for refunds)',
  `checkout_url`           TEXT            NOT NULL                  COMMENT 'Stripe hosted checkout URL',
  `expires_at`             DATETIME                 DEFAULT NULL      COMMENT 'Checkout session expiry',
  `paid_at`                DATETIME                 DEFAULT NULL,
  `refunded_at`            DATETIME                 DEFAULT NULL,
  `refund_amount`          DECIMAL(14,2)            DEFAULT NULL,
  `notes`                  TEXT            NOT NULL,
  `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`             INT UNSIGNED             DEFAULT NULL     COMMENT 'FK → usuarios.id (admin who created)',
  PRIMARY KEY (`id`),
  KEY `idx_cp_request`  (`request_id`),
  KEY `idx_cp_item`     (`item_id`),
  KEY `idx_cp_provider` (`provider_id`),
  KEY `idx_cp_status`   (`status`),
  KEY `idx_cp_session`  (`stripe_session_id`(64)),
  CONSTRAINT `fk_cp_provider`
    FOREIGN KEY (`provider_id`)
    REFERENCES `providers` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_request`
    FOREIGN KEY (`request_id`)
    REFERENCES `booking_requests` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_item`
    FOREIGN KEY (`item_id`)
    REFERENCES `booking_request_items` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'commission_tables_ready' AS status;
