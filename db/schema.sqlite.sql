-- =====================================================================
-- Bagel Boyz NJ — Online Ordering Schema (SQLite)
--
-- Applied AUTOMATICALLY the first time the site touches the database, so
-- there is nothing to run by hand. See bb_sqlite_init() in includes/db.php.
--
-- This mirrors db/schema.sql (MySQL) — keep the two in step if you change
-- either. Differences are only what SQLite requires:
--   INTEGER PRIMARY KEY AUTOINCREMENT   instead of INT AUTO_INCREMENT
--   TEXT / INTEGER                      instead of VARCHAR / TINYINT etc.
--   no ENGINE / CHARSET clauses
--   no ON UPDATE CURRENT_TIMESTAMP      (updated_at is set by the app)
--
-- All money columns are INTEGER, in CENTS. Never floats.
-- =====================================================================

-- ---------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_orders (
  id                    INTEGER PRIMARY KEY AUTOINCREMENT,

  order_code            TEXT NOT NULL UNIQUE,   -- shouted across the counter
  track_token           TEXT NOT NULL UNIQUE,   -- gates the public tracking page

  location_id           TEXT NOT NULL,

  -- pending_payment → new → in_progress → ready → completed
  --                        └→ cancelled
  status                TEXT NOT NULL DEFAULT 'pending_payment',

  customer_name         TEXT NOT NULL,
  customer_phone        TEXT NOT NULL,
  customer_email        TEXT,

  pickup_type           TEXT NOT NULL DEFAULT 'asap',   -- asap | scheduled
  pickup_at             TEXT,
  quoted_minutes        INTEGER,

  subtotal_cents        INTEGER NOT NULL DEFAULT 0,
  tax_cents             INTEGER NOT NULL DEFAULT 0,
  tip_cents             INTEGER NOT NULL DEFAULT 0,
  total_cents           INTEGER NOT NULL DEFAULT 0,
  item_count            INTEGER NOT NULL DEFAULT 0,

  payment_method        TEXT NOT NULL DEFAULT 'in_store',
  payment_status        TEXT NOT NULL DEFAULT 'unpaid',
  stripe_payment_intent TEXT,

  order_notes           TEXT,
  cancel_reason         TEXT,

  print_status          TEXT NOT NULL DEFAULT 'pending',
  printed_at            TEXT,

  source                TEXT NOT NULL DEFAULT 'web',   -- web | preview
  customer_ip           BLOB,
  user_agent            TEXT,

  created_at            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at          TEXT,
  started_at            TEXT,
  ready_at              TEXT,
  completed_at          TEXT,
  cancelled_at          TEXT
);

-- The KDS board query: active orders at this location, oldest first.
CREATE INDEX IF NOT EXISTS idx_bb_orders_board     ON bb_orders (location_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_bb_orders_created   ON bb_orders (created_at);
CREATE INDEX IF NOT EXISTS idx_bb_orders_stripe_pi ON bb_orders (stripe_payment_intent);


-- ---------------------------------------------------------------------
-- order_items — name/price SNAPSHOTTED at order time, so an old order
-- still reprints exactly as it was sold.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_items (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id     INTEGER NOT NULL,
  menu_item_id TEXT NOT NULL,
  category_id  TEXT NOT NULL DEFAULT '',
  name         TEXT NOT NULL,
  qty          INTEGER NOT NULL DEFAULT 1,
  unit_cents   INTEGER NOT NULL DEFAULT 0,
  line_cents   INTEGER NOT NULL DEFAULT 0,
  notes        TEXT,
  sort_order   INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES bb_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bb_order_items_order ON bb_order_items (order_id);


-- ---------------------------------------------------------------------
-- order_item_options — the customization detail the kitchen reads
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_item_options (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  order_item_id INTEGER NOT NULL,
  group_id      TEXT NOT NULL,
  group_name    TEXT NOT NULL,
  option_id     TEXT NOT NULL,
  option_name   TEXT NOT NULL,
  price_cents   INTEGER NOT NULL DEFAULT 0,
  sort_order    INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (order_item_id) REFERENCES bb_order_items(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bb_item_options_item ON bb_order_item_options (order_item_id);


-- ---------------------------------------------------------------------
-- order_events — append-only audit trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_order_events (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id   INTEGER NOT NULL,
  event      TEXT NOT NULL,
  note       TEXT,
  actor      TEXT NOT NULL DEFAULT 'system',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES bb_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bb_events_order ON bb_order_events (order_id, created_at);


-- ---------------------------------------------------------------------
-- print_jobs — one queue drained by whichever printer driver is active
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_print_jobs (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id    INTEGER NOT NULL,
  location_id TEXT NOT NULL,
  copies      INTEGER NOT NULL DEFAULT 1,
  status      TEXT NOT NULL DEFAULT 'queued',   -- queued | claimed | done | failed
  job_token   TEXT NOT NULL UNIQUE,
  claimed_by  TEXT,
  attempts    INTEGER NOT NULL DEFAULT 0,
  last_error  TEXT,
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at  TEXT,
  done_at     TEXT,
  FOREIGN KEY (order_id) REFERENCES bb_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bb_print_queue ON bb_print_jobs (location_id, status, created_at);


-- ---------------------------------------------------------------------
-- item_availability — the "86 board". A row exists only when something
-- is turned off; absence means available.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_item_availability (
  location_id  TEXT NOT NULL,
  menu_item_id TEXT NOT NULL,
  available    INTEGER NOT NULL DEFAULT 1,
  until_date   TEXT,
  updated_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id, menu_item_id)
);


-- ---------------------------------------------------------------------
-- store_state — per-location switches the iPad can flip
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_store_state (
  location_id  TEXT PRIMARY KEY,
  accepting    INTEGER NOT NULL DEFAULT 1,
  prep_minutes INTEGER NOT NULL DEFAULT 15,
  pause_until  TEXT,
  pause_reason TEXT,
  updated_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO bb_store_state (location_id, accepting, prep_minutes) VALUES ('holmdel', 1, 15);
INSERT OR IGNORE INTO bb_store_state (location_id, accepting, prep_minutes) VALUES ('airport', 1, 15);


-- ---------------------------------------------------------------------
-- kds_sessions — iPad logins
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_kds_sessions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  session_token TEXT NOT NULL UNIQUE,
  location_id   TEXT NOT NULL,
  device_label  TEXT,
  created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_bb_kds_expiry ON bb_kds_sessions (expires_at);


-- ---------------------------------------------------------------------
-- rate_limit — abuse guard for order placement and PIN attempts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bb_rate_limit (
  bucket_key   TEXT PRIMARY KEY,
  hits         INTEGER NOT NULL DEFAULT 0,
  window_start TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
