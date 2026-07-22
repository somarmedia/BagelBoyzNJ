-- =====================================================================
-- Bagel Boyz NJ — Online Ordering Schema
-- MySQL 5.7+ / MariaDB 10.2+  (Hostinger shared hosting default)
--
-- Import once via hPanel → Databases → phpMyAdmin → Import.
-- Safe to re-run: every statement is IF NOT EXISTS.
--
-- All money columns are INT UNSIGNED, in CENTS. Never floats.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_orders (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Short human code shouted across the counter, e.g. "BB-4821".
  order_code           VARCHAR(16)  NOT NULL,
  -- Long unguessable token; gates the public tracking page so order codes
  -- can't be enumerated to read other customers' details.
  track_token          CHAR(32)     NOT NULL,

  location_id          VARCHAR(32)  NOT NULL,

  -- pending_payment → new → in_progress → ready → completed
  --                        └→ cancelled (from any pre-completed state)
  status               VARCHAR(24)  NOT NULL DEFAULT 'pending_payment',

  customer_name        VARCHAR(120) NOT NULL,
  customer_phone       VARCHAR(32)  NOT NULL,
  customer_email       VARCHAR(190) DEFAULT NULL,

  pickup_type          VARCHAR(16)  NOT NULL DEFAULT 'asap',  -- asap | scheduled
  pickup_at            DATETIME     DEFAULT NULL,             -- promised ready time
  quoted_minutes       SMALLINT UNSIGNED DEFAULT NULL,

  subtotal_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  tax_cents            INT UNSIGNED NOT NULL DEFAULT 0,
  tip_cents            INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents          INT UNSIGNED NOT NULL DEFAULT 0,
  item_count           SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  payment_method       VARCHAR(24)  NOT NULL DEFAULT 'in_store', -- stripe | in_store
  payment_status       VARCHAR(24)  NOT NULL DEFAULT 'unpaid',   -- unpaid | paid | refunded | failed
  stripe_payment_intent VARCHAR(64) DEFAULT NULL,

  order_notes          TEXT         DEFAULT NULL,
  cancel_reason        VARCHAR(255) DEFAULT NULL,

  -- Ticket printing lifecycle, independent of order status.
  print_status         VARCHAR(16)  NOT NULL DEFAULT 'pending', -- pending | queued | printed | failed
  printed_at           DATETIME     DEFAULT NULL,

  source               VARCHAR(24)  NOT NULL DEFAULT 'web',
  customer_ip          VARBINARY(16) DEFAULT NULL,
  user_agent           VARCHAR(255) DEFAULT NULL,

  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at         DATETIME     DEFAULT NULL,
  started_at           DATETIME     DEFAULT NULL,
  ready_at             DATETIME     DEFAULT NULL,
  completed_at         DATETIME     DEFAULT NULL,
  cancelled_at         DATETIME     DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_order_code (order_code),
  UNIQUE KEY uq_track_token (track_token),
  -- The KDS board query: "active orders at this location, oldest first."
  KEY idx_kds_board (location_id, status, created_at),
  KEY idx_created (created_at),
  KEY idx_stripe_pi (stripe_payment_intent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- order_items — name/price are SNAPSHOTTED at order time.
-- If the menu changes tomorrow, this order still reprints exactly as sold.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id      INT UNSIGNED NOT NULL,
  menu_item_id  VARCHAR(64)  NOT NULL,
  category_id   VARCHAR(64)  NOT NULL DEFAULT '',
  name          VARCHAR(190) NOT NULL,
  qty           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_cents    INT UNSIGNED NOT NULL DEFAULT 0,  -- base + modifiers, per unit
  line_cents    INT UNSIGNED NOT NULL DEFAULT 0,  -- unit_cents * qty
  notes         VARCHAR(255) DEFAULT NULL,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_order (order_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES bb_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- order_item_options — the customization detail the kitchen actually reads
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_item_options (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_item_id INT UNSIGNED NOT NULL,
  group_id      VARCHAR(64)  NOT NULL,
  group_name    VARCHAR(120) NOT NULL,
  option_id     VARCHAR(64)  NOT NULL,
  option_name   VARCHAR(120) NOT NULL,
  price_cents   INT NOT NULL DEFAULT 0,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_item (order_item_id),
  CONSTRAINT fk_opts_item FOREIGN KEY (order_item_id)
    REFERENCES bb_order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- order_events — append-only audit trail. Who moved what, when.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  event      VARCHAR(48)  NOT NULL,
  note       VARCHAR(255) DEFAULT NULL,
  actor      VARCHAR(64)  NOT NULL DEFAULT 'system',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order (order_id, created_at),
  CONSTRAINT fk_events_order FOREIGN KEY (order_id)
    REFERENCES bb_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- print_jobs — one queue, drained by BOTH the Star CloudPRNT and the
-- Epson Server-Direct-Print endpoints. Whichever printer is plugged in
-- polls and claims jobs; the other simply never polls.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_print_jobs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NOT NULL,
  location_id VARCHAR(32)  NOT NULL,
  copies      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status      VARCHAR(16)  NOT NULL DEFAULT 'queued', -- queued | claimed | done | failed
  job_token   CHAR(32)     NOT NULL,
  claimed_by  VARCHAR(64)  DEFAULT NULL,              -- printer MAC / device id
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error  VARCHAR(255) DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at  DATETIME     DEFAULT NULL,
  done_at     DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_token (job_token),
  -- The printer poll query: "oldest queued job for my location."
  KEY idx_queue (location_id, status, created_at),
  CONSTRAINT fk_print_order FOREIGN KEY (order_id)
    REFERENCES bb_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- item_availability — the "86 board". A row exists ONLY when something is
-- turned off or re-enabled; absence means available.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_item_availability (
  location_id  VARCHAR(32) NOT NULL,
  menu_item_id VARCHAR(64) NOT NULL,
  available    TINYINT(1)  NOT NULL DEFAULT 1,
  -- NULL = 86'd until a human turns it back on. A date = auto-clears (daily reset).
  until_date   DATE        DEFAULT NULL,
  updated_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id, menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- store_state — per-location runtime switches the iPad can flip.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_store_state (
  location_id     VARCHAR(32) NOT NULL,
  accepting       TINYINT(1)  NOT NULL DEFAULT 1,   -- master "stop taking orders"
  prep_minutes    SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  pause_until     DATETIME    DEFAULT NULL,         -- temporary slam-rush pause
  pause_reason    VARCHAR(190) DEFAULT NULL,
  updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO bb_store_state (location_id, accepting, prep_minutes)
VALUES ('holmdel', 1, 15), ('airport', 1, 15);


-- ---------------------------------------------------------------------
-- kds_sessions — iPad login sessions (PIN-gated).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_kds_sessions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_token CHAR(48)    NOT NULL,
  location_id  VARCHAR(32)  NOT NULL,
  device_label VARCHAR(64)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_session_token (session_token),
  KEY idx_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- rate_limit — cheap abuse guard for order placement, keyed by IP.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_rate_limit (
  bucket_key  VARCHAR(64) NOT NULL,
  hits        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
