CREATE DATABASE IF NOT EXISTS studbolt_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studbolt_stock;
CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) UNIQUE NOT NULL,
 password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(160) NOT NULL,
 role ENUM('manager','stock_staff','employee') NOT NULL DEFAULT 'employee', active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE products (
 id INT AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(80) UNIQUE NOT NULL,
 size_mm VARCHAR(30) NOT NULL, diameter_mm DECIMAL(8,2) NULL, length_mm DECIMAL(8,2) NOT NULL,
 grade VARCHAR(80) NOT NULL, standard_name VARCHAR(80) DEFAULT '', description VARCHAR(255) DEFAULT '',
 qty INT NOT NULL DEFAULT 0, min_qty INT NOT NULL DEFAULT 0, location_code VARCHAR(80) DEFAULT '',
 active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_sku(sku), INDEX idx_size(size_mm)
) ENGINE=InnoDB;
CREATE TABLE transactions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NULL,
 user_username VARCHAR(80) NOT NULL, user_full_name VARCHAR(160) NOT NULL,
 txn_type ENUM('IN','OUT','ADJUST') NOT NULL, qty INT NOT NULL, balance_after INT NOT NULL,
 job_ref VARCHAR(120) DEFAULT '', note VARCHAR(255) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_transactions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_txn_date(created_at), INDEX idx_txn_product(product_id)
) ENGINE=InnoDB;
CREATE TABLE stock_requests (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL, requester_user_id INT NULL,
 requester_username VARCHAR(80) NOT NULL, requester_full_name VARCHAR(160) NOT NULL,
 request_type ENUM('IN','OUT','ADJUST') NOT NULL, qty INT NOT NULL,
 job_ref VARCHAR(120) NOT NULL DEFAULT '', note VARCHAR(255) NOT NULL DEFAULT '',
 status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 reviewer_user_id INT NULL, reviewer_username VARCHAR(80) NULL, reviewer_full_name VARCHAR(160) NULL,
 review_note VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_stock_requests_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_stock_requests_requester FOREIGN KEY(requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_stock_requests_reviewer FOREIGN KEY(reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_stock_requests_status_created(status, created_at),
 INDEX idx_stock_requests_product(product_id)
) ENGINE=InnoDB;
CREATE TABLE activity_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(80) NOT NULL,
 full_name VARCHAR(160) NULL, event_type VARCHAR(30) NOT NULL, details VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_activity_date(created_at), INDEX idx_activity_user(user_id)
) ENGINE=InnoDB;
