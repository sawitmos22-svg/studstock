-- Run this file in Supabase SQL Editor before using the application.
-- This is the PostgreSQL equivalent of database/schema.sql.
-- Create the first manager account separately with a private password.

CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  username VARCHAR(80) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'employee'
    CHECK (role IN ('manager', 'stock_staff', 'employee')),
  active SMALLINT NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id BIGSERIAL PRIMARY KEY,
  sku VARCHAR(80) UNIQUE NOT NULL,
  size_mm VARCHAR(30) NOT NULL,
  diameter_mm NUMERIC(8,2),
  length_mm NUMERIC(8,2) NOT NULL,
  grade VARCHAR(80) NOT NULL,
  standard_name VARCHAR(80) DEFAULT '',
  description VARCHAR(500) DEFAULT '',
  qty INTEGER NOT NULL DEFAULT 0,
  min_qty INTEGER NOT NULL DEFAULT 0,
  location_code VARCHAR(80) DEFAULT '',
  active SMALLINT NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_products_size ON products(size_mm);

CREATE TABLE IF NOT EXISTS transactions (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id),
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  user_username VARCHAR(80) NOT NULL,
  user_full_name VARCHAR(160) NOT NULL,
  txn_type VARCHAR(10) NOT NULL CHECK (txn_type IN ('IN', 'OUT', 'ADJUST')),
  qty INTEGER NOT NULL,
  balance_after INTEGER NOT NULL,
  job_ref VARCHAR(120) DEFAULT '',
  note VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_txn_date ON transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_txn_product ON transactions(product_id);

CREATE TABLE IF NOT EXISTS stock_requests (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id),
  requester_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  requester_username VARCHAR(80) NOT NULL,
  requester_full_name VARCHAR(160) NOT NULL,
  request_type VARCHAR(10) NOT NULL CHECK (request_type IN ('IN', 'OUT', 'ADJUST')),
  qty INTEGER NOT NULL,
  job_ref VARCHAR(120) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'PENDING'
    CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
  reviewer_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  reviewer_username VARCHAR(80),
  reviewer_full_name VARCHAR(160),
  review_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_stock_requests_status_created ON stock_requests(status, created_at);
CREATE INDEX IF NOT EXISTS idx_stock_requests_product ON stock_requests(product_id);

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(160),
  event_type VARCHAR(30) NOT NULL,
  details VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id);

CREATE OR REPLACE FUNCTION set_products_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS products_updated_at ON products;
CREATE TRIGGER products_updated_at
BEFORE UPDATE ON products
FOR EACH ROW EXECUTE FUNCTION set_products_updated_at();
